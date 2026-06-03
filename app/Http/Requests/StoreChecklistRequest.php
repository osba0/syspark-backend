<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('checklist.create');
    }

    public function rules(): array
    {
        return [
            'vehicule_id'    => ['required', 'exists:vehicules,id'],
            'chauffeur_id'   => ['nullable', 'exists:chauffeurs,id'],
            'agence_id'      => ['required', 'exists:agences,id'],
            'type_checklist' => ['required', Rule::in([
                'hebdomadaire_vehicule',
                'hebdomadaire_scooter',
                'visite_technique',
                'passation',
                'attribution',
            ])],
            'date'           => ['required', 'date', 'before_or_equal:today'],
            'kilometrage'    => ['nullable', 'integer', 'min:0'],
            'data_json'      => ['required', 'array'],
            'data_json.*'    => ['array'],
            'observations'   => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'data_json.required' => 'Les données de la checklist sont obligatoires.',
            'data_json.array'    => 'Les données doivent être un objet JSON valide.',
        ];
    }
}