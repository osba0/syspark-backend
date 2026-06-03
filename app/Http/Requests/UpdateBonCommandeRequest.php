<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBonCommandeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bonCommande.update');
    }

    public function rules(): array
    {
        return [
            'fournisseur_id'        => ['nullable', 'exists:fournisseurs,id'],
            'vehicule_id'           => ['nullable', 'exists:vehicules,id'],
            'date_commande'         => ['sometimes', 'date'],
            'date_livraison_prevue' => ['nullable', 'date'],
            'date_livraison_reelle' => ['nullable', 'date'],
            'tva'                   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'observations'          => ['nullable', 'string', 'max:2000'],
            'lignes'                => ['sometimes', 'array', 'min:1'],
            'lignes.*.description'  => ['required_with:lignes', 'string', 'max:255'],
            'lignes.*.quantite'     => ['required_with:lignes', 'numeric', 'min:0.01'],
            'lignes.*.prix_unitaire'=> ['required_with:lignes', 'numeric', 'min:0'],
            'lignes.*.unite'        => ['nullable', 'string', 'max:30'],
        ];
    }
}
