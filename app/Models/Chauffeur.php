<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Chauffeur extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'agence_id',
        'user_id',
        'nom',
        'prenom',
        'telephone',
        'email',
        'date_naissance',
        'adresse',
        'cni',
        'numero_permis',
        'categorie_permis',
        'date_delivrance_permis',
        'date_expiration_permis',
        'date_embauche',
        'matricule_interne',
        'statut',
        'photo',
        'photo_profil',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_naissance'         => 'date',
            'date_delivrance_permis' => 'date',
            'date_expiration_permis' => 'date',
            'date_embauche'          => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    // ============================================================
    // Relations
    // ============================================================

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    // ============================================================
    // Accesseurs
    // ============================================================

    /** URL de la photo de profil du chauffeur */
    public function getPhotoProfilUrlAttribute(): ?string
    {
        if (! $this->photo_profil) return null;
        return url('storage/' . $this->photo_profil);
    }

    public function getNomCompletAttribute(): string
    {
        return "{$this->prenom} {$this->nom}";
    }

    public function getJoursAvantExpirationPermisAttribute(): ?int
    {
        if (!$this->date_expiration_permis) {
            return null;
        }
        return now()->diffInDays($this->date_expiration_permis, false);
    }

    public function getVehiculeActuelAttribute(): ?Vehicule
    {
        return $this->affectationActive?->vehicule;
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

    public function scopePermisExpirantDans($query, int $jours = 30)
    {
        return $query->whereNotNull('date_expiration_permis')
            ->where('date_expiration_permis', '<=', now()->addDays($jours));
    }
}