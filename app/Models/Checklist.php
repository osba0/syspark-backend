<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Checklist extends Model
{
    use LogsActivity;

    protected $fillable = [
        'vehicule_id',
        'chauffeur_id',
        'agence_id',
        'type_checklist',
        'date',
        'kilometrage',
        'data_json',
        'non_conformites',
        'statut',
        'resultat_global',
        'valide_par',
        'valide_le',
        'commentaire_validation',
        'signalement_genere_id',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'date'             => 'date',
            'valide_le'        => 'datetime',
            'data_json'        => 'array',
            'non_conformites'  => 'array',
            'kilometrage'      => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['statut', 'resultat_global'])->logOnlyDirty();
    }

    // ============================================================
    // Relations
    // ============================================================

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function chauffeur(): BelongsTo
    {
        return $this->belongsTo(Chauffeur::class);
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function signalementGenere(): BelongsTo
    {
        return $this->belongsTo(Signalement::class, 'signalement_genere_id');
    }

    // ============================================================
    // Accesseurs
    // ============================================================

    public function getNombreNonConformitesAttribute(): int
    {
        return count($this->non_conformites ?? []);
    }

    public function getEstConformeAttribute(): bool
    {
        return $this->resultat_global === 'conforme';
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeParAgence($query, int $agenceId)
    {
        return $query->where('agence_id', $agenceId);
    }

    public function scopeNonConformes($query)
    {
        return $query->where('resultat_global', 'non_conforme');
    }

    public function scopeParType($query, string $type)
    {
        return $query->where('type_checklist', $type);
    }
}
