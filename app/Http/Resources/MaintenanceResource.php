<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'date_travaux'        => $this->date_travaux?->format('Y-m-d'),
            'date_entree'         => $this->date_entree?->format('Y-m-d'),
            'date_sortie'         => $this->date_sortie?->format('Y-m-d'),
            'kilometrage'         => $this->kilometrage,
            'type_operation'      => $this->type_operation,
            'categorie_travaux'   => $this->categorie_travaux,
            'titre'               => $this->titre,
            'description_travaux' => $this->description_travaux,
            'fournitures_mo'      => $this->fournitures_mo,
            'montant_ht'          => (float)$this->montant_ht,
            'tva'                 => (float)$this->tva,
            'montant_ttc'         => (float)$this->montant_ttc,
            'numero_facture'      => $this->numero_facture,
            'date_facture'        => $this->date_facture?->format('Y-m-d'),
            'statut'              => $this->statut,
            'necessite_approbation' => $this->necessite_approbation,

            // Facture (media)
            'facture_url'         => $this->getFirstMediaUrl('factures'),

            'vehicule_id'         => $this->vehicule_id,
            'vehicule'            => $this->whenLoaded('vehicule', fn () => [
                'id'             => $this->vehicule->id,
                'immatriculation'=> $this->vehicule->immatriculation,
                'marque'         => $this->vehicule->marque,
                'modele'         => $this->vehicule->modele,
                'agence'         => $this->vehicule->agence?->nom,
            ]),

            'fournisseur_id'      => $this->fournisseur_id,
            'fournisseur'         => $this->whenLoaded('fournisseur', fn () => $this->fournisseur ? [
                'id'   => $this->fournisseur->id,
                'nom'  => $this->fournisseur->nom,
                'type' => $this->fournisseur->type,
            ] : null),

            'chauffeur_id'        => $this->chauffeur_id,
            'chauffeur'           => $this->whenLoaded('chauffeur', fn () => $this->chauffeur ? [
                'id'  => $this->chauffeur->id,
                'nom' => $this->chauffeur->nom_complet,
            ] : null),

            'agence_id'           => $this->agence_id,
            'axe_livraison_id'    => $this->axe_livraison_id,
            'bon_commande_id'     => $this->bon_commande_id,
            'signalement_id'      => $this->signalement_id,

            // Approbation
            'approuve_par'        => $this->whenLoaded('approuvePar', fn () => $this->approuvePar?->nom_complet),
            'approuve_le'         => $this->approuve_le?->format('Y-m-d H:i'),

            'created_by'          => $this->whenLoaded('createdBy', fn () => $this->createdBy?->nom_complet),
            'notes'               => $this->notes,
            'created_at'          => $this->created_at?->toISOString(),
            'updated_at'          => $this->updated_at?->toISOString(),
        ];
    }
}
