<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDisponibiliteRequest extends FormRequest
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
            'jour_semaine' => ['required', 'integer', 'between:1,7'],
            'plages' => ['present', 'array'],
            'plages.*.heure_debut' => ['required_with:plages.*.heure_fin', 'date_format:H:i'],
            'plages.*.heure_fin' => ['required_with:plages.*.heure_debut', 'date_format:H:i', 'after:plages.*.heure_debut'],
            'duree_creneau_min' => ['required', 'integer', 'min:5', 'max:180'],
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
            'jour_semaine.required' => 'Le jour de la semaine est obligatoire.',
            'jour_semaine.between' => 'Le jour de la semaine doit être compris entre 1 (lundi) et 7 (dimanche).',
            'plages.present' => 'La liste des plages horaires est obligatoire (peut être vide pour un jour fermé).',
            'plages.*.heure_debut.date_format' => "L'heure de début doit être au format HH:MM.",
            'plages.*.heure_fin.date_format' => "L'heure de fin doit être au format HH:MM.",
            'plages.*.heure_fin.after' => "L'heure de fin doit être après l'heure de début.",
            'duree_creneau_min.required' => 'La durée des créneaux est obligatoire.',
            'duree_creneau_min.min' => 'La durée des créneaux doit être d\'au moins 5 minutes.',
            'duree_creneau_min.max' => 'La durée des créneaux ne peut pas dépasser 180 minutes.',
        ];
    }
}
