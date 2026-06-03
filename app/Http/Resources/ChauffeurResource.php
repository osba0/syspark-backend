<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ChauffeurResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'nom'                    => $this->nom,
            'prenom'                 => $this->prenom,
            'nom_complet'            => $this->nom_complet,
            'telephone'              => $this->telephone,
            'email'                  => $this->email,
            'date_naissance'         => $this->date_naissance?->format('Y-m-d'),
            'adresse'                => $this->adresse,
            'cni'                    => $this->cni,

            // Permis
            'numero_permis'          => $this->numero_permis,
            'categorie_permis'       => $this->categorie_permis,
            'date_delivrance_permis' => $this->date_delivrance_permis?->format('Y-m-d'),
            'date_expiration_permis' => $this->date_expiration_permis?->format('Y-m-d'),
            'jours_avant_expiration_permis' => $this->jours_avant_expiration_permis,
            'statut_permis'          => $this->getStatutPermis(),

            // Emploi
            'date_embauche'          => $this->date_embauche?->format('Y-m-d'),
            'matricule_interne'      => $this->matricule_interne,
            // fonction provient du compte User associé (users.fonction)
            // Elle n'est pas dupliquée sur la table chauffeurs
            'fonction'               => $this->user?->fonction,
            'statut'                 => $this->statut,
            // Compte applicatif
            'user_id'                => $this->user_id,
            'a_un_compte'            => $this->user_id !== null,

            // Photo permis — URL absolue (même logique que DocumentResource)
            'photo'                  => $this->photo
                ? Storage::disk(config('parc.uploads.disque', 'public'))->url($this->photo)
                : null,

            // Agence
            'agence_id'              => $this->agence_id,
            'agence'                 => $this->whenLoaded('agence', fn () => [
                'id'   => $this->agence->id,
                'nom'  => $this->agence->nom,
                'code' => $this->agence->code,
            ]),

            // Affectation active
            'vehicule_actuel'        => $this->whenLoaded(
                'affectationActive',
                fn () => $this->affectationActive ? [
                    'affectation_id'  => $this->affectationActive->id,
                    'vehicule_id'     => $this->affectationActive->vehicule_id,
                    'immatriculation' => $this->affectationActive->vehicule?->immatriculation,
                    'marque_modele'   => $this->affectationActive->vehicule
                        ? $this->affectationActive->vehicule->marque . ' ' . $this->affectationActive->vehicule->modele
                        : null,
                    'axe'             => $this->affectationActive->axeLivraison?->nom,
                    'depuis'          => $this->affectationActive->date_debut?->format('Y-m-d'),
                ] : null
            ),

            'notes'                  => $this->notes,
            'created_at'             => $this->created_at?->toISOString(),
            'updated_at'             => $this->updated_at?->toISOString(),
        ];
    }

    private function getStatutPermis(): string
    {
        $jours = $this->jours_avant_expiration_permis;
        if ($jours === null) return 'inconnu';
        if ($jours < 0)      return 'expire';
        if ($jours <= 30)    return 'critique';
        if ($jours <= 90)    return 'warning';
        return 'valide';
    }
}