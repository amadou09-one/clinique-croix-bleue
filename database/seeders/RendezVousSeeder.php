<?php

namespace Database\Seeders;

use App\Models\Medecin;
use App\Models\RendezVous;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class RendezVousSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Quelques RDV réalistes pour pouvoir tester la logique de créneaux occupés
     * dès la première utilisation (sans avoir à en créer manuellement).
     */
    public function run(): void
    {
        $patient1 = User::where('email', 'patient1@croixbleue.sn')->firstOrFail();
        $patient2 = User::where('email', 'patient2@croixbleue.sn')->firstOrFail();

        $medecinGeneraliste = $this->medecinParEmail('medecin@croixbleue.sn');
        $cardiologue = $this->medecinParEmail('o.diallo@croixbleue.sn');
        $pediatre = $this->medecinParEmail('a.diagne@croixbleue.sn');
        $gynecologue = $this->medecinParEmail('m.cisse@croixbleue.sn');

        // RDV à venir, confirmé — occupe le créneau lundi 9h chez le généraliste.
        RendezVous::create([
            'patient_id' => $patient1->id,
            'medecin_id' => $medecinGeneraliste->id,
            'date_heure' => $this->prochain(1, '09:00'),
            'duree_min' => 30,
            'statut' => 'confirme',
            'motif' => 'Consultation de suivi',
            'cree_par' => $patient1->id,
        ]);

        // RDV à venir, en attente — occupe le créneau mardi 16h chez le cardiologue.
        RendezVous::create([
            'patient_id' => $patient2->id,
            'medecin_id' => $cardiologue->id,
            'date_heure' => $this->prochain(2, '16:00'),
            'duree_min' => 30,
            'statut' => 'en_attente',
            'motif' => 'Douleurs thoraciques',
            'cree_par' => $patient2->id,
        ]);

        // RDV passé, honoré — pour tester l'historique "passés".
        RendezVous::create([
            'patient_id' => $patient1->id,
            'medecin_id' => $pediatre->id,
            'date_heure' => Carbon::now()->subWeek()->setTime(10, 0),
            'duree_min' => 30,
            'statut' => 'honore',
            'motif' => 'Vaccination',
            'cree_par' => $patient1->id,
        ]);

        // RDV annulé — le créneau redevient disponible malgré son existence en base.
        RendezVous::create([
            'patient_id' => $patient2->id,
            'medecin_id' => $gynecologue->id,
            'date_heure' => $this->prochain(3, '10:00'),
            'duree_min' => 30,
            'statut' => 'annule',
            'motif' => 'Consultation de routine',
            'cree_par' => $patient2->id,
            'annule_le' => Carbon::now()->subDay(),
            'motif_annulation' => 'Empêchement personnel',
        ]);
    }

    private function medecinParEmail(string $email): Medecin
    {
        return Medecin::whereHas('user', fn ($q) => $q->where('email', $email))->firstOrFail();
    }

    /**
     * Prochaine occurrence du jour de la semaine donné (1 = lundi ... 7 = dimanche)
     * à l'heure indiquée, toujours dans le futur.
     */
    private function prochain(int $jourIso, string $heure): Carbon
    {
        [$h, $m] = explode(':', $heure);

        $date = Carbon::now()->startOfDay();
        while ($date->isoWeekday() !== $jourIso) {
            $date->addDay();
        }

        $date->setTime((int) $h, (int) $m);

        if ($date->lte(Carbon::now())) {
            $date->addWeek();
        }

        return $date;
    }
}
