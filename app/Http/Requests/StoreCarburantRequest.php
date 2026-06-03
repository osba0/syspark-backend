<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCarburantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('carburant.create');
    }

    public function rules(): array
    {
        return [
            'vehicule_id'      => ['required', 'exists:vehicules,id'],
            'chauffeur_id'     => ['nullable', 'exists:chauffeurs,id'],
            'agence_id'        => ['required', 'exists:agences,id'],
            'axe_livraison_id' => ['nullable', 'exists:axes_livraison,id'],
            'date'             => ['required', 'date', 'before_or_equal:today'],
            'litres'           => ['required', 'numeric', 'min:0.1', 'max:500'],
            'montant'          => ['required', 'numeric', 'min:0'],
            'prix_unitaire'    => ['nullable', 'numeric', 'min:0'],
            'type_carburant'   => ['nullable', 'string', 'max:30'],
            'kilometrage'      => ['nullable', 'integer', 'min:0'],
            'numero_transaction'=> ['nullable', 'string', 'max:100'],
            'station'          => ['nullable', 'string', 'max:100'],
            'est_complet'      => ['nullable', 'boolean'],
            'notes'            => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'litres.required'  => 'Le nombre de litres est obligatoire.',
            'montant.required' => 'Le montant est obligatoire.',
        ];
    }
}
