<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Vehicule extends Model implements HasMedia
{
    use SoftDeletes, LogsActivity, InteractsWithMedia;
    

    protected $fillable = [
        'agence_id',
        'immatriculation',
        'marque',
        'modele',
        'type_vehicule',
        'categorie',
        'annee_fabrication',
        'date_mise_circulation',
        'couleur',
        'numero_chassis',
        'numero_moteur',
        'energie',
        'statut',
        'kilometrage_actuel',
        'prochain_entretien_km',
        'prochain_entretien_date',
        'intervalle_entretien_km',
        'date_mise_circulation_officielle',
        'date_derniere_visite_tech',
        'date_prochaine_visite_tech',
        'date_expiration_assurance',
        'numero_assurance',
        'compagnie_assurance',
        'numero_carte_carburant',
        'type_carburant',
        'photo_principale',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_mise_circulation'            => 'date',
            'date_mise_circulation_officielle' => 'date',
            'date_derniere_visite_tech'         => 'date',
            'date_prochaine_visite_tech'        => 'date',
            'date_expiration_assurance'         => 'date',
            'prochain_entretien_date'           => 'date',
            'kilometrage_actuel'                => 'integer',
            'prochain_entretien_km'             => 'integer',
            'intervalle_entretien_km'           => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('documents')
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png']);
    }

    // ============================================================
    // Relations
    // ============================================================

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function affectations(): HasMany
    {
        return $this->hasMany(Affectation::class);
    }

    public function affectationActive(): HasOne
    {
        return $this->hasOne(Affectation::class)
            ->where('statut', 'active')
            ->whereNull('date_fin')
            ->latest();
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(Checklist::class);
    }

    public function signalements(): HasMany
    {
        return $this->hasMany(Signalement::class);
    }

    public function carburants(): HasMany
    {
        return $this->hasMany(Carburant::class);
    }

    public function pneumatiques(): HasMany
    {
        return $this->hasMany(Pneumatique::class);
    }

    public function documentsVehicule(): HasMany
    {
        return $this->hasMany(DocumentVehicule::class);
    }

    public function alertes(): HasMany
    {
        return $this->hasMany(Alerte::class);
    }

    public function dotationsCarburant(): HasMany
    {
        return $this->hasMany(DotationCarburant::class);
    }

    // ============================================================
    // Accesseurs calculés
    // ============================================================

    public function getJoursAvantVtAttribute(): ?int
    {
        if (!$this->date_prochaine_visite_tech) {
            return null;
        }
        return now()->diffInDays($this->date_prochaine_visite_tech, false);
    }

    public function getJoursAvantAssuranceAttribute(): ?int
    {
        if (!$this->date_expiration_assurance) {
            return null;
        }
        return now()->diffInDays($this->date_expiration_assurance, false);
    }

    public function getStatutVtAttribute(): string
    {
        $jours = $this->jours_avant_vt;
        if ($jours === null)  return 'inconnu';
        if ($jours < 0)       return 'expire';
        if ($jours <= 15)     return 'critique';
        if ($jours <= 30)     return 'warning';
        return 'valide';
    }

    public function getStatutAssuranceAttribute(): string
    {
        $jours = $this->jours_avant_assurance;
        if ($jours === null)  return 'inconnu';
        if ($jours < 0)       return 'expire';
        if ($jours <= 15)     return 'critique';
        if ($jours <= 30)     return 'warning';
        return 'valide';
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeActifs($query)
    {
        return $query->where('statut', 'actif');
    }

    public function scopeParAgence($query, int $agenceId)
    {
        return $query->where('agence_id', $agenceId);
    }

    public function scopeParType($query, string $type)
    {
        return $query->where('type_vehicule', $type);
    }

    public function scopeVtProchaine($query, int $jours = 30)
    {
        return $query->whereNotNull('date_prochaine_visite_tech')
            ->where('date_prochaine_visite_tech', '<=', now()->addDays($jours));
    }

    public function scopeAssuranceProchaine($query, int $jours = 30)
    {
        return $query->whereNotNull('date_expiration_assurance')
            ->where('date_expiration_assurance', '<=', now()->addDays($jours));
    }

    public function scopeEntretienDu($query)
    {
        return $query->whereNotNull('prochain_entretien_km')
            ->whereColumn('kilometrage_actuel', '>=', 'prochain_entretien_km');
    }
}
