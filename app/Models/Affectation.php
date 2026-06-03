<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Affectation extends Model
{
    use LogsActivity;

    protected $fillable = [
        'vehicule_id',
        'agence_id',
        'axe_livraison_id',
        'chauffeur_id',
        'attributaire_id',
        'date_debut',
        'date_fin',
        'kilometrage_debut',
        'kilometrage_fin',
        'type_affectation',
        'statut',
        'validee_par',
        'validee_le',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_debut'  => 'date',
            'date_fin'    => 'date',
            'validee_le'  => 'datetime',
            'kilometrage_debut' => 'integer',
            'kilometrage_fin'   => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    // ============================================================
    // Relations
    // ============================================================

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function axeLivraison(): BelongsTo
    {
        return $this->belongsTo(AxeLivraison::class);
    }

    public function chauffeur(): BelongsTo
    {
        return $this->belongsTo(Chauffeur::class);
    }

    public function attributaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attributaire_id');
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validee_par');
    }

    // ============================================================
    // Accesseurs
    // ============================================================

    public function getKmParcorusAttribute(): ?int
    {
        if ($this->kilometrage_fin && $this->kilometrage_debut) {
            return $this->kilometrage_fin - $this->kilometrage_debut;
        }
        return null;
    }

    public function getDureeJoursAttribute(): int
    {
        $fin = $this->date_fin ?? now();
        return (int) $this->date_debut->diffInDays($fin);
    }

    public function getActeurAttribute(): string
    {
        if ($this->chauffeur) {
            return $this->chauffeur->nom_complet;
        }
        if ($this->attributaire) {
            return $this->attributaire->nom_complet;
        }
        return 'Non assigné';
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeActives($query)
    {
        return $query->where('statut', 'active')->whereNull('date_fin');
    }

    public function scopeParAgence($query, int $agenceId)
    {
        return $query->where('agence_id', $agenceId);
    }
}
