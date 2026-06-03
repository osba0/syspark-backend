<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChauffeurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('chauffeur.create');
    }

    public function rules(): array
    {
        return [
            'agence_id'              => ['required', 'exists:agences,id'],
            'user_id'                => ['nullable', 'exists:users,id'],
            // Création de compte applicatif — optionnelle
            'creer_compte'           => ['nullable', 'boolean'],
            'compte_password'        => ['nullable', 'string', 'min:8', 'required_if:creer_compte,true'],
            'nom'                    => ['required', 'string', 'max:100'],
            'prenom'                 => ['required', 'string', 'max:100'],
            'telephone'              => ['nullable', 'string', 'max:30'],
            'email'                  => ['nullable', 'email', 'max:150'],
            'date_naissance'         => ['nullable', 'date', 'before:today'],
            'adresse'                => ['nullable', 'string', 'max:255'],
            'cni'                    => ['nullable', 'string', 'max:50'],
            'numero_permis'          => ['nullable', 'string', 'max:50'],
            'categorie_permis'       => ['nullable', Rule::in(['A', 'B', 'C', 'D', 'E', 'BE', 'CE', 'DE'])],
            'date_delivrance_permis' => ['nullable', 'date', 'before_or_equal:today'],
            'date_expiration_permis' => ['nullable', 'date', 'after:date_delivrance_permis'],
            'date_embauche'          => ['nullable', 'date', 'before_or_equal:today'],
            'matricule_interne'      => ['nullable', 'string', 'max:50', 'unique:chauffeurs,matricule_interne'],
            'statut'                 => ['nullable', Rule::in(['actif', 'suspendu', 'quitte', 'conge'])],
            'notes'                  => ['nullable', 'string', 'max:2000'],
            'photo_permis'           => [
                'nullable', 'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:' . (config('parc.uploads.taille_max_mo', 10) * 1024),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'agence_id.required'          => 'L\'agence est obligatoire.',
            'nom.required'                => 'Le nom est obligatoire.',
            'prenom.required'             => 'Le prénom est obligatoire.',
            'matricule_interne.unique'    => 'Ce matricule est déjà utilisé.',
            'date_expiration_permis.after'=> 'La date d\'expiration doit être après la date de délivrance.',
        ];
    }
}