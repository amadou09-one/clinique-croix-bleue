<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdatePreferencesRequest;
use App\Http\Requests\UpdateProfilRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfilController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->avecMedecinSiBesoin($request->user()),
            'message' => 'Profil récupéré.',
        ]);
    }

    public function update(UpdateProfilRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return response()->json([
            'data' => $this->avecMedecinSiBesoin($user->fresh()),
            'message' => 'Profil mis à jour avec succès.',
        ]);
    }

    /**
     * Le frontend médecin affiche spécialité/titre/bio sur sa page Profil — mêmes
     * données que celles déjà chargées par AuthController::me().
     */
    private function avecMedecinSiBesoin(User $user): User
    {
        if ($user->role === 'medecin') {
            $user->loadMissing('medecin.specialite');
        }

        return $user;
    }

    /**
     * Le mot de passe actuel est vérifié manuellement (comme dans AuthController::login)
     * plutôt que via la règle "current_password" : cette dernière dépend du guard "web"
     * par défaut, alors que l'API est authentifiée en stateless via le guard "sanctum".
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (! Hash::check($data['mot_de_passe_actuel'], $user->password)) {
            throw ValidationException::withMessages([
                'mot_de_passe_actuel' => ['Le mot de passe actuel est incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        return response()->json([
            'data' => null,
            'message' => 'Mot de passe modifié avec succès.',
        ]);
    }

    public function showPreferences(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'notif_email_rdv' => $user->notif_email_rdv,
                'notif_email_rappel' => $user->notif_email_rappel,
            ],
            'message' => 'Préférences récupérées.',
        ]);
    }

    public function updatePreferences(UpdatePreferencesRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return response()->json([
            'data' => $user->fresh(),
            'message' => 'Préférences mises à jour.',
        ]);
    }
}
