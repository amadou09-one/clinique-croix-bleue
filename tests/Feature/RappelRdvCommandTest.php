<?php

namespace Tests\Feature;

use App\Mail\RappelRdvMail;
use App\Models\Medecin;
use App\Models\RendezVous;
use App\Models\Specialite;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RappelRdvCommandTest extends TestCase
{
    use RefreshDatabase;

    private function creerMedecin(): Medecin
    {
        $userMedecin = User::factory()->create(['role' => 'medecin']);
        $specialite = Specialite::create(['nom' => 'Cardiologie '.uniqid()]);

        return Medecin::create([
            'user_id' => $userMedecin->id,
            'specialite_id' => $specialite->id,
            'annees_experience' => 5,
        ]);
    }

    private function creerRendezVous(Medecin $medecin, User $patient, Carbon $dateHeure, string $statut = 'confirme'): RendezVous
    {
        return RendezVous::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'date_heure' => $dateHeure,
            'statut' => $statut,
            'cree_par' => $patient->id,
        ]);
    }

    public function test_envoie_un_rappel_pour_un_rdv_dans_20_heures(): void
    {
        Mail::fake();
        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);
        $rendezVous = $this->creerRendezVous($medecin, $patient, Carbon::now()->addHours(20));

        $this->artisan('rdv:envoyer-rappels')->assertExitCode(0);

        Mail::assertSent(RappelRdvMail::class, 1);
        Mail::assertSent(RappelRdvMail::class, function (RappelRdvMail $mail) use ($patient) {
            return $mail->hasTo($patient->email);
        });
        $this->assertDatabaseHas('notifications', [
            'rendez_vous_id' => $rendezVous->id,
            'type' => 'rappel',
            'canal' => 'email',
        ]);
    }

    public function test_relancer_la_commande_nenvoie_pas_de_doublon(): void
    {
        Mail::fake();
        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);
        $this->creerRendezVous($medecin, $patient, Carbon::now()->addHours(20));

        $this->artisan('rdv:envoyer-rappels');
        $this->artisan('rdv:envoyer-rappels');

        Mail::assertSent(RappelRdvMail::class, 1);
        $this->assertSame(1, \App\Models\Notification::where('type', 'rappel')->count());
    }

    public function test_ne_previent_pas_un_rdv_dans_3_jours(): void
    {
        Mail::fake();
        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);
        $this->creerRendezVous($medecin, $patient, Carbon::now()->addDays(3));

        $this->artisan('rdv:envoyer-rappels');

        Mail::assertNothingSent();
    }

    public function test_ne_previent_pas_un_rdv_annule(): void
    {
        Mail::fake();
        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);
        $this->creerRendezVous($medecin, $patient, Carbon::now()->addHours(20), 'annule');

        $this->artisan('rdv:envoyer-rappels');

        Mail::assertNothingSent();
    }

    public function test_naucun_mail_nest_envoye_si_la_preference_de_rappel_est_desactivee_mais_la_notification_in_app_est_creee(): void
    {
        Mail::fake();
        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient', 'notif_email_rappel' => false]);
        $rendezVous = $this->creerRendezVous($medecin, $patient, Carbon::now()->addHours(20));

        $this->artisan('rdv:envoyer-rappels')->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertDatabaseHas('notifications', [
            'rendez_vous_id' => $rendezVous->id,
            'type' => 'rappel',
            'canal' => 'in_app',
        ]);
    }
}
