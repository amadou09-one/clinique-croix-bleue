<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMedecinProfilRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedecinProfilController extends Controller
{
    /**
     * Seule la biographie est éditable par le médecin lui-même : la spécialité et le
     * titre sont attribués administrativement (règle métier n°6 — seul un admin gère
     * les comptes medecin) et restent en lecture seule ici.
     */
    public function update(UpdateMedecinProfilRequest $request): JsonResponse
    {
        $medecin = $request->user()->medecin;
        $medecin->update(['bio' => $request->validated()['bio'] ?? null]);

        return response()->json([
            'data' => $medecin->fresh(['specialite']),
            'message' => 'Profil professionnel mis à jour.',
        ]);
    }
}
