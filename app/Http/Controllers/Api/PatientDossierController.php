<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentMedical;
use App\Models\DossierMedical;
use App\Models\RendezVous;
use App\Models\Traitement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientDossierController extends Controller
{
    /**
     * Dossier médical du patient authentifié : constantes de santé (allergies
     * toujours renvoyées, règle métier n°7), historique des consultations
     * honorées avec diagnostic/observations, traitements, documents (ordonnances).
     */
    public function show(Request $request): JsonResponse
    {
        $patient = $request->user();

        $dossier = DossierMedical::where('patient_id', $patient->id)->first();

        $historique = RendezVous::with(['medecin.user:id,prenom,nom', 'medecin.specialite', 'consultation'])
            ->where('patient_id', $patient->id)
            ->where('statut', 'honore')
            ->orderByDesc('date_heure')
            ->get(['id', 'medecin_id', 'date_heure', 'statut', 'motif']);

        $traitements = Traitement::with('medecin.user:id,prenom,nom')
            ->where('patient_id', $patient->id)
            ->orderByDesc('date_debut')
            ->get();

        $documents = DocumentMedical::where('patient_id', $patient->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => [
                'dossier_medical' => [
                    'groupe_sanguin' => $dossier?->groupe_sanguin,
                    'poids_kg' => $dossier?->poids_kg,
                    'taille_cm' => $dossier?->taille_cm,
                    'tension' => $dossier?->tension,
                    'allergies' => $dossier?->allergies,
                ],
                'historique_consultations' => $historique,
                'traitements' => $traitements,
                'documents' => $documents,
            ],
            'message' => 'Dossier médical récupéré.',
        ]);
    }
}
