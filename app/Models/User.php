<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\Traits\CausesActivity;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes, CausesActivity;
    
    /**
     * Guard Spatie Permission.
     *
     * Sanctum utilise le guard 'sanctum' pour l'authentification API,
     * mais tous les rôles/permissions sont créés avec guard_name = 'web'.
     * Sans cette propriété, Spatie v6 résout le guard depuis le contexte
     * de la requête (sanctum) → ne trouve pas les rôles 'web' → 403.
     *
     * Cette propriété force Spatie à toujours utiliser 'web' pour
     * résoudre les rôles et permissions de ce modèle.
     */
    protected string $guard_name = 'web';

    protected $fillable = [
        'agence_id',
        'name',
        'prenom',
        'email',
        'password',
        'telephone',
        'fonction',
        'est_actif',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
            'est_actif'         => 'boolean',
        ];
    }

    // ============================================================
    // Relations
    // ============================================================

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function chauffeur(): HasMany
    {
        return $this->hasMany(Chauffeur::class);
    }

    // ============================================================
    // Accesseurs
    // ============================================================

    public function getNomCompletAttribute(): string
    {
        return trim("{$this->prenom} {$this->name}");
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeActifs($query)
    {
        return $query->where('est_actif', true);
    }

    public function scopeParAgence($query, int $agenceId)
    {
        return $query->where('agence_id', $agenceId);
    }
}
