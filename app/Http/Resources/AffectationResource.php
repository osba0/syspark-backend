<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AffectationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'type_affectation' => $this->type_affectation,
            'statut'           => $this->statut,
            'date_debut'       => $this->date_debut?->format('Y-m-d'),
            'date_fin'         => $this->date_fin?->format('Y-m-d'),
            'kilometrage_debut'=> $this->kilometrage_debut,
            'kilometrage_fin'  => $this->kilometrage_fin,
            'km_parcourus'     => $this->km_parcorus,
            'duree_jours'      => $this->duree_jours,

            'vehicule_id'      => $this->vehicule_id,
            'vehicule'         => $this->whenLoaded('vehicule', fn () => [
                'id'             => $this->vehicule->id,
                'immatriculation'=> $this->vehicule->immatriculation,
                'marque'         => $this->vehicule->marque,
                'modele'         => $this->vehicule->modele,
                'type_vehicule'  => $this->vehicule->type_vehicule,
            ]),

            'chauffeur_id'     => $this->chauffeur_id,
            'chauffeur'        => $this->whenLoaded('chauffeur', fn () => $this->chauffeur ? [
                'id'        => $this->chauffeur->id,
                'nom'       => $this->chauffeur->nom_complet,
                'telephone' => $this->chauffeur->telephone,
            ] : null),

            'attributaire_id'  => $this->attributaire_id,
            'agence_id'        => $this->agence_id,
            'agence'           => $this->whenLoaded('agence', fn () => [
                'id'   => $this->agence->id,
                'nom'  => $this->agence->nom,
                'code' => $this->agence->code,
            ]),

            'axe_livraison_id' => $this->axe_livraison_id,
            'axe_livraison'    => $this->whenLoaded('axeLivraison', fn () => $this->axeLivraison ? [
                'id'  => $this->axeLivraison->id,
                'nom' => $this->axeLivraison->nom,
                'code'=> $this->axeLivraison->code,
            ] : null),

            'validee_par'      => $this->whenLoaded('validePar', fn () => $this->validePar?->nom_complet),
            'validee_le'       => $this->validee_le?->format('Y-m-d H:i'),
            'notes'            => $this->notes,
            'created_at'       => $this->created_at?->toISOString(),
        ];
    }
}
