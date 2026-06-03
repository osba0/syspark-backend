<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('maintenance.update');
    }

    public function rules(): array
    {
        return [
            'fournisseur_id'     => ['nullable', 'exists:fournisseurs,id'],
            'axe_livraison_id'   => ['nullable', 'exists:axes_livraison,id'],
            'date_travaux'       => ['sometimes', 'date'],
            'date_entree'        => ['nullable', 'date'],
            'date_sortie'        => ['nullable', 'date'],
            'kilometrage'        => ['sometimes', 'integer', 'min:0'],
            'type_operation'     => ['sometimes', Rule::in([
                'entretien', 'reparation', 'pneu', 'equipement',
                'contravention', 'carrosserie', 'visite_technique',
            ])],
            'categorie_travaux'  => ['nullable', 'string', 'max:100'],
            'titre'              => ['sometimes', 'string', 'max:200'],
            'description_travaux'=> ['sometimes', 'string', 'min:5'],
            'fournitures_mo'     => ['nullable', 'string'],
            'montant_ht'         => ['nullable', 'numeric', 'min:0'],
            'tva'                => ['nullable', 'numeric', 'min:0', 'max:100'],
            'montant_ttc'        => ['nullable', 'numeric', 'min:0'],
            'numero_facture'     => ['nullable', 'string', 'max:100'],
            'date_facture'       => ['nullable', 'date'],
            'statut'             => ['sometimes', Rule::in(['planifie', 'en_cours', 'annule'])],
            'notes'              => ['nullable', 'string', 'max:2000'],
        ];
    }
}
