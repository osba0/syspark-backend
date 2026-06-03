<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alerte extends Model
{
    protected $fillable = [
        'vehicule_id', 'chauffeur_id', 'agence_id',
        'type_alerte', 'titre', 'message',
        'echeance', 'jours_restants', 'niveau', 'statut',
        'destinataires', 'modele_source', 'source_id', 'envoyee_le',
    ];

    protected function casts(): array
    {
        return [
            'echeance'        => 'date',
            'envoyee_le'      => 'datetime',
            'destinataires'   => 'array',
            'jours_restants'  => 'integer',
        ];
    }

    // ============================================================
    // Relations
    // ============================================================

    public function vehicule(): BelongsTo  { return $this->belongsTo(Vehicule::class); }
    public function chauffeur(): BelongsTo { return $this->belongsTo(Chauffeur::class); }
    public function agence(): BelongsTo    { return $this->belongsTo(Agence::class); }

    // ============================================================
    // Accesseurs
    // ============================================================

    public function getEstLueParAttribute(): bool
    {
        $userId = auth()->id();
        if (!$userId || !$this->destinataires) return false;
        foreach ($this->destinataires as $d) {
            if (($d['user_id'] ?? null) == $userId) {
                return !empty($d['lu_le']);
            }
        }
        return false;
    }

    // Marquer comme lue par un utilisateur
    public function marquerLuePar(int $userId): void
    {
        $destinataires = $this->destinataires ?? [];
        foreach ($destinataires as &$d) {
            if (($d['user_id'] ?? null) == $userId) {
                $d['lu_le'] = now()->toISOString();
            }
        }
        $this->update(['destinataires' => $destinataires, 'statut' => 'lue']);
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeActives($query)  { return $query->where('statut', 'active'); }
    public function scopeDanger($query)   { return $query->where('niveau', 'danger'); }
    public function scopeParAgence($query, int $id) { return $query->where('agence_id', $id); }
}
