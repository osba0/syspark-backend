<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BonCommandeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'numero_bc'             => $this->numero_bc,
            'statut'                => $this->statut,
            'date_commande'         => $this->date_commande?->format('Y-m-d'),
            'date_livraison_prevue' => $this->date_livraison_prevue?->format('Y-m-d'),
            'date_livraison_reelle' => $this->date_livraison_reelle?->format('Y-m-d'),

            // Financier
            'lignes'      => collect($this->lignes)->map(fn ($l) => [
                'description'  => $l['description'],
                'quantite'     => (float)($l['quantite'] ?? 1),
                'unite'        => $l['unite'] ?? null,
                'prix_unitaire'=> (float)($l['prix_unitaire'] ?? 0),
                'total'        => round((float)($l['quantite'] ?? 1) * (float)($l['prix_unitaire'] ?? 0), 2),
            ]),
            'montant_ht'  => (float)$this->montant_ht,
            'tva'         => (float)$this->tva,
            'montant_ttc' => (float)$this->montant_ttc,

            'agence_id'     => $this->agence_id,
            'agence'        => $this->whenLoaded('agence', fn () => [
                'id'  => $this->agence->id,
                'nom' => $this->agence->nom,
            ]),

            'fournisseur_id'=> $this->fournisseur_id,
            'fournisseur'   => $this->whenLoaded('fournisseur', fn () => $this->fournisseur ? [
                'id'   => $this->fournisseur->id,
                'nom'  => $this->fournisseur->nom,
                'type' => $this->fournisseur->type,
                'telephone' => $this->fournisseur->telephone,
            ] : null),

            'vehicule_id'   => $this->vehicule_id,
            'vehicule'      => $this->whenLoaded('vehicule', fn () => $this->vehicule ? [
                'id'             => $this->vehicule->id,
                'immatriculation'=> $this->vehicule->immatriculation,
            ] : null),

            // Workflow
            'cree_par'      => $this->whenLoaded('creePar', fn () => $this->creePar?->nom_complet),
            'approuve_par'  => $this->whenLoaded('approuvePar', fn () => $this->approuvePar?->nom_complet),
            'approuve_le'   => $this->approuve_le?->format('Y-m-d H:i'),
            'motif_rejet'   => $this->motif_rejet,

            'observations'  => $this->observations,
            'created_at'    => $this->created_at?->toISOString(),
            'updated_at'    => $this->updated_at?->toISOString(),
        ];
    }
}
