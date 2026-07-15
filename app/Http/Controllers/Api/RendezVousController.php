<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRendezVousRequest;
use App\Models\Medecin;
use App\Models\RendezVous;
use App\Services\CreneauxCalculator;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RendezVousController extends Controller
{
    public function __construct(private readonly CreneauxCalculator $calculator)
    {
    }

    /**
     * Création d'un rendez-vous par le patient authentifié.
     * Toute la logique de validité du créneau est recalculée côté serveur —
     * on ne fait jamais confiance à ce que le client prétend disponible.
     */
    public function store(StoreRendezVousRequest $request): JsonResponse
    {
        $data = $request->validated();
        $medecin = Medecin::findOrFail($data['medecin_id']);
        $dateHeure = Carbon::parse($data['date_heure']);
        $patient = $request->user();

        if ($dateHeure->lte(Carbon::now())) {
            throw ValidationException::withMessages([
                'date_heure' => ['Impossible de réserver un créneau dans le passé.'],
            ]);
        }

        if (! $this->calculator->estCreneauValide($medecin, $dateHeure)) {
            throw ValidationException::withMessages([
                'date_heure' => ["Ce créneau n'est pas disponible chez ce médecin."],
            ]);
        }

        $creneauPris = RendezVous::where('medecin_id', $medecin->id)
            ->where('date_heure', $dateHeure)
            ->where('statut', '!=', 'annule')
            ->exists();

        if ($creneauPris) {
            throw ValidationException::withMessages([
                'date_heure' => ['Ce créneau vient d\'être réservé par un autre patient.'],
            ]);
        }

        $memeJourActif = RendezVous::where('medecin_id', $medecin->id)
            ->where('patient_id', $patient->id)
            ->whereDate('date_heure', $dateHeure->toDateString())
            ->whereIn('statut', ['en_attente', 'confirme'])
            ->exists();

        if ($memeJourActif) {
            throw ValidationException::withMessages([
                'date_heure' => ['Vous avez déjà un rendez-vous actif avec ce médecin ce jour-là.'],
            ]);
        }

        try {
            $rendezVous = RendezVous::create([
                'patient_id' => $patient->id,
                'medecin_id' => $medecin->id,
                'date_heure' => $dateHeure,
                'duree_min' => $this->calculator->dureeCreneau($medecin, $dateHeure),
                'statut' => 'en_attente',
                'motif' => $data['motif'] ?? null,
                'cree_par' => $patient->id,
            ]);
        } catch (QueryException $e) {
            // Filet de sécurité : la contrainte UNIQUE(medecin_id, date_heure) protège
            // contre une réservation concurrente passée entre les vérifications ci-dessus
            // et l'insertion (deux requêtes quasi simultanées sur le même créneau).
            throw ValidationException::withMessages([
                'date_heure' => ['Ce créneau vient d\'être réservé par un autre patient.'],
            ]);
        }

        return response()->json([
            'data' => $rendezVous->load(['medecin.user:id,prenom,nom', 'medecin.specialite']),
            'message' => 'Rendez-vous créé avec succès.',
        ], 201);
    }

    /**
     * Rendez-vous du patient authentifié, séparés en à venir / passés.
     */
    public function mesRendezVous(Request $request): JsonResponse
    {
        $patient = $request->user();
        $maintenant = Carbon::now();

        $rendezVous = RendezVous::with(['medecin.user:id,prenom,nom', 'medecin.specialite'])
            ->where('patient_id', $patient->id)
            ->orderBy('date_heure')
            ->get();

        return response()->json([
            'data' => [
                'a_venir' => $rendezVous
                    ->filter(fn (RendezVous $r) => $r->date_heure->gt($maintenant) && $r->statut !== 'annule')
                    ->values(),
                'passes' => $rendezVous
                    ->filter(fn (RendezVous $r) => $r->date_heure->lte($maintenant) || $r->statut === 'annule')
                    ->values(),
            ],
            'message' => 'Vos rendez-vous.',
        ]);
    }

    /**
     * Annulation par le patient propriétaire uniquement, jusqu'à 6 h avant le RDV.
     */
    public function annuler(Request $request, RendezVous $rendezVous): JsonResponse
    {
        $patient = $request->user();

        if ($rendezVous->patient_id !== $patient->id) {
            return response()->json([
                'data' => null,
                'message' => 'Vous ne pouvez annuler que vos propres rendez-vous.',
            ], 403);
        }

        if ($rendezVous->statut === 'annule') {
            throw ValidationException::withMessages([
                'date_heure' => ['Ce rendez-vous est déjà annulé.'],
            ]);
        }

        $limiteAnnulation = $rendezVous->date_heure->copy()->subHours(6);

        if (Carbon::now()->gte($limiteAnnulation)) {
            throw ValidationException::withMessages([
                'date_heure' => ["L'annulation n'est plus possible moins de 6 heures avant le rendez-vous."],
            ]);
        }

        $rendezVous->update([
            'statut' => 'annule',
            'annule_le' => Carbon::now(),
            'motif_annulation' => $request->input('motif_annulation'),
        ]);

        return response()->json([
            'data' => $rendezVous->fresh(['medecin.user:id,prenom,nom', 'medecin.specialite']),
            'message' => 'Rendez-vous annulé.',
        ]);
    }
}
