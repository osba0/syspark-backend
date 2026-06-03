<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloturerAffectationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('affectation.cloturer');
    }

    public function rules(): array
    {
        $affectation = $this->route('affectation');

        return [
            'kilometrage_fin' => [
                'required', 'integer',
                'min:' . ($affectation?->kilometrage_debut ?? 0),
            ],
            'date_fin' => ['nullable', 'date', 'after_or_equal:' . ($affectation?->date_debut ?? 'today')],
            'notes'    => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'kilometrage_fin.min' => 'Le kilométrage final ne peut pas être inférieur au kilométrage de départ.',
        ];
    }
}
