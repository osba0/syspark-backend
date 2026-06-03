<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Maintenance extends Model implements HasMedia
{
    use SoftDeletes, LogsActivity, InteractsWithMedia;

    protected $fillable = [
        'vehicule_id',
        'agence_id',
        'fournisseur_id',
        'axe_livraison_id',
        'chauffeur_id',
        'date_travaux',
        'date_entree',
        'date_sortie',
        'kilometrage',
        'type_operation',
        'categorie_travaux',
        'titre',
        'description_travaux',
        'fournitures_mo',
        'montant_ht',
        'tva',
        'montant_ttc',
        'numero_facture',
        'date_facture',
        'statut',
        'bon_commande_id',
        'signalement_id',
        'necessite_approbation',
        'approuve_par',
        'approuve_le',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_travaux'          => 'date',
            'date_entree'           => 'date',
            'date_sortie'           => 'date',
            'date_facture'          => 'date',
            'approuve_le'           => 'datetime',
            'montant_ht'            => 'decimal:2',
            'tva'                   => 'decimal:2',
            'montant_ttc'           => 'decimal:2',
            'necessite_approbation' => 'boolean',
            'kilometrage'           => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('factures')
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png'])
            ->singleFile();

        $this->addMediaCollection('photos')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
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

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function axeLivraison(): BelongsTo
    {
        return $this->belongsTo(AxeLivraison::class);
    }

    public function chauffeur(): BelongsTo
    {
        return $this->belongsTo(Chauffeur::class);
    }

    public function bonCommande(): BelongsTo
    {
        return $this->belongsTo(BonCommande::class);
    }

    public function signalement(): BelongsTo
    {
        return $this->belongsTo(Signalement::class);
    }

    public function approuvePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approuve_par');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeParAgence($query, int $agenceId)
    {
        return $query->where('agence_id', $agenceId);
    }

    public function scopeParPeriode($query, string $debut, string $fin)
    {
        return $query->whereBetween('date_travaux', [$debut, $fin]);
    }

    public function scopeParType($query, string $type)
    {
        return $query->where('type_operation', $type);
    }

    public function scopeTerminees($query)
    {
        return $query->where('statut', 'termine');
    }

    public function scopeEnAttente($query)
    {
        return $query->whereIn('statut', ['planifie', 'en_cours']);
    }
}
