<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DotationCarburant extends Model
{
    // Laravel générerait "dotation_carburants" → on force le bon nom
    protected $table = 'dotations_carburant';

    protected $fillable = [
        'vehicule_id', 'chauffeur_id', 'agence_id', 'mois', 'annee',
        'montant_dote', 'montant_consomme',
        'litres_dotes', 'litres_consommes',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'montant_dote'      => 'decimal:2',
            'montant_consomme'  => 'decimal:2',
            'litres_dotes'      => 'decimal:2',
            'litres_consommes'  => 'decimal:2',
            'mois'              => 'integer',
            'annee'             => 'integer',
        ];
    }

    public function vehicule(): BelongsTo  { return $this->belongsTo(Vehicule::class); }
    public function agence(): BelongsTo    { return $this->belongsTo(Agence::class); }
    public function chauffeur(): BelongsTo { return $this->belongsTo(Chauffeur::class); }

    /** Écart montant dotation vs consommation (positif = économie, négatif = dépassement) */
    public function getEcartAttribute(): float
    {
        return (float)$this->montant_dote - (float)$this->montant_consomme;
    }

    /** Taux de consommation montant en % */
    public function getTauxConsommationAttribute(): float
    {
        if ((float)$this->montant_dote <= 0) return 0;
        return round(((float)$this->montant_consomme / (float)$this->montant_dote) * 100, 1);
    }

    /** Écart litres */
    public function getEcartLitresAttribute(): float
    {
        return (float)$this->litres_dotes - (float)$this->litres_consommes;
    }

    /** Taux consommation litres en % */
    public function getTauxConsommationLitresAttribute(): float
    {
        if ((float)$this->litres_dotes <= 0) return 0;
        return round(((float)$this->litres_consommes / (float)$this->litres_dotes) * 100, 1);
    }

    public function scopeParAnnee($query, int $annee)
    {
        return $query->where('annee', $annee);
    }

    public function scopeParMois($query, int $mois, int $annee)
    {
        return $query->where('mois', $mois)->where('annee', $annee);
    }
}