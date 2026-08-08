<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentMedicalRequest;
use App\Http\Requests\StoreTraitementRequest;
use App\Models\DocumentMedical;
use App\Models\DossierMedical;
use App\Models\Medecin;
use App\Models\RendezVous;
use App\Models\Traitement;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

        if (! $this->dejaVu($medecin, $patient)) {
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

        $documents = DocumentMedical::where('patient_id', $patient->id)
            ->whereIn('rendez_vous_id', RendezVous::where('medecin_id', $medecin->id)->where('patient_id', $patient->id)->pluck('id'))
            ->orderByDesc('created_at')
            ->get();

        // Chaque ligne d'historique embarque sa consultation (diagnostic +
        // observations) quand elle existe, pour un affichage enrichi côté fiche
        // patient — les traitements et documents sont regroupés par rendez_vous_id
        // côté frontend à partir des listes ci-dessus.
        $historique = RendezVous::with('consultation')
            ->where('medecin_id', $medecin->id)
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
                'documents' => $documents,
                'historique_rendez_vous' => $historique,
            ],
            'message' => 'Fiche patient récupérée.',
        ]);
    }

    /**
     * Ajoute une ligne de traitement au dossier d'un patient déjà reçu en RDV
     * par ce médecin (interdit de prescrire à un patient jamais consulté).
     */
    public function storeTraitement(StoreTraitementRequest $request, User $patient): JsonResponse
    {
        $medecin = $this->medecinConnecte($request);

        if (! $this->dejaVu($medecin, $patient)) {
            return response()->json([
                'data' => null,
                'message' => "Vous n'avez pas les droits nécessaires pour accéder à cette ressource.",
            ], 403);
        }

        $data = $request->validated();

        if (! empty($data['rendez_vous_id']) && ! $this->rendezVousAppartient($medecin, $patient, $data['rendez_vous_id'])) {
            throw ValidationException::withMessages([
                'rendez_vous_id' => ['Ce rendez-vous ne correspond pas à ce patient et à ce médecin.'],
            ]);
        }

        $traitement = Traitement::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'rendez_vous_id' => $data['rendez_vous_id'] ?? null,
            'medicament' => $data['medicament'],
            'posologie' => $data['posologie'],
            'date_debut' => $data['date_debut'],
            'date_fin' => $data['date_fin'] ?? null,
        ]);

        return response()->json([
            'data' => $traitement,
            'message' => 'Traitement ajouté.',
        ], 201);
    }

    /**
     * Génère une ordonnance PDF simple (en-tête clinique + traitements en cours
     * prescrits par ce médecin, ou ceux liés au rendez_vous_id fourni) et
     * l'enregistre dans documents_medicaux (stockage privé, jamais public).
     */
    public function storeDocument(StoreDocumentMedicalRequest $request, User $patient): JsonResponse
    {
        $medecin = $this->medecinConnecte($request);

        if (! $this->dejaVu($medecin, $patient)) {
            return response()->json([
                'data' => null,
                'message' => "Vous n'avez pas les droits nécessaires pour accéder à cette ressource.",
            ], 403);
        }

        $data = $request->validated();
        $rendezVousId = $data['rendez_vous_id'] ?? null;

        if ($rendezVousId && ! $this->rendezVousAppartient($medecin, $patient, $rendezVousId)) {
            throw ValidationException::withMessages([
                'rendez_vous_id' => ['Ce rendez-vous ne correspond pas à ce patient et à ce médecin.'],
            ]);
        }

        $traitementsQuery = Traitement::where('patient_id', $patient->id)->where('medecin_id', $medecin->id);

        if ($rendezVousId) {
            $traitementsQuery->where('rendez_vous_id', $rendezVousId);
        } else {
            $traitementsQuery->where(function ($q) {
                $q->whereNull('date_fin')->orWhere('date_fin', '>=', now()->toDateString());
            });
        }

        $traitements = $traitementsQuery->orderByDesc('date_debut')->get();

        if ($traitements->isEmpty()) {
            throw ValidationException::withMessages([
                'rendez_vous_id' => ["Aucun traitement à prescrire pour générer une ordonnance."],
            ]);
        }

        $medecin->loadMissing('user', 'specialite');

        $pdf = Pdf::loadView('pdf.ordonnance', [
            'patient' => $patient,
            'medecin' => $medecin,
            'traitements' => $traitements,
            'date' => now(),
        ]);

        $chemin = 'ordonnances/'.$patient->id.'/'.now()->format('Ymd-His').'-'.Str::random(8).'.pdf';
        Storage::disk('local')->put($chemin, $pdf->output());

        $document = DocumentMedical::create([
            'patient_id' => $patient->id,
            'rendez_vous_id' => $rendezVousId,
            'type' => 'ordonnance',
            'titre' => 'Ordonnance du '.now()->format('d/m/Y'),
            'fichier_url' => $chemin,
        ]);

        return response()->json([
            'data' => $document,
            'message' => 'Ordonnance générée.',
        ], 201);
    }

    private function dejaVu(Medecin $medecin, User $patient): bool
    {
        return RendezVous::where('medecin_id', $medecin->id)
            ->where('patient_id', $patient->id)
            ->exists();
    }

    private function rendezVousAppartient(Medecin $medecin, User $patient, int $rendezVousId): bool
    {
        return RendezVous::where('id', $rendezVousId)
            ->where('medecin_id', $medecin->id)
            ->where('patient_id', $patient->id)
            ->exists();
    }

    private function medecinConnecte(Request $request): Medecin
    {
        return $request->user()->medecin;
    }
}
