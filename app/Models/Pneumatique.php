<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pneumatique extends Model
{
    protected $fillable = [
        'vehicule_id', 'chauffeur_id', 'agence_id', 'fournisseur_id',
        'date', 'type_operation', 'position', 'marque_pneu', 'dimension',
        'quantite', 'prix_unitaire', 'montant_total', 'kilometrage',
        'commentaire', 'saisi_par',
    ];

    protected function casts(): array
    {
        return [
            'date'          => 'date',
            'prix_unitaire' => 'decimal:2',
            'montant_total' => 'decimal:2',
            'quantite'      => 'integer',
            'kilometrage'   => 'integer',
        ];
    }

    public function vehicule(): BelongsTo    { return $this->belongsTo(Vehicule::class); }
    public function chauffeur(): BelongsTo   { return $this->belongsTo(Chauffeur::class); }
    public function agence(): BelongsTo      { return $this->belongsTo(Agence::class); }
    public function fournisseur(): BelongsTo { return $this->belongsTo(Fournisseur::class); }
    public function saisiPar(): BelongsTo    { return $this->belongsTo(User::class, 'saisi_par'); }

    public function scopeParAgence($query, int $id) { return $query->where('agence_id', $id); }
    public function scopeAchatsNeuf($query) { return $query->where('type_operation', 'achat_neuf'); }
}
