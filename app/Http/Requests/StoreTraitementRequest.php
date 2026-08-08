<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTraitementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'medicament' => ['required', 'string', 'max:150'],
            'posologie' => ['required', 'string', 'max:200'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'rendez_vous_id' => ['nullable', 'integer', 'exists:rendez_vous,id'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'medicament.required' => 'Le médicament est obligatoire.',
            'posologie.required' => 'La posologie est obligatoire.',
            'date_debut.required' => 'La date de début est obligatoire.',
            'date_debut.date' => 'La date de début est invalide.',
            'date_fin.date' => 'La date de fin est invalide.',
            'date_fin.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
            'rendez_vous_id.exists' => "Ce rendez-vous n'existe pas.",
        ];
    }
}
