<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChecklistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'type_checklist'  => $this->type_checklist,
            'date'            => $this->date?->format('Y-m-d'),
            'kilometrage'     => $this->kilometrage,
            'statut'          => $this->statut,
            'resultat_global' => $this->resultat_global,
            'est_conforme'    => $this->est_conforme,
            'nb_non_conformites' => $this->nombre_non_conformites,
            'non_conformites' => $this->non_conformites,
            'data_json'       => $this->data_json,
            'observations'    => $this->observations,

            'vehicule_id'     => $this->vehicule_id,
            'vehicule'        => $this->whenLoaded('vehicule', fn () => [
                'id'             => $this->vehicule->id,
                'immatriculation'=> $this->vehicule->immatriculation,
                'marque'         => $this->vehicule->marque,
                'modele'         => $this->vehicule->modele,
            ]),

            'chauffeur_id'    => $this->chauffeur_id,
            'chauffeur'       => $this->whenLoaded('chauffeur', fn () => $this->chauffeur ? [
                'id'  => $this->chauffeur->id,
                'nom' => $this->chauffeur->nom_complet,
            ] : null),

            'agence_id'       => $this->agence_id,

            'valide_par'      => $this->whenLoaded('validePar', fn () => $this->validePar?->nom_complet),
            'valide_le'       => $this->valide_le?->format('Y-m-d H:i'),
            'commentaire_validation' => $this->commentaire_validation,

            'signalement_genere_id' => $this->signalement_genere_id,

            'created_at'      => $this->created_at?->toISOString(),
            'updated_at'      => $this->updated_at?->toISOString(),
        ];
    }
}
