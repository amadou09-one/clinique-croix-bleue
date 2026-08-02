<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DossierMedical;
use App\Models\Medecin;
use App\Models\RendezVous;
use App\Models\Traitement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedecinPatientController extends Controller
{
    /**
     * Patients ayant eu au moins un rendez-vous (non annulé) avec le médecin connecté.
     * Statut calculé : "actif" (consultation honorée il y a moins de 6 mois),
     * "nouveau" (un seul RDV, à venir, sans historique), sinon "inactif".
     */
    public function index(Request $request): JsonResponse
    {
        $medecin = $this->medecinConnecte($request);
        $maintenant = Carbon::now();

        $parPatient = RendezVous::where('medecin_id', $medecin->id)
            ->where('statut', '!=', 'annule')
            ->with('patient:id,prenom,nom,date_naissance')
            ->get()
            ->groupBy('patient_id');

        $dossiers = DossierMedical::whereIn('patient_id', $parPatient->keys())
            ->get()
            ->keyBy('patient_id');

        $patients = $parPatient->map(function ($rendezVous, $patientId) use ($dossiers, $maintenant) {
            $patient = $rendezVous->first()->patient;
            $dossier = $dossiers->get($patientId);

            $derniereConsultation = $rendezVous->where('statut', 'honore')->sortByDesc('date_heure')->first();
            $prochainRdv = $rendezVous
                ->filter(fn (RendezVous $r) => $r->date_heure->gt($maintenant) && in_array($r->statut, ['confirme', 'en_attente'], true))
                ->sortBy('date_heure')
                ->first();

            if ($derniereConsultation && $derniereConsultation->date_heure->gte($maintenant->copy()->subMonths(6))) {
                $statut = 'actif';
            } elseif ($rendezVous->count() === 1 && $prochainRdv) {
                $statut = 'nouveau';
            } else {
                $statut = 'inactif';
            }

            return [
                'id' => $patientId,
                'prenom' => $patient->prenom,
                'nom' => $patient->nom,
                'age' => $patient->date_naissance?->age,
                'groupe_sanguin' => $dossier?->groupe_sanguin,
                'allergies' => $dossier?->allergies,
                'derniere_consultation' => $derniereConsultation?->date_heure,
                'prochain_rdv' => $prochainRdv?->date_heure,
                'statut' => $statut,
            ];
        })->values();

        if ($request->filled('recherche')) {
            $recherche = mb_strtolower($request->query('recherche'));
            $patients = $patients->filter(
                fn (array $p) => str_contains(mb_strtolower($p['prenom'].' '.$p['nom']), $recherche)
            )->values();
        }

        if ($request->filled('statut')) {
            $patients = $patients->where('statut', $request->query('statut'))->values();
        }

        $sansProchainRdv = Carbon::now()->addCentury();
        $patients = $patients->sortBy(fn (array $p) => $p['prochain_rdv'] ?? $sansProchainRdv)->values();

        return response()->json([
            'data' => $patients,
            'message' => 'Patients récupérés.',
        ]);
    }

    /**
     * Fiche complète d'un patient — réservée au médecin qui l'a déjà reçu en RDV.
     * L'historique de RDV et les traitements sont limités à CE médecin uniquement
     * (confidentialité : jamais les données d'un confrère). Les allergies sont
     * toujours renvoyées (même null), conformément à la règle métier n°7.
     */
    public function show(Request $request, User $patient): JsonResponse
    {
        $medecin = $this->medecinConnecte($request);

        $dejaVu = RendezVous::where('medecin_id', $medecin->id)
            ->where('patient_id', $patient->id)
            ->exists();

        if (! $dejaVu) {
            return response()->json([
                'data' => null,
                'message' => "Vous n'avez pas les droits nécessaires pour accéder à cette ressource.",
            ], 403);
        }

        $dossier = DossierMedical::where('patient_id', $patient->id)->first();

        $traitements = Traitement::where('patient_id', $patient->id)
            ->where('medecin_id', $medecin->id)
            ->orderByDesc('date_debut')
            ->get();

        $historique = RendezVous::where('medecin_id', $medecin->id)
            ->where('patient_id', $patient->id)
            ->orderByDesc('date_heure')
            ->get(['id', 'date_heure', 'statut', 'motif']);

        return response()->json([
            'data' => [
                'patient' => $patient,
                'dossier_medical' => [
                    'groupe_sanguin' => $dossier?->groupe_sanguin,
                    'poids_kg' => $dossier?->poids_kg,
                    'taille_cm' => $dossier?->taille_cm,
                    'tension' => $dossier?->tension,
                    'allergies' => $dossier?->allergies,
                ],
                'traitements' => $traitements,
                'historique_rendez_vous' => $historique,
            ],
            'message' => 'Fiche patient récupérée.',
        ]);
    }

    private function medecinConnecte(Request $request): Medecin
    {
        return $request->user()->medecin;
    }
}
