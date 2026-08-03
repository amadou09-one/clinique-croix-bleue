<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class DefinirMotDePasseController extends Controller
{
    /**
     * Endpoint public (pas d'auth) : consomme le token envoyé par e-mail lors
     * de la création d'un compte patient au comptoir (voir SecretairePatientController)
     * pour définir le mot de passe définitif. Réutilise le broker de mots de passe
     * natif de Laravel (table password_reset_tokens déjà en place).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', PasswordRule::min(8), 'confirmed'],
        ], [
            'email.required' => "L'adresse e-mail est obligatoire.",
            'token.required' => 'Le lien utilisé est invalide.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
        ]);

        $statut = Password::broker()->reset(
            $request->only('email', 'token', 'password', 'password_confirmation'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        if ($statut !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [$this->messageErreur($statut)],
            ]);
        }

        return response()->json([
            'data' => null,
            'message' => 'Mot de passe défini avec succès. Vous pouvez maintenant vous connecter.',
        ]);
    }

    private function messageErreur(string $statut): string
    {
        return match ($statut) {
            Password::INVALID_USER => "Aucun compte ne correspond à cette adresse e-mail.",
            Password::INVALID_TOKEN => "Ce lien n'est plus valide ou a déjà été utilisé.",
            default => "Impossible de définir le mot de passe. Merci de redemander un lien.",
        };
    }
}
