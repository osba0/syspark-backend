<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Agence extends Model
{
    use LogsActivity;

    protected $fillable = [
        'nom',
        'code',
        'ville',
        'adresse',
        'telephone',
        'email',
        'est_active',
    ];

    protected function casts(): array
    {
        return [
            'est_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    // ============================================================
    // Relations
    // ============================================================

    public function vehicules(): HasMany
    {
        return $this->hasMany(Vehicule::class);
    }

    public function chauffeurs(): HasMany
    {
        return $this->hasMany(Chauffeur::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function axesLivraison(): HasMany
    {
        return $this->hasMany(AxeLivraison::class);
    }

    public function affectations(): HasMany
    {
        return $this->hasMany(Affectation::class);
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeActives($query)
    {
        return $query->where('est_active', true);
    }
}
