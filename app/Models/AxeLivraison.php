<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AxeLivraison extends Model
{
    protected $table = 'axes_livraison';
    protected $fillable = [
        'agence_id', 'nom', 'code', 'zone', 'description', 'est_actif',
    ];

    protected function casts(): array
    {
        return ['est_actif' => 'boolean'];
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function affectations(): HasMany
    {
        return $this->hasMany(Affectation::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    public function scopeActifs($query)
    {
        return $query->where('est_actif', true);
    }
}
