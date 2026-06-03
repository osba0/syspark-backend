<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfigEntreprise extends Model
{
    protected $table = 'config_entreprise';

    protected $fillable = [
        'nom', 'ninea', 'rc', 'adresse',
        'telephone', 'email', 'site_web',
        'logo', 'couleur_1', 'couleur_2', 'couleur_3',
        'notes',
    ];

    /** Retourne l'unique enregistrement de configuration */
    public static function instance(): static
    {
        return static::firstOrCreate([], [
            'nom'       => 'Gestion Parc Auto',
            'couleur_1' => '#1E3A5F',
            'couleur_2' => '#2E86C1',
            'couleur_3' => '#1ABC9C',
        ]);
    }

    /** URL absolue du logo */
    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo) return null;
        return url('storage/' . $this->logo);
    }
}
