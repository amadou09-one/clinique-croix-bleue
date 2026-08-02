<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferencesRequest extends FormRequest
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
            'notif_email_rdv' => ['required', 'boolean'],
            'notif_email_rappel' => ['required', 'boolean'],
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
            'notif_email_rdv.required' => 'La préférence de notification pour les rendez-vous est obligatoire.',
            'notif_email_rdv.boolean' => 'La préférence de notification pour les rendez-vous doit être vraie ou fausse.',
            'notif_email_rappel.required' => 'La préférence de notification pour les rappels est obligatoire.',
            'notif_email_rappel.boolean' => 'La préférence de notification pour les rappels doit être vraie ou fausse.',
        ];
    }
}
