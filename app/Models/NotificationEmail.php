<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * File d'attente d'emails — découplée de l'envoi de notifications Laravel.
 *
 * Cycle de vie :
 *   pending → (cron toutes les minutes) → sent | failed
 *   failed  → relance automatique tant que tentatives < max_tentatives
 */
class NotificationEmail extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'to_email',
        'to_name',
        'subject',
        'body_html',
        'body_text',
        'notification_type',
        'notification_id',
        'statut',
        'tentatives',
        'max_tentatives',
        'next_attempt_at',
        'sent_at',
        'last_error',
    ];

    protected $casts = [
        'next_attempt_at' => 'datetime',
        'sent_at'         => 'datetime',
        'tentatives'      => 'integer',
        'max_tentatives'  => 'integer',
    ];

    // ── Relations ─────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ────────────────────────────────────────────────

    /** Emails prêts à être envoyés par le cron */
    public function scopeAPourEnvoi(Builder $query): Builder
    {
        return $query->where('statut', 'pending')
            ->where(function ($q) {
                $q->whereNull('next_attempt_at')
                  ->orWhere('next_attempt_at', '<=', now());
            });
    }

    public function scopeEnvoyes(Builder $query): Builder
    {
        return $query->where('statut', 'sent');
    }

    public function scopeEchecs(Builder $query): Builder
    {
        return $query->where('statut', 'failed');
    }

    // ── Actions ───────────────────────────────────────────────

    /** Marquer comme envoyé */
    public function marquerEnvoye(): void
    {
        $this->update([
            'statut'  => 'sent',
            'sent_at' => now(),
            'last_error' => null,
        ]);
    }

    /**
     * Marquer comme échoué et planifier une relance (backoff exponentiel).
     * Si max_tentatives atteint → statut définitif 'failed' sans relance.
     */
    public function marquerEchec(string $erreur): void
    {
        $tentatives = $this->tentatives + 1;
        $epuise     = $tentatives >= $this->max_tentatives;

        $this->update([
            'statut'          => 'failed',
            'tentatives'      => $tentatives,
            'last_error'      => $erreur,
            // Backoff : 5min, 15min, 45min... (3^n minutes)
            'next_attempt_at' => $epuise ? null : now()->addMinutes(5 * pow(3, $tentatives - 1)),
        ]);

        // Si relance possible, repasser en pending pour le prochain cron
        if (!$epuise) {
            $this->update(['statut' => 'pending']);
        }
    }

    /** L'email a définitivement échoué (plus de relance possible) */
    public function getEstDefinitivementEchoueAttribute(): bool
    {
        return $this->statut === 'failed' && $this->tentatives >= $this->max_tentatives;
    }
}
