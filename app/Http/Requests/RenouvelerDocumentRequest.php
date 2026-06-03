<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RenouvelerDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('document.renouveler');
    }

    public function rules(): array
    {
        return [
            'numero'             => ['nullable', 'string', 'max:100'],
            'date_emission'      => ['required', 'date'],
            'date_expiration'    => ['required', 'date', 'after:date_emission'],
            'organisme_emetteur' => ['nullable', 'string', 'max:150'],
            'notes'              => ['nullable', 'string', 'max:1000'],
            'fichier'            => [
                'nullable', 'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:' . (config('parc.uploads.taille_max_mo', 10) * 1024),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'date_expiration.after' => 'La date d\'expiration doit être postérieure à la date d\'émission.',
        ];
    }
}
