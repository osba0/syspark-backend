<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'type_document'     => $this->type_document,
            'intitule'          => $this->intitule,
            'numero'            => $this->numero,
            'date_emission'     => $this->date_emission?->format('Y-m-d'),
            'date_expiration'   => $this->date_expiration?->format('Y-m-d'),
            'organisme_emetteur'=> $this->organisme_emetteur,
            'statut'            => $this->statut,
            'statut_calcule'    => $this->statut_calcule,
            'jours_avant_expiration' => $this->jours_avant_expiration,
            'est_actif'         => $this->est_actif,

            // Fichier — URL absolue pour que le frontend puisse y accéder
            // quelle que soit l'origine (dev Vite sur :5173 vs backend sur :8000)
            'fichier_nom'       => $this->fichier_nom,
            'fichier_url'       => $this->fichier_path
                ? Storage::disk(config('parc.uploads.disque', 'public'))->url($this->fichier_path)
                : null,

            'vehicule_id'       => $this->vehicule_id,
            'vehicule'          => $this->whenLoaded('vehicule', fn () => [
                'id'             => $this->vehicule->id,
                'immatriculation'=> $this->vehicule->immatriculation,
                'marque'         => $this->vehicule->marque,
                'modele'         => $this->vehicule->modele,
                'agence'         => $this->vehicule->agence?->nom,
            ]),

            'notes'             => $this->notes,
            'created_by'        => $this->whenLoaded('createdBy', fn () => $this->createdBy?->nom_complet),
            'created_at'        => $this->created_at?->toISOString(),
            'updated_at'        => $this->updated_at?->toISOString(),
        ];
    }
}