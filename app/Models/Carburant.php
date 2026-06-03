<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Carburant extends Model
{
    protected $fillable = [
        'vehicule_id', 'chauffeur_id', 'agence_id', 'axe_livraison_id',
        'date', 'litres', 'montant', 'prix_unitaire', 'type_carburant',
        'kilometrage', 'km_precedent', 'numero_transaction', 'station',
        'est_complet', 'notes', 'saisi_par',
    ];

    protected function casts(): array
    {
        return [
            'date'          => 'date',
            'litres'        => 'decimal:2',
            'montant'       => 'decimal:2',
            'prix_unitaire' => 'decimal:2',
            'est_complet'   => 'boolean',
            'kilometrage'   => 'integer',
            'km_precedent'  => 'integer',
        ];
    }

    public function vehicule(): BelongsTo   { return $this->belongsTo(Vehicule::class); }
    public function chauffeur(): BelongsTo  { return $this->belongsTo(Chauffeur::class); }
    public function agence(): BelongsTo     { return $this->belongsTo(Agence::class); }
    public function axeLivraison(): BelongsTo { return $this->belongsTo(AxeLivraison::class); }
    public function saisiPar(): BelongsTo   { return $this->belongsTo(User::class, 'saisi_par'); }

    /** Consommation aux 100 km calculée */
    public function getConso100KmAttribute(): ?float
    {
        if ($this->km_precedent && $this->kilometrage && $this->litres > 0) {
            $km = $this->kilometrage - $this->km_precedent;
            if ($km > 0) {
                return round(($this->litres / $km) * 100, 2);
            }
        }
        return null;
    }

    public function scopeParAgence($query, int $id) { return $query->where('agence_id', $id); }
    public function scopeParPeriode($query, string $debut, string $fin)
    {
        return $query->whereBetween('date', [$debut, $fin]);
    }
}
