<?php

namespace Database\Seeders;

use App\Models\Disponibilite;
use App\Models\Medecin;
use Illuminate\Database\Seeder;

class DisponibiliteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * jour_semaine : 1 = lundi ... 7 = dimanche (convention Carbon::dayOfWeekIso).
     */
    public function run(): void
    {
        $lunVendredi = [1, 2, 3, 4, 5];

        Medecin::with('user')->get()->each(function (Medecin $medecin) use ($lunVendredi) {
            $email = $medecin->user->email;

            // Cas particulier : le cardiologue reçoit aussi le samedi matin.
            if ($email === 'o.diallo@croixbleue.sn') {
                $this->creerCreneaux($medecin, $lunVendredi, '08:00', '12:00');
                $this->creerCreneaux($medecin, $lunVendredi, '15:00', '18:00');
                $this->creerCreneaux($medecin, [6], '08:00', '12:00');

                return;
            }

            // Cas particulier : l'ORL a des horaires décalés.
            if ($email === 'f.sy@croixbleue.sn') {
                $this->creerCreneaux($medecin, $lunVendredi, '09:00', '13:00');
                $this->creerCreneaux($medecin, $lunVendredi, '14:00', '17:00');

                return;
            }

            // Horaires standard : lun-ven 8h-12h / 15h-18h, créneaux de 30 min.
            $this->creerCreneaux($medecin, $lunVendredi, '08:00', '12:00');
            $this->creerCreneaux($medecin, $lunVendredi, '15:00', '18:00');
        });
    }

    private function creerCreneaux(Medecin $medecin, array $jours, string $heureDebut, string $heureFin): void
    {
        foreach ($jours as $jour) {
            Disponibilite::create([
                'medecin_id' => $medecin->id,
                'jour_semaine' => $jour,
                'heure_debut' => $heureDebut,
                'heure_fin' => $heureFin,
                'duree_creneau_min' => 30,
            ]);
        }
    }
}
