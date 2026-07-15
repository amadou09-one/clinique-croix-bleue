<?php

namespace App\Services;

use App\Models\Medecin;
use Carbon\Carbon;

/**
 * Calcule les créneaux de rendez-vous d'un médecin.
 *
 * Fuseau horaire : toutes les dates sont stockées et manipulées en UTC
 * (config('app.timezone') === 'UTC'). Le Sénégal (Africa/Dakar) est à UTC+0
 * toute l'année (pas d'heure d'été) : aucune conversion n'est donc nécessaire
 * entre l'heure stockée et l'heure affichée à Dakar. Si l'application devait
 * un jour servir un fuseau différent, il faudrait convertir explicitement ici.
 */
class CreneauxCalculator
{
    /**
     * Grille complète des créneaux du médecin pour une journée donnée,
     * chacun marqué disponible ou non (déjà réservé, ou passé).
     *
     * @return array<int, array{heure: string, disponible: bool}>
     */
    public function pourJour(Medecin $medecin, Carbon $date): array
    {
        $jourIso = $date->isoWeekday(); // 1 = lundi ... 7 = dimanche (cf. DATA-DICTIONARY)

        $disponibilites = $medecin->disponibilites()
            ->where('jour_semaine', $jourIso)
            ->get();

        $heuresPrises = $medecin->rendezVous()
            ->whereDate('date_heure', $date->toDateString())
            ->where('statut', '!=', 'annule')
            ->get()
            ->map(fn ($rdv) => $rdv->date_heure->format('H:i'))
            ->all();

        $maintenant = Carbon::now();
        $creneaux = [];

        foreach ($disponibilites as $disponibilite) {
            $debut = $date->copy()->setTimeFromTimeString($disponibilite->heure_debut);
            $fin = $date->copy()->setTimeFromTimeString($disponibilite->heure_fin);
            $duree = max(1, (int) $disponibilite->duree_creneau_min);

            for ($slot = $debut->copy(); $slot->lt($fin); $slot->addMinutes($duree)) {
                $heure = $slot->format('H:i');

                $creneaux[] = [
                    'heure' => $heure,
                    'disponible' => $slot->gt($maintenant) && ! in_array($heure, $heuresPrises, true),
                ];
            }
        }

        usort($creneaux, fn ($a, $b) => $a['heure'] <=> $b['heure']);

        return $creneaux;
    }

    /**
     * Vérifie qu'un instant donné correspond bien à un créneau réservable
     * (dans une plage de disponibilité du médecin et aligné sur la durée du créneau).
     */
    public function estCreneauValide(Medecin $medecin, Carbon $dateHeure): bool
    {
        $jourIso = $dateHeure->isoWeekday();

        $disponibilites = $medecin->disponibilites()
            ->where('jour_semaine', $jourIso)
            ->get();

        foreach ($disponibilites as $disponibilite) {
            $debut = $dateHeure->copy()->setTimeFromTimeString($disponibilite->heure_debut);
            $fin = $dateHeure->copy()->setTimeFromTimeString($disponibilite->heure_fin);
            $duree = max(1, (int) $disponibilite->duree_creneau_min);

            if ($dateHeure->lt($debut) || $dateHeure->gte($fin)) {
                continue;
            }

            if ($debut->diffInMinutes($dateHeure) % $duree === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Durée (en minutes) du créneau applicable à cet instant chez ce médecin.
     */
    public function dureeCreneau(Medecin $medecin, Carbon $dateHeure): int
    {
        $jourIso = $dateHeure->isoWeekday();

        return (int) ($medecin->disponibilites()
            ->where('jour_semaine', $jourIso)
            ->value('duree_creneau_min') ?? 30);
    }
}
