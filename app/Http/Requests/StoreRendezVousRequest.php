<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRendezVousRequest extends FormRequest
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
        // patient_id n'est exploité que si l'appelant est une secrétaire (voir
        // RendezVousController::store) — pour un patient, il est required-si-présent
        // uniquement pour la validation de forme : sa valeur réelle est de toute façon
        // ignorée et remplacée par $request->user()->id côté contrôleur.
        $estSecretaire = $this->user()?->role === 'secretaire';

        return [
            'medecin_id' => ['required', 'integer', 'exists:medecins,id'],
            'date_heure' => ['required', 'date'],
            'motif' => ['nullable', 'string', 'max:255'],
            'patient_id' => [$estSecretaire ? 'required' : 'nullable', 'integer'],
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
            'medecin_id.required' => 'Le médecin est obligatoire.',
            'medecin_id.exists' => "Ce médecin n'existe pas.",
            'date_heure.required' => 'La date et l\'heure du rendez-vous sont obligatoires.',
            'date_heure.date' => "La date et l'heure du rendez-vous ne sont pas valides.",
            'motif.max' => 'Le motif ne doit pas dépasser 255 caractères.',
            'patient_id.required' => 'Le patient est obligatoire.',
        ];
    }
}
