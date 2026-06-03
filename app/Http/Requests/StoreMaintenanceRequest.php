<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('maintenance.create');
    }

    public function rules(): array
    {
        return [
            'vehicule_id'        => ['required', 'exists:vehicules,id'],
            'agence_id'          => ['required', 'exists:agences,id'],
            'fournisseur_id'     => ['nullable', 'exists:fournisseurs,id'],
            'axe_livraison_id'   => ['nullable', 'exists:axes_livraison,id'],
            'chauffeur_id'       => ['nullable', 'exists:chauffeurs,id'],
            'date_travaux'       => ['required', 'date'],
            'date_entree'        => ['nullable', 'date'],
            'kilometrage'        => ['required', 'integer', 'min:0'],
            'type_operation'     => ['required', Rule::in([
                'entretien', 'reparation', 'pneu', 'equipement',
                'contravention', 'carrosserie', 'visite_technique',
            ])],
            'categorie_travaux'  => ['nullable', 'string', 'max:100'],
            'titre'              => ['required', 'string', 'max:200'],
            'description_travaux'=> ['required', 'string', 'min:5'],
            'fournitures_mo'     => ['nullable', 'string'],
            'montant_ht'         => ['nullable', 'numeric', 'min:0'],
            'tva'                => ['nullable', 'numeric', 'min:0', 'max:100'],
            'montant_ttc'        => ['nullable', 'numeric', 'min:0'],
            'numero_facture'     => ['nullable', 'string', 'max:100'],
            'date_facture'       => ['nullable', 'date'],
            'statut'             => ['nullable', Rule::in(['planifie', 'en_cours', 'termine', 'annule'])],
            'bon_commande_id'    => ['nullable', 'exists:bons_commande,id'],
            'notes'              => ['nullable', 'string', 'max:2000'],
        ];
    }
}
