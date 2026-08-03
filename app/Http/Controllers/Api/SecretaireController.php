<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Blocage;
use App\Models\Medecin;
use App\Models\RendezVous;
use App\Services\CreneauxCalculator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecretaireController extends Controller
{
    public function __construct(private readonly CreneauxCalculator $calculator)
    {
    }

    public function stats(): JsonResponse
    {
        $aujourdhui = Carbon::today();
        $debutSemaine = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $finSemaine = Carbon::now()->endOfWeek(Carbon::SUNDAY);

        $rdvAujourdhui = RendezVous::whereDate('date_heure', $aujourdhui)
            ->where('statut', '!=', 'annule')
            ->count();

        $enAttenteConfirmation = RendezVous::whereDate('date_heure', $aujourdhui)
            ->where('statut', 'en_attente')
            ->count();

        $nouveauxPatientsSemaine = RendezVous::where('statut', '!=', 'annule')
            ->select('patient_id')
            ->groupBy('patient_id')
            ->havingRaw('MIN(date_heure) BETWEEN ? AND ?', [$debutSemaine, $finSemaine])
            ->get()
            ->count();

        return response()->json([
            'data' => [
                'rdv_aujourdhui' => $rdvAujourdhui,
                'en_attente_confirmation' => $enAttenteConfirmation,
                'nouveaux_patients_semaine' => $nouveauxPatientsSemaine,
                'medecins_presents_aujourdhui' => $this->medecinsPresents($aujourdhui),
                'medecins_total_actifs' => $this->medecinsActifs()->count(),
            ],
            'message' => 'Statistiques du secrétariat.',
        ]);
    }

    public function planning(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'date.date_format' => 'La date doit être au format AAAA-MM-JJ.',
        ]);

        $date = $request->filled('date')
            ? Carbon::createFromFormat('Y-m-d', $request->query('date'), 'UTC')->startOfDay()
            : Carbon::now()->startOfDay();

        $medecins = $this->medecinsActifs();

        $rendezVousParMedecin = RendezVous::whereIn('medecin_id', $medecins->pluck('id'))
            ->whereDate('date_heure', $date->toDateString())
            ->where('statut', '!=', 'annule')
            ->with('patient:id,prenom,nom')
            ->get()
            ->groupBy('medecin_id');

        $grilleParMedecin = [];
        $heures = [];

        foreach ($medecins as $medecin) {
            $creneaux = $this->calculator->pourJour($medecin, $date);
            $rdvsDuMedecin = $rendezVousParMedecin->get($medecin->id, collect())
                ->keyBy(fn (RendezVous $r) => $r->date_heure->format('H:i'));

            $grilleParMedecin[$medecin->id] = [];

            foreach ($creneaux as $creneau) {
                $heures[] = $creneau['heure'];
                $rdv = $rdvsDuMedecin->get($creneau['heure']);

                $grilleParMedecin[$medecin->id][$creneau['heure']] = [
                    'medecin_id' => $medecin->id,
                    'nom' => 'Dr '.$medecin->user->nom,
                    'statut' => $rdv ? 'rdv' : 'libre',
                    'rdv' => $rdv ? [
                        'id' => $rdv->id,
                        'statut' => $rdv->statut,
                        'motif' => $rdv->motif,
                        'patient' => $rdv->patient,
                    ] : null,
                ];
            }
        }

        $heures = collect($heures)->unique()->sort()->values();

        $planning = $heures->map(function (string $heure) use ($medecins, $grilleParMedecin) {
            return [
                'heure' => $heure,
                'medecins' => $medecins->map(function (Medecin $medecin) use ($heure, $grilleParMedecin) {
                    return $grilleParMedecin[$medecin->id][$heure] ?? [
                        'medecin_id' => $medecin->id,
                        'nom' => 'Dr '.$medecin->user->nom,
                        'statut' => 'ferme',
                        'rdv' => null,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'data' => [
                'date' => $date->toDateString(),
                'medecins' => $medecins->map(fn (Medecin $m) => [
                    'id' => $m->id,
                    'nom' => 'Dr '.$m->user->nom,
                    'specialite' => $m->specialite->nom,
                ])->values(),
                'planning' => $planning,
            ],
            'message' => 'Planning du '.$date->toDateString().'.',
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{nom: string, specialite: string, statut: string}>
     */
    private function medecinsPresents(Carbon $date): \Illuminate\Support\Collection
    {
        $jourIso = $date->isoWeekday();
        $maintenant = Carbon::now();

        $medecinsBloques = Blocage::whereDate('date', $date->toDateString())->pluck('medecin_id');

        return $this->medecinsActifs()
            ->filter(fn (Medecin $medecin) => ! $medecinsBloques->contains($medecin->id)
                && $medecin->disponibilites->where('jour_semaine', $jourIso)->isNotEmpty())
            ->map(function (Medecin $medecin) use ($date, $maintenant) {
                $enConsultation = RendezVous::where('medecin_id', $medecin->id)
                    ->where('statut', 'confirme')
                    ->whereDate('date_heure', $date->toDateString())
                    ->get()
                    ->contains(fn (RendezVous $r) => $maintenant->between($r->date_heure, $r->date_heure->copy()->addMinutes($r->duree_min)));

                return [
                    'nom' => 'Dr '.$medecin->user->nom,
                    'specialite' => $medecin->specialite->nom,
                    'statut' => $enConsultation ? 'en_consultation' : 'disponible',
                ];
            })
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Medecin>
     */
    private function medecinsActifs(): \Illuminate\Support\Collection
    {
        return Medecin::with(['user:id,prenom,nom,est_actif', 'specialite', 'disponibilites'])
            ->whereHas('user', fn ($q) => $q->where('est_actif', true))
            ->get();
    }
}
