<?php

namespace Tests\Feature;

use App\Mail\RdvBlocageMail;
use App\Models\Blocage;
use App\Models\Medecin;
use App\Models\RendezVous;
use App\Models\Specialite;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BlocageTest extends TestCase
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

    public function test_bloquer_une_date_sans_rdv_ne_declenche_aucun_mail(): void
    {
        Mail::fake();
        $medecin = $this->creerMedecin();
        $date = Carbon::now()->addWeek()->toDateString();

        $this->actingAs($medecin->user, 'sanctum')
            ->postJson('/api/medecin/blocages', ['date' => $date, 'motif' => 'Congés'])
            ->assertCreated()
            ->assertJsonPath('data.rendez_vous_annules', 0);

        $this->assertDatabaseHas('blocages', ['medecin_id' => $medecin->id, 'date' => $date]);
        Mail::assertNothingSent();
    }

    public function test_bloquer_une_date_avec_rdv_annule_les_rdv_et_envoie_un_mail(): void
    {
        Mail::fake();
        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);
        $date = Carbon::now()->addWeek()->startOfDay();

        $rdv = RendezVous::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'date_heure' => $date->copy()->setTime(9, 0),
            'statut' => 'confirme',
            'cree_par' => $patient->id,
        ]);

        $this->actingAs($medecin->user, 'sanctum')
            ->postJson('/api/medecin/blocages', ['date' => $date->toDateString(), 'motif' => 'Formation'])
            ->assertCreated()
            ->assertJsonPath('data.rendez_vous_annules', 1);

        $this->assertSame('annule', $rdv->fresh()->statut);
        $this->assertNotNull($rdv->fresh()->annule_le);

        Mail::assertSent(RdvBlocageMail::class, function (RdvBlocageMail $mail) use ($rdv, $patient) {
            return $mail->rendezVous->id === $rdv->id && $mail->hasTo($patient->email);
        });

        $this->assertDatabaseHas('notifications', [
            'rendez_vous_id' => $rdv->id,
            'type' => 'annulation',
            'canal' => 'email',
        ]);
    }

    public function test_bloquer_une_date_ne_touche_pas_aux_rdv_deja_annules(): void
    {
        Mail::fake();
        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);
        $date = Carbon::now()->addWeek()->startOfDay();

        RendezVous::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'date_heure' => $date->copy()->setTime(9, 0),
            'statut' => 'annule',
            'cree_par' => $patient->id,
        ]);

        $this->actingAs($medecin->user, 'sanctum')
            ->postJson('/api/medecin/blocages', ['date' => $date->toDateString()])
            ->assertCreated()
            ->assertJsonPath('data.rendez_vous_annules', 0);

        Mail::assertNothingSent();
    }

    public function test_le_mail_de_blocage_nest_pas_envoye_si_la_preference_est_desactivee(): void
    {
        Mail::fake();
        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient', 'notif_email_rdv' => false]);
        $date = Carbon::now()->addWeek()->startOfDay();

        $rdv = RendezVous::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'date_heure' => $date->copy()->setTime(9, 0),
            'statut' => 'confirme',
            'cree_par' => $patient->id,
        ]);

        $this->actingAs($medecin->user, 'sanctum')
            ->postJson('/api/medecin/blocages', ['date' => $date->toDateString()])
            ->assertCreated();

        Mail::assertNothingSent();
        $this->assertDatabaseHas('notifications', [
            'rendez_vous_id' => $rdv->id,
            'type' => 'annulation',
            'canal' => 'in_app',
        ]);
    }

    public function test_bloquer_deux_fois_la_meme_date_est_refuse(): void
    {
        Mail::fake();
        $medecin = $this->creerMedecin();
        $date = Carbon::now()->addWeek()->toDateString();

        $this->actingAs($medecin->user, 'sanctum')
            ->postJson('/api/medecin/blocages', ['date' => $date])
            ->assertCreated();

        $this->actingAs($medecin->user, 'sanctum')
            ->postJson('/api/medecin/blocages', ['date' => $date])
            ->assertStatus(422)
            ->assertJsonValidationErrors('date');
    }

    public function test_bloquer_une_date_passee_est_refuse(): void
    {
        $medecin = $this->creerMedecin();

        $this->actingAs($medecin->user, 'sanctum')
            ->postJson('/api/medecin/blocages', ['date' => Carbon::now()->subDay()->toDateString()])
            ->assertStatus(422)
            ->assertJsonValidationErrors('date');
    }

    public function test_le_medecin_peut_lister_ses_blocages(): void
    {
        $medecin = $this->creerMedecin();
        Blocage::create(['medecin_id' => $medecin->id, 'date' => Carbon::now()->addWeek(), 'motif' => 'Congés']);

        $this->actingAs($medecin->user, 'sanctum')
            ->getJson('/api/medecin/blocages')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_le_medecin_peut_supprimer_son_propre_blocage(): void
    {
        $medecin = $this->creerMedecin();
        $blocage = Blocage::create(['medecin_id' => $medecin->id, 'date' => Carbon::now()->addWeek()]);

        $this->actingAs($medecin->user, 'sanctum')
            ->deleteJson("/api/medecin/blocages/{$blocage->id}")
            ->assertOk();

        $this->assertDatabaseMissing('blocages', ['id' => $blocage->id]);
    }

    public function test_un_medecin_ne_peut_pas_supprimer_le_blocage_dun_confrere(): void
    {
        $medecinA = $this->creerMedecin();
        $medecinB = $this->creerMedecin();
        $blocage = Blocage::create(['medecin_id' => $medecinB->id, 'date' => Carbon::now()->addWeek()]);

        $this->actingAs($medecinA->user, 'sanctum')
            ->deleteJson("/api/medecin/blocages/{$blocage->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('blocages', ['id' => $blocage->id]);
    }
}
