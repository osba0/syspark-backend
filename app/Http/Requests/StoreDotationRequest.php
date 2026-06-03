<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('carburant.gererDotations');
    }

    public function rules(): array
    {
        return [
            'vehicule_id'  => ['required', 'exists:vehicules,id'],
            'chauffeur_id' => ['nullable', 'exists:chauffeurs,id'],
            'agence_id'    => ['required', 'exists:agences,id'],
            'mois'         => ['required', 'integer', 'min:1', 'max:12'],
            'annee'        => ['required', 'integer', 'min:2020', 'max:' . (date('Y') + 1)],
            'montant_dote' => ['required', 'numeric', 'min:0'],
            'litres_dotes' => ['nullable', 'numeric', 'min:0'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ];
    }
}
