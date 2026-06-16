<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id'                    => $this->id,
            'titre'                 => $this->titre,
            'contenu'               => $this->contenu,
            'type'                  => $this->type,
            'gravite'               => $this->gravite,
            'roles_cibles'          => $this->roles_cibles,
            'agences_cibles'        => $this->agences_cibles,
            'date_publication'      => $this->date_publication?->toISOString(),
            'date_expiration'       => $this->date_expiration?->toISOString(),
            'accuse_lecture_requis' => $this->accuse_lecture_requis,
            'statut'                => $this->statut,
            'piece_jointe_url'      => $this->getFirstMediaUrl('piece_jointe') ?: null,
            'piece_jointe_nom'      => $this->getFirstMedia('piece_jointe')?->file_name,
            'auteur'                => $this->whenLoaded('auteur', fn () => [
                'id'          => $this->auteur->id,
                'nom_complet' => trim("{$this->auteur->prenom} {$this->auteur->name}"),
            ]),
            // Statistiques de lecture — uniquement pour les auteurs/gestionnaires (chargées via withCount)
            'nb_destinataires' => $this->when(
                isset($this->lectures_count),
                fn () => $this->lectures_count
            ),
            'nb_lectures' => $this->when(
                isset($this->lectures_lues_count),
                fn () => $this->lectures_lues_count
            ),
            // Statut de lecture pour l'utilisateur courant
            'lu'    => $user ? $this->estLuePar($user->id) : null,
            'lu_at' => $user ? $this->lectures->firstWhere('user_id', $user->id)?->lu_at?->toISOString() : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
