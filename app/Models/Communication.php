<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Annonce ou note de service diffusée dans SysPark.
 *
 * Cycle de vie éditorial : brouillon → publié → archivé.
 * Le ciblage (rôles_cibles / agences_cibles) est appliqué côté requête
 * via le scope scopeVisiblePour().
 */
class Communication extends Model implements HasMedia
{
    use SoftDeletes, LogsActivity, InteractsWithMedia;

    protected $fillable = [
        'titre', 'contenu', 'type', 'gravite',
        'roles_cibles', 'agences_cibles',
        'date_publication', 'date_expiration',
        'accuse_lecture_requis', 'statut', 'auteur_id',
    ];

    protected function casts(): array
    {
        return [
            'roles_cibles'          => 'array',
            'agences_cibles'        => 'array',
            'date_publication'      => 'datetime',
            'date_expiration'       => 'datetime',
            'accuse_lecture_requis' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function registerMediaCollections(): void
    {
        // Pièce jointe optionnelle — un seul fichier (PDF, image)
        $this->addMediaCollection('piece_jointe')
            ->singleFile()
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp']);
    }

    // ── Relations ─────────────────────────────────────────────

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    public function lectures(): HasMany
    {
        return $this->hasMany(CommunicationLecture::class);
    }

    // ── Scopes ────────────────────────────────────────────────

    /**
     * Communications publiées et dans leur fenêtre d'affichage
     * (date_publication <= now <= date_expiration, bornes nullable).
     */
    public function scopeActives(Builder $query): Builder
    {
        $now = now();

        return $query->where('statut', 'publie')
            ->where(function ($q) use ($now) {
                $q->whereNull('date_publication')->orWhere('date_publication', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('date_expiration')->orWhere('date_expiration', '>=', $now);
            });
    }

    /**
     * Filtre les communications visibles pour un utilisateur donné,
     * selon son rôle et son agence (ciblage roles_cibles / agences_cibles).
     *
     * roles_cibles = null  → visible par tous les rôles
     * agences_cibles = null → visible par toutes les agences
     */
    public function scopeVisiblePour(Builder $query, User $user): Builder
    {
        $rolesUser = $user->getRoleNames()->toArray();

        return $query->where(function ($q) use ($rolesUser) {
            $q->whereNull('roles_cibles');
            foreach ($rolesUser as $role) {
                $q->orWhereJsonContains('roles_cibles', $role);
            }
        })->where(function ($q) use ($user) {
            $q->whereNull('agences_cibles');
            if ($user->agence_id) {
                $q->orWhereJsonContains('agences_cibles', $user->agence_id);
            }
        });
    }

    // ── Helpers ───────────────────────────────────────────────

    /** Cette communication a-t-elle été lue par cet utilisateur ? */
    public function estLuePar(int $userId): bool
    {
        return $this->lectures()
            ->where('user_id', $userId)
            ->whereNotNull('lu_at')
            ->exists();
    }
}
