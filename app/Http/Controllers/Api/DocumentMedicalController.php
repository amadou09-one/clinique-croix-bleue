<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentMedical;
use App\Models\RendezVous;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentMedicalController extends Controller
{
    /**
     * Téléchargement d'un document médical (stockage privé) — réservé au patient
     * propriétaire, ou au médecin qui a déjà reçu ce patient en rendez-vous
     * (même règle de confidentialité que la fiche patient).
     */
    public function telecharger(Request $request, DocumentMedical $document): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $utilisateur = $request->user();
        $autorise = false;

        if ($utilisateur->role === 'patient' && $document->patient_id === $utilisateur->id) {
            $autorise = true;
        } elseif ($utilisateur->role === 'medecin' && $utilisateur->medecin) {
            $autorise = RendezVous::where('medecin_id', $utilisateur->medecin->id)
                ->where('patient_id', $document->patient_id)
                ->exists();
        }

        if (! $autorise) {
            return response()->json([
                'data' => null,
                'message' => "Vous n'avez pas les droits nécessaires pour accéder à ce document.",
            ], 403);
        }

        // Le titre affiché ("Ordonnance du 08/08/2026") contient des "/" invalides
        // pour un en-tête Content-Disposition — on les remplace pour le nom de
        // fichier téléchargé, sans toucher au titre stocké en base.
        $nomFichier = str_replace(['/', '\\'], '-', $document->titre).'.pdf';

        return Storage::disk('local')->download($document->fichier_url, $nomFichier);
    }
}
