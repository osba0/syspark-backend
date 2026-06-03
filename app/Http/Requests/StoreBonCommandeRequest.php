<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBonCommandeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bonCommande.create');
    }

    public function rules(): array
    {
        return [
            'agence_id'             => ['required', 'exists:agences,id'],
            'fournisseur_id'        => ['nullable', 'exists:fournisseurs,id'],
            'vehicule_id'           => ['nullable', 'exists:vehicules,id'],
            'date_commande'         => ['required', 'date'],
            'date_livraison_prevue' => ['nullable', 'date', 'after_or_equal:date_commande'],
            'tva'                   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'observations'          => ['nullable', 'string', 'max:2000'],
            'lignes'                => ['required', 'array', 'min:1'],
            'lignes.*.description'  => ['required', 'string', 'max:255'],
            'lignes.*.quantite'     => ['required', 'numeric', 'min:0.01'],
            'lignes.*.prix_unitaire'=> ['required', 'numeric', 'min:0'],
            'lignes.*.unite'        => ['nullable', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'lignes.required'              => 'Le bon de commande doit avoir au moins une ligne.',
            'lignes.*.description.required'=> 'La description de chaque ligne est obligatoire.',
            'lignes.*.quantite.required'   => 'La quantité est obligatoire.',
            'lignes.*.prix_unitaire.required'=> 'Le prix unitaire est obligatoire.',
        ];
    }
}
