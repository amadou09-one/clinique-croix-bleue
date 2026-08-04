<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUtilisateurAdminRequest extends FormRequest
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
            'prenom' => ['required', 'string', 'max:100'],
            'nom' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:180', Rule::unique('users', 'email')->ignore($this->route('utilisateur'))],
            'telephone' => ['required', 'regex:/^\+221[0-9]{9}$/'],
            'role' => ['required', 'in:patient,medecin,secretaire,admin'],
            'est_actif' => ['required', 'boolean'],
            'date_naissance' => ['nullable', 'date', 'before:today'],
            'sexe' => ['nullable', 'in:F,M'],
            'specialite_id' => ['required_if:role,medecin', 'nullable', 'integer', 'exists:specialites,id'],
            'titre' => ['nullable', 'string', 'max:150'],
            'annees_experience' => ['required_if:role,medecin', 'nullable', 'integer', 'min:0'],
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
            'prenom.required' => 'Le prénom est obligatoire.',
            'nom.required' => 'Le nom est obligatoire.',
            'email.required' => "L'adresse e-mail est obligatoire.",
            'email.email' => "L'adresse e-mail n'est pas valide.",
            'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
            'telephone.required' => 'Le numéro de téléphone est obligatoire.',
            'telephone.regex' => 'Le numéro de téléphone doit être au format +221 suivi de 9 chiffres.',
            'role.required' => 'Le rôle est obligatoire.',
            'role.in' => 'Le rôle doit être patient, medecin, secretaire ou admin.',
            'est_actif.required' => 'Le statut du compte est obligatoire.',
            'date_naissance.date' => 'La date de naissance n\'est pas valide.',
            'date_naissance.before' => 'La date de naissance doit être antérieure à aujourd\'hui.',
            'sexe.in' => 'Le sexe doit être F ou M.',
            'specialite_id.required_if' => 'La spécialité est obligatoire pour un médecin.',
            'specialite_id.exists' => "Cette spécialité n'existe pas.",
            'annees_experience.required_if' => "Les années d'expérience sont obligatoires pour un médecin.",
        ];
    }
}
