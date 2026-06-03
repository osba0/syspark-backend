<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('document.create');
    }

    public function rules(): array
    {
        return [
            'vehicule_id'        => ['required', 'exists:vehicules,id'],
            'type_document'      => ['required', Rule::in([
                'carte_grise', 'assurance', 'visite_technique',
                'autorisation_circulation', 'carte_carburant', 'autre',
            ])],
            'intitule'           => ['nullable', 'string', 'max:150'],
            'numero'             => ['nullable', 'string', 'max:100'],
            'date_emission'      => ['nullable', 'date'],
            'date_expiration'    => ['nullable', 'date'],
            'organisme_emetteur' => ['nullable', 'string', 'max:150'],
            'notes'              => ['nullable', 'string', 'max:1000'],
            // Fichier optionnel (PDF ou image)
            'fichier'            => [
                'nullable', 'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:' . (config('parc.uploads.taille_max_mo', 10) * 1024),
            ],
        ];
    }
}
