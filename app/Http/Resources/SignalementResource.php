<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SignalementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'origine'           => $this->origine,
            'date_signalement'  => $this->date_signalement?->format('Y-m-d'),
            'kilometrage'       => $this->kilometrage,
            'type_defaut'       => $this->type_defaut,
            'gravite'           => $this->gravite,
            'titre'             => $this->titre,
            'description'       => $this->description,
            'etat_elements'     => $this->etat_elements,
            'statut'            => $this->statut,

            // Photos (via spatie/media-library)
            'photos'            => $this->getMedia('photos')->map(fn ($m) => [
                'id'  => $m->id,
                'url' => $m->getUrl(),
                'nom' => $m->file_name,
            ]),

            'vehicule_id'       => $this->vehicule_id,
            'vehicule'          => $this->whenLoaded('vehicule', fn () => [
                'id'             => $this->vehicule->id,
                'immatriculation'=> $this->vehicule->immatriculation,
                'marque'         => $this->vehicule->marque,
                'modele'         => $this->vehicule->modele,
            ]),

            'chauffeur_id'      => $this->chauffeur_id,
            'chauffeur'         => $this->whenLoaded('chauffeur', fn () => $this->chauffeur ? [
                'id'        => $this->chauffeur->id,
                'nom'       => $this->chauffeur->nom_complet,
                'telephone' => $this->chauffeur->telephone,
            ] : null),

            'agence_id'         => $this->agence_id,

            // Workflow
            'maintenance_id'        => $this->maintenance_id,
            'checklist_id'          => $this->checklist_id,
            'pris_en_charge_par'    => $this->whenLoaded('prisEnChargePar', fn () => $this->prisEnChargePar?->nom_complet),
            'pris_en_charge_le'     => $this->pris_en_charge_le?->format('Y-m-d H:i'),
            'resolu_par'            => $this->whenLoaded('resoluPar', fn () => $this->resoluPar?->nom_complet),
            'resolu_le'             => $this->resolu_le?->format('Y-m-d H:i'),
            'commentaire_resolution'=> $this->commentaire_resolution,

            'created_by'        => $this->whenLoaded('createdBy', fn () => $this->createdBy?->nom_complet),
            'created_at'        => $this->created_at?->toISOString(),
            'updated_at'        => $this->updated_at?->toISOString(),
        ];
    }
}
