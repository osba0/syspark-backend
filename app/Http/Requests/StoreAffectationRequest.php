<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAffectationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('affectation.create');
    }

    public function rules(): array
    {
        return [
            'vehicule_id'      => ['required', 'exists:vehicules,id'],
            'agence_id'        => ['required', 'exists:agences,id'],
            'axe_livraison_id' => ['nullable', 'exists:axes_livraison,id'],
            'chauffeur_id'     => ['nullable', 'exists:chauffeurs,id',
                'required_without:attributaire_id'],
            'attributaire_id'  => ['nullable', 'exists:users,id',
                'required_without:chauffeur_id'],
            'date_debut'       => ['required', 'date'],
            'kilometrage_debut'=> ['required', 'integer', 'min:0'],
            'type_affectation' => ['required', Rule::in(['livraison', 'administratif', 'mission'])],
            'notes'            => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'chauffeur_id.required_without'   => 'Le chauffeur ou l\'attributaire est obligatoire.',
            'attributaire_id.required_without' => 'Le chauffeur ou l\'attributaire est obligatoire.',
        ];
    }
}
