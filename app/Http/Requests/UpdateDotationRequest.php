<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('carburant.gererDotations');
    }

    public function rules(): array
    {
        return [
            'montant_dote' => ['sometimes', 'numeric', 'min:0'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ];
    }
}
