<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehiculeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('vehicule.create');
    }

    public function rules(): array
    {
        return [
            'agence_id'              => ['required', 'exists:agences,id'],
            'immatriculation'        => ['required', 'string', 'max:20', 'unique:vehicules,immatriculation',
                'regex:/^[A-Z0-9 \-]+$/i'],
            'marque'                 => ['required', 'string', 'max:100'],
            'modele'                 => ['required', 'string', 'max:100'],
            'type_vehicule'          => ['required', Rule::in(['livraison', 'administratif', 'scooter'])],
            'categorie'              => ['nullable', 'string', 'max:50'],
            'annee_fabrication'      => ['nullable', 'integer', 'min:1990', 'max:' . (date('Y') + 1)],
            'date_mise_circulation'  => ['nullable', 'date', 'before_or_equal:today'],
            'couleur'                => ['nullable', 'string', 'max:50'],
            'numero_chassis'         => ['nullable', 'string', 'max:100', 'unique:vehicules,numero_chassis'],
            'numero_moteur'          => ['nullable', 'string', 'max:100'],
            'energie'                => ['nullable', Rule::in(['diesel', 'essence', 'hybride', 'electrique'])],
            'kilometrage_actuel'     => ['nullable', 'integer', 'min:0'],
            'intervalle_entretien_km' => ['nullable', 'integer', 'min:1000'],
            'date_derniere_visite_tech' => ['nullable', 'date'],
            'date_prochaine_visite_tech' => ['nullable', 'date', 'after:today'],
            'date_expiration_assurance'  => ['nullable', 'date'],
            'numero_assurance'       => ['nullable', 'string', 'max:100'],
            'compagnie_assurance'    => ['nullable', 'string', 'max:100'],
            'numero_carte_carburant' => ['nullable', 'string', 'max:50'],
            'type_carburant'         => ['nullable', Rule::in(['diesel', 'essence', 'sans_plomb'])],
            'notes'                  => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'immatriculation.unique'  => 'Cette immatriculation existe déjà.',
            'immatriculation.regex'   => 'L\'immatriculation contient des caractères invalides.',
            'numero_chassis.unique'   => 'Ce numéro de châssis existe déjà.',
            'agence_id.exists'        => 'L\'agence sélectionnée n\'existe pas.',
        ];
    }
}
