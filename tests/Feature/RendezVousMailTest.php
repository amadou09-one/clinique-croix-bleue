<?php

namespace Tests\Feature;

use App\Mail\AnnulationRdvMail;
use App\Mail\RdvConfirmationPatient;
use App\Mail\RdvDemandeValidationMedecin;
use App\Models\Disponibilite;
use App\Models\Medecin;
use App\Models\RendezVous;
use App\Models\Specialite;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RendezVousMailTest extends TestCase
{
    use RefreshDatabase;

    private function creerMedecinAvecDisponibilite(): array
    {
        $medecin = $this->creerMedecin();
        $jour = Carbon::now()->addWeek()->next(Carbon::MONDAY)->startOfDay();

        Disponibilite::create([
            'medecin_id' => $medecin->id,
            'jour_semaine' => $jour->isoWeekday(),
            'heure_debut' => '08:00',
            'heure_fin' => '12:00',
            'duree_creneau_min' => 30,
        ]);

        return [$medecin, $jour];
    }

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

    public function test_la_creation_dun_rdv_met_en_file_la_confirmation_patient_et_la_demande_medecin(): void
    {
        Mail::fake();
        [$medecin, $jour] = $this->creerMedecinAvecDisponibilite();
        $medecin->loadMissing('user');
        $patient = User::factory()->create(['role' => 'patient']);

        $this->actingAs($patient, 'sanctum')
            ->postJson('/api/rendez-vous', [
                'medecin_id' => $medecin->id,
                'date_heure' => $jour->copy()->setTime(9, 0)->toDateTimeString(),
                'motif' => 'Douleurs thoraciques persistantes',
            ])
            ->assertCreated();

        Mail::assertQueued(RdvConfirmationPatient::class, function (RdvConfirmationPatient $mail) use ($patient) {
            return $mail->hasTo($patient->email);
        });

        Mail::assertQueued(RdvDemandeValidationMedecin::class, function (RdvDemandeValidationMedecin $mail) use ($medecin) {
            return $mail->hasTo($medecin->user->email)
                && str_contains($mail->urlAccepter, 'action=accepter')
                && str_contains($mail->urlRefuser, 'action=refuser');
        });
    }

    public function test_lannulation_dun_rdv_envoie_le_mail_dannulation(): void
    {
        Mail::fake();
        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);

        $rendezVous = RendezVous::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'date_heure' => Carbon::now()->addDay(),
            'statut' => 'confirme',
            'cree_par' => $patient->id,
        ]);

        $this->actingAs($patient, 'sanctum')
            ->patchJson("/api/rendez-vous/{$rendezVous->id}/annuler")
            ->assertOk();

        Mail::assertSent(AnnulationRdvMail::class, function (AnnulationRdvMail $mail) use ($patient) {
            return $mail->hasTo($patient->email);
        });

        $this->assertDatabaseHas('notifications', [
            'rendez_vous_id' => $rendezVous->id,
            'user_id' => $patient->id,
            'type' => 'annulation',
            'canal' => 'email',
        ]);
    }

    public function test_le_mail_de_confirmation_nest_pas_envoye_si_la_preference_est_desactivee(): void
    {
        Mail::fake();
        [$medecin, $jour] = $this->creerMedecinAvecDisponibilite();
        $patient = User::factory()->create(['role' => 'patient', 'notif_email_rdv' => false]);

        $this->actingAs($patient, 'sanctum')
            ->postJson('/api/rendez-vous', [
                'medecin_id' => $medecin->id,
                'date_heure' => $jour->copy()->setTime(9, 0)->toDateTimeString(),
            ])
            ->assertCreated();

        Mail::assertNotQueued(RdvConfirmationPatient::class);

        // Le mail au médecin, lui, n'est pas concerné par la préférence du patient.
        Mail::assertQueued(RdvDemandeValidationMedecin::class);
    }

    public function test_aucun_mail_de_creation_ne_contient_le_motif_medical(): void
    {
        Mail::fake();
        [$medecin, $jour] = $this->creerMedecinAvecDisponibilite();
        $patient = User::factory()->create(['role' => 'patient']);
        $motifSensible = 'Diagnostic suspect de diabète type 2';

        $this->actingAs($patient, 'sanctum')
            ->postJson('/api/rendez-vous', [
                'medecin_id' => $medecin->id,
                'date_heure' => $jour->copy()->setTime(9, 0)->toDateTimeString(),
                'motif' => $motifSensible,
            ])
            ->assertCreated();

        Mail::assertQueued(RdvConfirmationPatient::class, function (RdvConfirmationPatient $mail) use ($motifSensible) {
            return ! str_contains($mail->render(), $motifSensible);
        });

        Mail::assertQueued(RdvDemandeValidationMedecin::class, function (RdvDemandeValidationMedecin $mail) use ($motifSensible) {
            return ! str_contains($mail->render(), $motifSensible);
        });
    }
}
