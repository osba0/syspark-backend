<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class DocumentVehicule extends Model
{
    use LogsActivity;

    protected $table = 'documents_vehicule';

    protected $fillable = [
        'vehicule_id', 'type_document', 'intitule', 'numero',
        'date_emission', 'date_expiration', 'organisme_emetteur',
        'statut', 'fichier_path', 'fichier_nom', 'est_actif',
        'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_emission'   => 'date',
            'date_expiration' => 'date',
            'est_actif'       => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function vehicule(): BelongsTo  { return $this->belongsTo(Vehicule::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    // ============================================================
    // Accesseurs
    // ============================================================

    public function getJoursAvantExpirationAttribute(): ?int
    {
        if (!$this->date_expiration) return null;
        return now()->diffInDays($this->date_expiration, false);
    }

    public function getStatutCalculeAttribute(): string
    {
        $jours = $this->jours_avant_expiration;
        if ($jours === null)   return 'inconnu';
        if ($jours < 0)        return 'expire';
        if ($jours <= 15)      return 'critique';
        if ($jours <= 30)      return 'warning';
        return 'valide';
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeActifs($query)       { return $query->where('est_actif', true); }
    public function scopeExpires($query)      { return $query->where('date_expiration', '<', now()); }
    public function scopeExpirantDans($query, int $jours)
    {
        return $query->whereNotNull('date_expiration')
            ->whereBetween('date_expiration', [now(), now()->addDays($jours)]);
    }
}
