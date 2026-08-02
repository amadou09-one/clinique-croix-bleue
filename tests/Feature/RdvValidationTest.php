<?php

namespace Tests\Feature;

use App\Mail\RdvDecisionPatient;
use App\Models\Medecin;
use App\Models\RendezVous;
use App\Models\Specialite;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RdvValidationTest extends TestCase
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

    private function creerRendezVousEnAttente(): RendezVous
    {
        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);

        return RendezVous::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'date_heure' => Carbon::now()->addDays(2),
            'statut' => 'en_attente',
            'cree_par' => $patient->id,
        ]);
    }

    private function lienSigne(RendezVous $rendezVous, string $action): string
    {
        return URL::temporarySignedRoute('rdv.validation', now()->addHours(48), [
            'rendezVous' => $rendezVous->id,
            'action' => $action,
        ]);
    }

    public function test_le_lien_accepter_confirme_le_rdv_et_envoie_la_decision(): void
    {
        Mail::fake();
        $rendezVous = $this->creerRendezVousEnAttente();

        $this->get($this->lienSigne($rendezVous, 'accepter'))
            ->assertOk()
            ->assertSee('Rendez-vous confirmé');

        $this->assertSame('confirme', $rendezVous->fresh()->statut);

        Mail::assertSent(RdvDecisionPatient::class, function (RdvDecisionPatient $mail) use ($rendezVous) {
            return $mail->rendezVous->id === $rendezVous->id && $mail->accepte === true;
        });

        $this->assertDatabaseHas('notifications', [
            'rendez_vous_id' => $rendezVous->id,
            'type' => 'confirmation',
            'canal' => 'email',
        ]);
    }

    public function test_le_lien_refuser_annule_le_rdv_et_envoie_la_decision(): void
    {
        Mail::fake();
        $rendezVous = $this->creerRendezVousEnAttente();

        $this->get($this->lienSigne($rendezVous, 'refuser'))
            ->assertOk()
            ->assertSee('Rendez-vous refusé');

        $rendezVousFrais = $rendezVous->fresh();
        $this->assertSame('annule', $rendezVousFrais->statut);
        $this->assertSame('Refusé par le médecin.', $rendezVousFrais->motif_annulation);

        Mail::assertSent(RdvDecisionPatient::class, function (RdvDecisionPatient $mail) use ($rendezVous) {
            return $mail->rendezVous->id === $rendezVous->id && $mail->accepte === false;
        });

        $this->assertDatabaseHas('notifications', [
            'rendez_vous_id' => $rendezVous->id,
            'type' => 'annulation',
            'canal' => 'email',
        ]);
    }

    public function test_la_decision_du_medecin_cree_une_notification_in_app_meme_si_lemail_est_desactive(): void
    {
        Mail::fake();
        $rendezVous = $this->creerRendezVousEnAttente();
        $rendezVous->patient->update(['notif_email_rdv' => false]);

        $this->get($this->lienSigne($rendezVous, 'accepter'))->assertOk();

        Mail::assertNothingSent();
        $this->assertDatabaseHas('notifications', [
            'rendez_vous_id' => $rendezVous->id,
            'type' => 'confirmation',
            'canal' => 'in_app',
        ]);
    }

    public function test_une_signature_invalide_ne_modifie_pas_le_statut(): void
    {
        Mail::fake();
        $rendezVous = $this->creerRendezVousEnAttente();

        $lienValide = $this->lienSigne($rendezVous, 'accepter');
        $lienAltere = str_replace('action=accepter', 'action=refuser', $lienValide);

        $this->get($lienAltere)->assertStatus(403);

        $this->assertSame('en_attente', $rendezVous->fresh()->statut);
        Mail::assertNothingSent();
    }

    public function test_un_rdv_deja_traite_ne_peut_pas_etre_retraite(): void
    {
        Mail::fake();
        $rendezVous = $this->creerRendezVousEnAttente();
        $rendezVous->update(['statut' => 'confirme']);

        $this->get($this->lienSigne($rendezVous, 'refuser'))
            ->assertOk()
            ->assertSee('déjà été traité');

        $this->assertSame('confirme', $rendezVous->fresh()->statut);
        Mail::assertNothingSent();
    }
}
