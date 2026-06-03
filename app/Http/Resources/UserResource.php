<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'prenom'       => $this->prenom,
            'nom_complet'  => $this->nom_complet,
            'email'        => $this->email,
            'telephone'    => $this->telephone,
            'fonction'     => $this->fonction,
            'est_actif'    => $this->est_actif,
            'last_login_at' => $this->last_login_at?->toISOString(),

            // Agence
            'agence_id'    => $this->agence_id,
            'agence'       => $this->whenLoaded('agence', fn() => [
                'id'   => $this->agence->id,
                'nom'  => $this->agence->nom,
                'code' => $this->agence->code,
            ]),

            // Fiche chauffeur liée (si l'utilisateur est un chauffeur)
            'chauffeur'    => $this->whenLoaded('chauffeur', function () {
                /** @var \App\Models\Chauffeur|null $c */
                $c = $this->chauffeur->first();
                if (!$c) return null;

                // affectationActive est chargée sur le modèle via eager loading
                // On doit la récupérer depuis les relations du modèle extrait
                $affectation = $c->relationLoaded('affectationActive')
                    ? $c->affectationActive
                    : $c->affectationActive()->with('vehicule')->first();

                $vehicule = $affectation?->relationLoaded('vehicule')
                    ? $affectation->vehicule
                    : $affectation?->vehicule;

                return [
                    'id'                    => $c->id,
                    'matricule_interne'     => $c->matricule_interne,
                    'statut'                => $c->statut,
                    'statut_permis'         => $c->statut_permis,
                    'date_expiration_permis'=> $c->date_expiration_permis?->format('Y-m-d'),
                    'jours_avant_expiration_permis' => $c->jours_avant_expiration_permis,
                    'vehicule_actuel'       => $vehicule
                        ? [
                            'vehicule_id'    => $vehicule->id,
                            'immatriculation'=> $vehicule->immatriculation,
                            'marque_modele'  => trim($vehicule->marque . ' ' . $vehicule->modele),
                            'depuis'         => $affectation->date_debut?->format('Y-m-d'),
                        ]
                        : null,
                ];
            }),

            // RBAC — rôles et permissions (chargés par spatie/permission)
            'roles'        => $this->getRoleNames(),
            'role'         => $this->getRoleNames()->first(), // Rôle principal
            'permissions'  => $this->getAllPermissions()->pluck('name'),

            'created_at'   => $this->created_at?->toISOString(),
        ];
    }
}