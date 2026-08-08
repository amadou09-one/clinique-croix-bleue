<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConsultationRequest;
use App\Models\Consultation;
use App\Models\RendezVous;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MedecinConsultationController extends Controller
{
    /**
     * Crée ou met à jour la consultation liée à CE rendez-vous (une consultation
     * par RDV — updateOrCreate sur la contrainte UNIQUE rendez_vous_id). Réservé
     * au médecin propriétaire du RDV, et uniquement une fois le RDV honoré : on
     * ne saisit jamais un diagnostic sur une consultation qui n'a pas eu lieu.
     */
    public function store(StoreConsultationRequest $request, RendezVous $rendezVous): JsonResponse
    {
        $medecin = $request->user()->medecin;

        if (! $medecin || $rendezVous->medecin_id !== $medecin->id) {
            return response()->json([
                'data' => null,
                'message' => 'Vous ne pouvez saisir une consultation que pour vos propres rendez-vous.',
            ], 403);
        }

        if ($rendezVous->statut !== 'honore') {
            throw ValidationException::withMessages([
                'statut' => ['Une consultation ne peut être saisie que pour un rendez-vous honoré.'],
            ]);
        }

        $data = $request->validated();

        $consultation = Consultation::updateOrCreate(
            ['rendez_vous_id' => $rendezVous->id],
            [
                'medecin_id' => $medecin->id,
                'patient_id' => $rendezVous->patient_id,
                'diagnostic' => $data['diagnostic'] ?? null,
                'observations' => $data['observations'] ?? null,
            ]
        );

        return response()->json([
            'data' => $consultation,
            'message' => 'Consultation enregistrée.',
        ]);
    }
}
