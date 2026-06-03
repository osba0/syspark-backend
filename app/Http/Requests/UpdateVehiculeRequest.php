<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehiculeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('vehicule.update');
    }

    public function rules(): array
    {
        $vehiculeId = $this->route('vehicule')?->id;

        return [
            'agence_id'              => ['sometimes', 'exists:agences,id'],
            'immatriculation'        => ['sometimes', 'string', 'max:20',
                Rule::unique('vehicules', 'immatriculation')->ignore($vehiculeId),
                'regex:/^[A-Z0-9 \-]+$/i'],
            'marque'                 => ['sometimes', 'string', 'max:100'],
            'modele'                 => ['sometimes', 'string', 'max:100'],
            'type_vehicule'          => ['sometimes', Rule::in(['livraison', 'administratif', 'scooter'])],
            'categorie'              => ['nullable', 'string', 'max:50'],
            'annee_fabrication'      => ['nullable', 'integer', 'min:1990', 'max:' . (date('Y') + 1)],
            'date_mise_circulation'  => ['nullable', 'date'],
            'couleur'                => ['nullable', 'string', 'max:50'],
            'numero_chassis'         => ['nullable', 'string', 'max:100',
                Rule::unique('vehicules', 'numero_chassis')->ignore($vehiculeId)],
            'energie'                => ['nullable', Rule::in(['diesel', 'essence', 'hybride', 'electrique'])],
            'statut'                 => ['sometimes', Rule::in([
                'actif', 'en_panne', 'en_maintenance', 'en_mission', 'cede', 'hors_service'])],
            'intervalle_entretien_km' => ['nullable', 'integer', 'min:1000'],
            'prochain_entretien_km'  => ['nullable', 'integer', 'min:0'],
            'prochain_entretien_date' => ['nullable', 'date'],
            'date_derniere_visite_tech' => ['nullable', 'date'],
            'date_prochaine_visite_tech' => ['nullable', 'date'],
            'date_expiration_assurance'  => ['nullable', 'date'],
            'numero_assurance'       => ['nullable', 'string', 'max:100'],
            'compagnie_assurance'    => ['nullable', 'string', 'max:100'],
            'numero_carte_carburant' => ['nullable', 'string', 'max:50'],
            'type_carburant'         => ['nullable', Rule::in(['diesel', 'essence', 'sans_plomb'])],
            'notes'                  => ['nullable', 'string', 'max:2000'],
        ];
    }
}
