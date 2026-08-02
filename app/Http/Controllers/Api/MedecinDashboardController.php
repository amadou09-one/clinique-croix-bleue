<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Medecin;
use App\Models\RendezVous;
use App\Services\CreneauxCalculator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedecinDashboardController extends Controller
{
    public function __construct(private readonly CreneauxCalculator $calculator)
    {
    }

    /**
     * Agenda du médecin connecté pour une date donnée (aujourd'hui par défaut).
     * Ne retourne que les RDV du médecin authentifié — jamais ceux d'un confrère.
     */
    public function agenda(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'date.date_format' => 'La date doit être au format AAAA-MM-JJ.',
        ]);

        $medecin = $this->medecinConnecte($request);

        $date = $request->filled('date')
            ? Carbon::createFromFormat('Y-m-d', $request->query('date'), 'UTC')->startOfDay()
            : Carbon::now()->startOfDay();

        // "annule" exclu : le créneau redevient "libre" une fois fusionné côté frontend
        // avec la grille de /medecins/{id}/creneaux (qui ne compte pas les RDV annulés
        // comme pris — voir CreneauxCalculator).
        $rendezVous = RendezVous::with('patient:id,prenom,nom')
            ->where('medecin_id', $medecin->id)
            ->where('statut', '!=', 'annule')
            ->whereDate('date_heure', $date->toDateString())
            ->orderBy('date_heure')
            ->get();

        return response()->json([
            'data' => $rendezVous,
            'message' => 'Agenda du '.$date->toDateString().'.',
        ]);
    }

    /**
     * Statistiques du tableau de bord médecin, calculées sur les RDV du médecin connecté.
     */
    public function stats(Request $request): JsonResponse
    {
        $medecin = $this->medecinConnecte($request);
        $maintenant = Carbon::now();
        $aujourdhui = $maintenant->toDateString();
        $debutSemaine = $maintenant->copy()->startOfWeek(Carbon::MONDAY);
        $finSemaine = $maintenant->copy()->endOfWeek(Carbon::SUNDAY);
        $debutMois = $maintenant->copy()->startOfMonth();
        $finMois = $maintenant->copy()->endOfMonth();

        $rdvAujourdhui = RendezVous::where('medecin_id', $medecin->id)
            ->whereDate('date_heure', $aujourdhui)
            ->where('statut', '!=', 'annule')
            ->get();

        $prochainPatient = $rdvAujourdhui
            ->filter(fn (RendezVous $r) => $r->date_heure->gt($maintenant) && in_array($r->statut, ['en_attente', 'confirme'], true))
            ->sortBy('date_heure')
            ->first();

        if ($prochainPatient) {
            $prochainPatient->loadMissing('patient:id,prenom,nom');
        }

        $rdvSemaine = RendezVous::where('medecin_id', $medecin->id)
            ->whereBetween('date_heure', [$debutSemaine, $finSemaine])
            ->where('statut', '!=', 'annule')
            ->get();

        $rdvSemainePasses = $rdvSemaine->filter(fn (RendezVous $r) => $r->date_heure->lte($maintenant) && in_array($r->statut, ['honore', 'absent'], true));
        $nbAbsents = $rdvSemainePasses->where('statut', 'absent')->count();
        $tauxAbsence = $rdvSemainePasses->count() > 0
            ? round(($nbAbsents / $rdvSemainePasses->count()) * 100)
            : 0;

        $nouveauxPatientsSemaine = RendezVous::where('medecin_id', $medecin->id)
            ->select('patient_id')
            ->groupBy('patient_id')
            ->havingRaw('MIN(date_heure) BETWEEN ? AND ?', [$debutSemaine, $finSemaine])
            ->get()
            ->count();

        $creneauxDisponiblesSemaine = 0;
        for ($jour = $debutSemaine->copy(); $jour->lte($finSemaine); $jour->addDay()) {
            $creneauxDisponiblesSemaine += collect($this->calculator->pourJour($medecin, $jour))
                ->where('disponible', true)
                ->count();
        }

        return response()->json([
            'data' => [
                'rdv_aujourdhui' => $rdvAujourdhui->count(),
                'patients_vus_aujourdhui' => $rdvAujourdhui->where('statut', 'honore')->count(),
                'prochain_patient' => $prochainPatient,
                'consultations_ce_mois' => RendezVous::where('medecin_id', $medecin->id)
                    ->whereBetween('date_heure', [$debutMois, $finMois])
                    ->where('statut', 'honore')
                    ->count(),
                'rdv_semaine' => $rdvSemaine->count(),
                'nouveaux_patients_semaine' => $nouveauxPatientsSemaine,
                'taux_absence_semaine' => $tauxAbsence,
                'creneaux_disponibles_semaine' => $creneauxDisponiblesSemaine,
            ],
            'message' => 'Statistiques du tableau de bord médecin.',
        ]);
    }

    private function medecinConnecte(Request $request): Medecin
    {
        return $request->user()->medecin;
    }
}
