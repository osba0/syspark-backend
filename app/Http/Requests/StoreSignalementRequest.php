<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSignalementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('signalement.create');
    }

    public function rules(): array
    {
        return [
            'vehicule_id'       => ['required', 'exists:vehicules,id'],
            'chauffeur_id'      => ['nullable', 'exists:chauffeurs,id'],
            'agence_id'         => ['required', 'exists:agences,id'],
            'origine'           => ['required', Rule::in([
                'chauffeur', 'checklist', 'responsable', 'visite_technique',
            ])],
            'date_signalement'  => ['required', 'date', 'before_or_equal:today'],
            'kilometrage'       => ['nullable', 'integer', 'min:0'],
            'type_defaut'       => ['required', Rule::in([
                'panne_moteur', 'probleme_freins', 'probleme_pneu',
                'probleme_electrique', 'probleme_carrosserie',
                'probleme_eclairage', 'fuite', 'surchauffe',
                'bruit_anormal', 'probleme_document', 'autre',
            ])],
            'gravite'           => ['required', Rule::in(['faible', 'moyenne', 'haute', 'critique'])],
            'titre'             => ['required', 'string', 'max:200'],
            'description'       => ['required', 'string', 'min:10'],
            'etat_elements'     => ['nullable', 'array'],
            // Photos envoyées séparément via /photos endpoint
        ];
    }

    public function messages(): array
    {
        return [
            'description.min' => 'La description doit contenir au moins 10 caractères.',
            'titre.required'  => 'Le titre du signalement est obligatoire.',
        ];
    }
}
