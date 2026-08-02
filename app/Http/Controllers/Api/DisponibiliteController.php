<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDisponibiliteRequest;
use App\Models\Disponibilite;
use App\Models\Medecin;
use App\Models\RendezVous;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DisponibiliteController extends Controller
{
    /**
     * Disponibilités du médecin connecté, groupées par jour de la semaine (1 à 7).
     * Les 7 jours sont toujours présents dans la réponse, même sans plage (jour fermé),
     * pour que le frontend puisse construire une grille hebdomadaire fixe.
     */
    public function index(Request $request): JsonResponse
    {
        $medecin = $this->medecinConnecte($request);

        $parJour = Disponibilite::where('medecin_id', $medecin->id)
            ->orderBy('heure_debut')
            ->get()
            ->groupBy('jour_semaine');

        $jours = [];
        for ($jour = 1; $jour <= 7; $jour++) {
            $lignes = $parJour->get($jour, collect());

            $jours[] = [
                'jour_semaine' => $jour,
                'duree_creneau_min' => (int) (optional($lignes->first())->duree_creneau_min ?? 30),
                'plages' => $lignes->map(fn (Disponibilite $d) => [
                    'heure_debut' => substr($d->heure_debut, 0, 5),
                    'heure_fin' => substr($d->heure_fin, 0, 5),
                ])->values(),
            ];
        }

        return response()->json([
            'data' => $jours,
            'message' => 'Disponibilités récupérées.',
        ]);
    }

    /**
     * Remplace intégralement les plages d'un jour donné. Refuse (422) si un rendez-vous
     * futur non annulé ne correspondrait plus aux nouveaux horaires — on ne supprime
     * jamais silencieusement un rendez-vous existant.
     */
    public function update(UpdateDisponibiliteRequest $request): JsonResponse
    {
        $medecin = $this->medecinConnecte($request);
        $data = $request->validated();

        if ($this->plagesSeChevauchent($data['plages'])) {
            throw ValidationException::withMessages([
                'plages' => ['Les plages horaires ne doivent pas se chevaucher.'],
            ]);
        }

        $rendezVousEnConflit = RendezVous::with('patient:id,prenom,nom')
            ->where('medecin_id', $medecin->id)
            ->where('date_heure', '>', Carbon::now())
            ->where('statut', '!=', 'annule')
            ->get()
            ->filter(fn (RendezVous $r) => $r->date_heure->isoWeekday() === $data['jour_semaine'])
            ->filter(fn (RendezVous $r) => ! $this->creneauValideDansPlages($data['plages'], $data['duree_creneau_min'], $r->date_heure))
            ->values();

        if ($rendezVousEnConflit->isNotEmpty()) {
            return response()->json([
                'data' => ['rendez_vous_concernes' => $rendezVousEnConflit],
                'message' => 'Impossible de modifier ces horaires : '.$rendezVousEnConflit->count()
                    .' rendez-vous existant(s) ne correspondraient plus aux nouvelles disponibilités.',
            ], 422);
        }

        DB::transaction(function () use ($medecin, $data) {
            Disponibilite::where('medecin_id', $medecin->id)
                ->where('jour_semaine', $data['jour_semaine'])
                ->delete();

            foreach ($data['plages'] as $plage) {
                Disponibilite::create([
                    'medecin_id' => $medecin->id,
                    'jour_semaine' => $data['jour_semaine'],
                    'heure_debut' => $plage['heure_debut'],
                    'heure_fin' => $plage['heure_fin'],
                    'duree_creneau_min' => $data['duree_creneau_min'],
                ]);
            }
        });

        return response()->json([
            'data' => null,
            'message' => 'Disponibilités mises à jour.',
        ]);
    }

    /**
     * @param  array<int, array{heure_debut: string, heure_fin: string}>  $plages
     */
    private function plagesSeChevauchent(array $plages): bool
    {
        for ($i = 0; $i < count($plages); $i++) {
            for ($j = $i + 1; $j < count($plages); $j++) {
                if ($plages[$i]['heure_debut'] < $plages[$j]['heure_fin'] && $plages[$j]['heure_debut'] < $plages[$i]['heure_fin']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{heure_debut: string, heure_fin: string}>  $plages
     */
    private function creneauValideDansPlages(array $plages, int $dureeMin, Carbon $dateHeure): bool
    {
        foreach ($plages as $plage) {
            $debut = $dateHeure->copy()->setTimeFromTimeString($plage['heure_debut']);
            $fin = $dateHeure->copy()->setTimeFromTimeString($plage['heure_fin']);

            if ($dateHeure->lt($debut) || $dateHeure->gte($fin)) {
                continue;
            }

            if ($debut->diffInMinutes($dateHeure) % $dureeMin === 0) {
                return true;
            }
        }

        return false;
    }

    private function medecinConnecte(Request $request): Medecin
    {
        return $request->user()->medecin;
    }
}
