<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Signalement extends Model implements HasMedia
{
    use LogsActivity, InteractsWithMedia;

    protected $fillable = [
        'vehicule_id',
        'chauffeur_id',
        'agence_id',
        'origine',
        'date_signalement',
        'kilometrage',
        'type_defaut',
        'gravite',
        'titre',
        'description',
        'etat_elements',
        'photos',
        'statut',
        'maintenance_id',
        'checklist_id',
        'pris_en_charge_par',
        'pris_en_charge_le',
        'resolu_par',
        'resolu_le',
        'commentaire_resolution',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_signalement'   => 'date',
            'pris_en_charge_le'  => 'datetime',
            'resolu_le'          => 'datetime',
            'etat_elements'      => 'array',
            'photos'             => 'array',
            'kilometrage'        => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['statut', 'gravite'])->logOnlyDirty();
    }

    public function registerMediaCollections(): void
    {
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

    public function chauffeur(): BelongsTo
    {
        return $this->belongsTo(Chauffeur::class);
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(Maintenance::class);
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }

    public function prisEnChargePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pris_en_charge_par');
    }

    public function resoluPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolu_par');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeOuverts($query)
    {
        return $query->whereIn('statut', ['nouveau', 'en_cours']);
    }

    public function scopeParAgence($query, int $agenceId)
    {
        return $query->where('agence_id', $agenceId);
    }

    public function scopeCritiques($query)
    {
        return $query->where('gravite', 'critique');
    }

    public function scopeNonTraites($query, int $jours = 3)
    {
        return $query->whereIn('statut', ['nouveau'])
            ->where('date_signalement', '<=', now()->subDays($jours));
    }
}
