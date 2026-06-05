<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehiculeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'immatriculation'       => $this->immatriculation,
            'marque'                => $this->marque,
            'modele'                => $this->modele,
            'type_vehicule'         => $this->type_vehicule,
            'categorie'             => $this->categorie,
            'annee_fabrication'     => $this->annee_fabrication,
            'date_mise_circulation' => $this->date_mise_circulation?->format('Y-m-d'),
            'couleur'               => $this->couleur,
            'numero_chassis'        => $this->numero_chassis,
            'energie'               => $this->energie,

            // Statut
            'statut'                => $this->statut,
            'photo_principale_url'  => $this->photo_principale_url,

            // Kilométrage
            'kilometrage_actuel'        => $this->kilometrage_actuel,
            'prochain_entretien_km'     => $this->prochain_entretien_km,
            'prochain_entretien_date'   => $this->prochain_entretien_date?->format('Y-m-d'),
            'intervalle_entretien_km'   => $this->intervalle_entretien_km,

            // Documents
            'date_derniere_visite_tech'  => $this->date_derniere_visite_tech?->format('Y-m-d'),
            'date_prochaine_visite_tech' => $this->date_prochaine_visite_tech?->format('Y-m-d'),
            'date_expiration_assurance'  => $this->date_expiration_assurance?->format('Y-m-d'),
            'numero_assurance'           => $this->numero_assurance,
            'compagnie_assurance'        => $this->compagnie_assurance,

            // Statuts calculés (utiles pour les badges couleur UI)
            'statut_vt'                 => $this->statut_vt,
            'statut_assurance'          => $this->statut_assurance,
            'jours_avant_vt'            => $this->jours_avant_vt,
            'jours_avant_assurance'     => $this->jours_avant_assurance,

            // Carburant
            'numero_carte_carburant' => $this->numero_carte_carburant,
            'type_carburant'         => $this->type_carburant,

            // Agence
            'agence_id'              => $this->agence_id,
            'agence'                 => $this->whenLoaded('agence', fn () => [
                'id'   => $this->agence->id,
                'nom'  => $this->agence->nom,
                'code' => $this->agence->code,
            ]),

            // Affectation active (pour l'affichage du chauffeur actuel)
            'affectation_active'     => $this->whenLoaded(
                'affectationActive',
                fn () => $this->affectationActive ? [
                    'id'           => $this->affectationActive->id,
                    'type'         => $this->affectationActive->type_affectation,
                    'date_debut'   => $this->affectationActive->date_debut?->format('Y-m-d'),
                    'chauffeur'    => $this->affectationActive->chauffeur ? [
                        'id'        => $this->affectationActive->chauffeur->id,
                        'nom'       => $this->affectationActive->chauffeur->nom_complet,
                        'telephone' => $this->affectationActive->chauffeur->telephone,
                    ] : null,
                    'axe'          => $this->affectationActive->axeLivraison?->nom,
                ] : null
            ),

            'notes'       => $this->notes,
            'created_at'  => $this->created_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),
        ];
    }
}