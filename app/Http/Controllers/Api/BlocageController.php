<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBlocageRequest;
use App\Mail\RdvBlocageMail;
use App\Models\Blocage;
use App\Models\Medecin;
use App\Models\Notification;
use App\Models\RendezVous;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class BlocageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $medecin = $this->medecinConnecte($request);

        return response()->json([
            'data' => Blocage::where('medecin_id', $medecin->id)->orderBy('date')->get(),
            'message' => 'Dates bloquées récupérées.',
        ]);
    }

    /**
     * Bloque une date : les RDV non annulés de ce jour passent en "annule" (statut
     * réutilisé — aucune valeur "à réattribuer" dédiée dans l'enum, même convention
     * que pour un refus médecin, voir RdvValidationController) avec un motif explicite,
     * et le patient reçoit un e-mail + une notification in-app l'invitant à reprendre
     * un rendez-vous. Aucun RDV n'est jamais supprimé.
     */
    public function store(StoreBlocageRequest $request): JsonResponse
    {
        $medecin = $this->medecinConnecte($request);
        $data = $request->validated();

        try {
            $blocage = Blocage::create([
                'medecin_id' => $medecin->id,
                'date' => $data['date'],
                'motif' => $data['motif'] ?? null,
            ]);
        } catch (QueryException $e) {
            throw ValidationException::withMessages([
                'date' => ['Cette date est déjà bloquée.'],
            ]);
        }

        $rendezVousConcernes = RendezVous::with('patient')
            ->where('medecin_id', $medecin->id)
            ->whereDate('date_heure', $data['date'])
            ->where('statut', '!=', 'annule')
            ->get();

        foreach ($rendezVousConcernes as $rendezVous) {
            $rendezVous->update([
                'statut' => 'annule',
                'annule_le' => now(),
                'motif_annulation' => 'Date bloquée par le médecin — merci de reprendre un nouveau rendez-vous.',
            ]);

            $envoyerEmail = $rendezVous->patient->notif_email_rdv;

            if ($envoyerEmail) {
                try {
                    Mail::to($rendezVous->patient->email)->send(new RdvBlocageMail($rendezVous));
                } catch (Throwable $e) {
                    Log::warning('Échec de l\'envoi de l\'e-mail de blocage de RDV.', [
                        'rendez_vous_id' => $rendezVous->id,
                        'erreur' => $e->getMessage(),
                    ]);
                }
            }

            Notification::create([
                'user_id' => $rendezVous->patient_id,
                'rendez_vous_id' => $rendezVous->id,
                'type' => 'annulation',
                'canal' => $envoyerEmail ? 'email' : 'in_app',
                'contenu' => 'Votre rendez-vous du '.$rendezVous->date_heure->format('d/m/Y H:i').' a été annulé (date bloquée par le médecin).',
                'envoye_le' => now(),
            ]);
        }

        return response()->json([
            'data' => [
                'blocage' => $blocage,
                'rendez_vous_annules' => $rendezVousConcernes->count(),
            ],
            'message' => $rendezVousConcernes->isEmpty()
                ? 'Date bloquée avec succès.'
                : 'Date bloquée avec succès. '.$rendezVousConcernes->count().' patient(s) notifié(s).',
        ], 201);
    }

    public function destroy(Request $request, Blocage $blocage): JsonResponse
    {
        $medecin = $this->medecinConnecte($request);

        if ($blocage->medecin_id !== $medecin->id) {
            return response()->json([
                'data' => null,
                'message' => 'Vous ne pouvez supprimer que vos propres blocages.',
            ], 403);
        }

        $blocage->delete();

        return response()->json([
            'data' => null,
            'message' => 'Blocage supprimé.',
        ]);
    }

    private function medecinConnecte(Request $request): Medecin
    {
        return $request->user()->medecin;
    }
}
