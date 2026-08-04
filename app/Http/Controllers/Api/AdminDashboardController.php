<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MessageContact;
use App\Models\RendezVous;
use App\Models\Specialite;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    private const MOIS_COURT = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];

    public function stats(): JsonResponse
    {
        $maintenant = Carbon::now();
        $debutMois = $maintenant->copy()->startOfMonth();
        $finMois = $maintenant->copy()->endOfMonth();

        return response()->json([
            'data' => [
                'total_patients' => User::where('role', 'patient')->count(),
                'medecins_actifs' => User::where('role', 'medecin')->where('est_actif', true)->count(),
                'rdv_ce_mois' => RendezVous::whereBetween('date_heure', [$debutMois, $finMois])
                    ->where('statut', '!=', 'annule')
                    ->count(),
                'messages_non_traites' => MessageContact::where('traite', false)->count(),
                'evolution_rdv_6_mois' => $this->evolutionRdv6Mois($maintenant),
                'repartition_specialites' => $this->repartitionSpecialites($debutMois, $finMois),
            ],
            'message' => 'Statistiques administrateur.',
        ]);
    }

    /**
     * @return array<int, array{mois: string, total: int}>
     */
    private function evolutionRdv6Mois(Carbon $maintenant): array
    {
        $evolution = [];

        for ($i = 5; $i >= 0; $i--) {
            $mois = $maintenant->copy()->subMonths($i);

            $total = RendezVous::whereYear('date_heure', $mois->year)
                ->whereMonth('date_heure', $mois->month)
                ->where('statut', '!=', 'annule')
                ->count();

            $evolution[] = [
                'mois' => self::MOIS_COURT[$mois->month - 1].' '.$mois->year,
                'total' => $total,
            ];
        }

        return $evolution;
    }

    /**
     * Taux d'occupation = RDV du mois / capacité mensuelle estimée (créneaux
     * hebdomadaires de tous les médecins actifs de la spécialité × nombre de
     * semaines dans le mois) — approximation raisonnable en l'absence de notion
     * de "créneau réservable" déjà agrégée par semaine dans le schéma.
     *
     * @return array<int, array{nom: string, medecins: int, rdv_mois: int, taux_occupation: int}>
     */
    private function repartitionSpecialites(Carbon $debutMois, Carbon $finMois): array
    {
        $specialites = Specialite::with(['medecins' => function ($query) {
            $query->whereHas('user', fn ($u) => $u->where('est_actif', true))
                ->with('disponibilites');
        }])->get();

        $resultat = [];

        foreach ($specialites as $specialite) {
            $medecins = $specialite->medecins;

            if ($medecins->isEmpty()) {
                continue;
            }

            $medecinIds = $medecins->pluck('id');

            $rdvMois = RendezVous::whereIn('medecin_id', $medecinIds)
                ->whereBetween('date_heure', [$debutMois, $finMois])
                ->where('statut', '!=', 'annule')
                ->count();

            $creneauxParSemaine = 0;
            foreach ($medecins as $medecin) {
                foreach ($medecin->disponibilites as $disponibilite) {
                    $debut = Carbon::parse($disponibilite->heure_debut);
                    $fin = Carbon::parse($disponibilite->heure_fin);
                    $creneauxParSemaine += intdiv($fin->diffInMinutes($debut), max(1, (int) $disponibilite->duree_creneau_min));
                }
            }

            $semainesDansLeMois = $debutMois->daysInMonth / 7;
            $capaciteMois = $creneauxParSemaine * $semainesDansLeMois;
            $tauxOccupation = $capaciteMois > 0 ? (int) min(100, round(($rdvMois / $capaciteMois) * 100)) : 0;

            $resultat[] = [
                'nom' => $specialite->nom,
                'medecins' => $medecins->count(),
                'rdv_mois' => $rdvMois,
                'taux_occupation' => $tauxOccupation,
            ];
        }

        return $resultat;
    }
}
