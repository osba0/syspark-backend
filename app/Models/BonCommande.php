<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class BonCommande extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'bons_commande';

    protected $fillable = [
        'agence_id', 'fournisseur_id', 'vehicule_id', 'numero_bc',
        'date_commande', 'date_livraison_prevue', 'date_livraison_reelle',
        'lignes', 'montant_ht', 'tva', 'montant_ttc',
        'statut', 'cree_par', 'approuve_par', 'approuve_le',
        'motif_rejet', 'observations',
    ];

    protected function casts(): array
    {
        return [
            'date_commande'          => 'date',
            'date_livraison_prevue'  => 'date',
            'date_livraison_reelle'  => 'date',
            'approuve_le'            => 'datetime',
            'lignes'                 => 'array',
            'montant_ht'             => 'decimal:2',
            'tva'                    => 'decimal:2',
            'montant_ttc'            => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['statut'])->logOnlyDirty();
    }

    // ============================================================
    // Relations
    // ============================================================

    public function agence(): BelongsTo      { return $this->belongsTo(Agence::class); }
    public function fournisseur(): BelongsTo { return $this->belongsTo(Fournisseur::class); }
    public function vehicule(): BelongsTo    { return $this->belongsTo(Vehicule::class); }
    public function creePar(): BelongsTo     { return $this->belongsTo(User::class, 'cree_par'); }
    public function approuvePar(): BelongsTo { return $this->belongsTo(User::class, 'approuve_par'); }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class, 'bon_commande_id');
    }

    // ============================================================
    // Auto-génération numéro BC
    // ============================================================

    public static function genererNumeroBc(): string
    {
        $annee    = date('Y');
        $dernierBc = self::whereYear('date_commande', $annee)->max('numero_bc');
        $numero   = $dernierBc ? (int) substr($dernierBc, -4) + 1 : 1;
        return sprintf('BC-%s-%04d', $annee, $numero);
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeParAgence($query, int $id) { return $query->where('agence_id', $id); }
    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'soumis');
    }
}
