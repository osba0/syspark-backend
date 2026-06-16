<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Fournisseur extends Model implements HasMedia
{
    use LogsActivity, InteractsWithMedia;

    protected $fillable = [
        'nom', 'type', 'telephone', 'email',
        'adresse', 'ville', 'specialite', 'ninea', 'est_actif', 'notes',
    ];

    protected function casts(): array
    {
        return ['est_actif' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function registerMediaCollections(): void
    {
        // Logo optionnel — un seul fichier, remplacé automatiquement
        // si un nouveau logo est uploadé (singleFile)
        $this->addMediaCollection('logo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml']);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    public function bonsCommande(): HasMany
    {
        return $this->hasMany(BonCommande::class);
    }

    public function pneumatiques(): HasMany
    {
        return $this->hasMany(Pneumatique::class);
    }

    // Dépense totale pour ce fournisseur
    public function getMontantTotalAttribute(): float
    {
        return $this->maintenances()->where('statut', 'termine')->sum('montant_ttc');
    }

    public function scopeActifs($query)
    {
        return $query->where('est_actif', true);
    }
}