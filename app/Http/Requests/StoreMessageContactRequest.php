<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageContactRequest extends FormRequest
{
    /**
     * Route publique (site vitrine) : tout visiteur peut soumettre le formulaire.
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
            'nom' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:180'],
            'telephone' => ['nullable', 'regex:/^\+221[0-9]{9}$/'],
            'sujet' => ['nullable', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:2000'],
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
            'nom.required' => 'Le nom est obligatoire.',
            'email.required' => "L'adresse e-mail est obligatoire.",
            'email.email' => "L'adresse e-mail n'est pas valide.",
            'telephone.regex' => 'Le numéro de téléphone doit être au format +221 suivi de 9 chiffres.',
            'message.required' => 'Le message est obligatoire.',
            'message.max' => 'Le message ne doit pas dépasser 2000 caractères.',
        ];
    }
}
