<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarburantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'date'              => $this->date?->format('Y-m-d'),
            'litres'            => (float)$this->litres,
            'montant'           => (float)$this->montant,
            'prix_unitaire'     => $this->prix_unitaire ? (float)$this->prix_unitaire : null,
            'type_carburant'    => $this->type_carburant,
            'kilometrage'       => $this->kilometrage,
            'km_precedent'      => $this->km_precedent,
            'conso_100km'       => $this->conso_100km,
            'numero_transaction'=> $this->numero_transaction,
            'station'           => $this->station,
            'est_complet'       => $this->est_complet,
            'notes'             => $this->notes,

            'vehicule_id'       => $this->vehicule_id,
            'vehicule'          => $this->whenLoaded('vehicule', fn () => [
                'id'             => $this->vehicule->id,
                'immatriculation'=> $this->vehicule->immatriculation,
                'marque'         => $this->vehicule->marque,
                'modele'         => $this->vehicule->modele,
            ]),

            'chauffeur_id'      => $this->chauffeur_id,
            'chauffeur'         => $this->whenLoaded('chauffeur', fn () => $this->chauffeur ? [
                'id'  => $this->chauffeur->id,
                'nom' => $this->chauffeur->nom_complet,
            ] : null),

            'agence_id'         => $this->agence_id,
            'axe_livraison_id'  => $this->axe_livraison_id,
            'axe_livraison'     => $this->whenLoaded('axeLivraison', fn () => $this->axeLivraison?->nom),

            'saisi_par'         => $this->saisi_par,
            'created_at'        => $this->created_at?->toISOString(),
        ];
    }
}
