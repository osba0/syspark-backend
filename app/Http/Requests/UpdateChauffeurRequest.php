<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChauffeurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('chauffeur.update');
    }

    public function rules(): array
    {
        $chauffeurId = $this->route('chauffeur')?->id;

        return [
            'agence_id'              => ['sometimes', 'exists:agences,id'],
            'user_id'                => ['nullable', 'exists:users,id'],
            'nom'                    => ['sometimes', 'string', 'max:100'],
            'prenom'                 => ['sometimes', 'string', 'max:100'],
            'telephone'              => ['nullable', 'string', 'max:30'],
            'email'                  => ['nullable', 'email', 'max:150'],
            'date_naissance'         => ['nullable', 'date', 'before:today'],
            'adresse'                => ['nullable', 'string', 'max:255'],
            'cni'                    => ['nullable', 'string', 'max:50'],
            'numero_permis'          => ['nullable', 'string', 'max:50'],
            'categorie_permis'       => ['nullable', Rule::in(['A', 'B', 'C', 'D', 'E', 'BE', 'CE', 'DE'])],
            'date_delivrance_permis' => ['nullable', 'date'],
            'date_expiration_permis' => ['nullable', 'date'],
            'date_embauche'          => ['nullable', 'date'],
            'matricule_interne'      => ['nullable', 'string', 'max:50',
                Rule::unique('chauffeurs', 'matricule_interne')->ignore($chauffeurId)],
            'statut'                 => ['sometimes', Rule::in(['actif', 'suspendu', 'quitte', 'conge'])],
            'notes'                  => ['nullable', 'string', 'max:2000'],
            'photo_permis'           => [
                'nullable', 'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:' . (config('parc.uploads.taille_max_mo', 10) * 1024),
            ],
            // Création de compte à la modification (uniquement si pas encore de compte)
            'creer_compte'           => ['nullable', 'boolean'],
            'compte_password'        => ['nullable', 'string', 'min:8', 'required_if:creer_compte,true'],
            'compte_fonction'        => ['nullable', 'string', 'max:100'],
        ];
    }
}