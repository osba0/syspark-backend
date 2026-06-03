<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFournisseurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fournisseur.create');
    }

    public function rules(): array
    {
        return [
            'nom'        => ['required', 'string', 'max:150'],
            'type'       => ['nullable', 'string', 'max:50'],
            'telephone'  => ['nullable', 'string', 'max:30'],
            'email'      => ['nullable', 'email', 'max:150'],
            'adresse'    => ['nullable', 'string', 'max:255'],
            'ville'      => ['nullable', 'string', 'max:100'],
            'specialite' => ['nullable', 'string', 'max:150'],
            'ninea'      => ['nullable', 'string', 'max:50'],
            'notes'      => ['nullable', 'string', 'max:1000'],
        ];
    }
}
