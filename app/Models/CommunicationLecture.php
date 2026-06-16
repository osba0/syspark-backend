<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Accusé de lecture d'une communication par un utilisateur.
 * Un enregistrement existe (lu_at = null) dès qu'une communication
 * ciblée est créée pour permettre le comptage "non lus" sans calcul
 * coûteux ; lu_at est rempli au moment de l'accusé de lecture.
 */
class CommunicationLecture extends Model
{
    protected $fillable = ['communication_id', 'user_id', 'lu_at'];

    protected function casts(): array
    {
        return ['lu_at' => 'datetime'];
    }

    public function communication(): BelongsTo
    {
        return $this->belongsTo(Communication::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
