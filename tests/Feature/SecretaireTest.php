<?php

namespace Tests\Feature;

use App\Mail\RdvDecisionPatient;
use App\Models\Blocage;
use App\Models\Disponibilite;
use App\Models\Medecin;
use App\Models\RendezVous;
use App\Models\Specialite;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SecretaireTest extends TestCase
{
    use RefreshDatabase;

    private function creerSecretaire(): User
    {
        return User::factory()->create(['role' => 'secretaire']);
    }

    private function creerMedecinAvecDisponibilite(int $jourSemaine): Medecin
    {
        $userMedecin = User::factory()->create(['role' => 'medecin']);
        $specialite = Specialite::create(['nom' => 'Cardiologie '.uniqid()]);

        $medecin = Medecin::create([
            'user_id' => $userMedecin->id,
            'specialite_id' => $specialite->id,
            'annees_experience' => 5,
        ]);

        Disponibilite::create([
            'medecin_id' => $medecin->id,
            'jour_semaine' => $jourSemaine,
            'heure_debut' => '08:00',
            'heure_fin' => '12:00',
            'duree_creneau_min' => 30,
        ]);

        return $medecin;
    }

    private function creerRdv(Medecin $medecin, Carbon $dateHeure, string $statut = 'confirme'): RendezVous
    {
        $patient = User::factory()->create(['role' => 'patient']);

        return RendezVous::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'date_heure' => $dateHeure,
            'statut' => $statut,
            'cree_par' => $patient->id,
        ]);
    }

    public function test_le_planning_retourne_tous_les_medecins_actifs_du_jour(): void
    {
        Carbon::setTestNow(Carbon::now()->next(Carbon::MONDAY)->setTime(9, 0));

        $secretaire = $this->creerSecretaire();
        $medecinA = $this->creerMedecinAvecDisponibilite(1);
        $medecinB = $this->creerMedecinAvecDisponibilite(1);
        $rdv = $this->creerRdv($medecinA, Carbon::today()->setTime(8, 0));

        $response = $this->actingAs($secretaire, 'sanctum')
            ->getJson('/api/secretaire/planning?date='.Carbon::today()->toDateString())
            ->assertOk();

        $idsMedecins = collect($response->json('data.medecins'))->pluck('id')->all();
        $this->assertContains($medecinA->id, $idsMedecins);
        $this->assertContains($medecinB->id, $idsMedecins);

        $ligne0800 = collect($response->json('data.planning'))->firstWhere('heure', '08:00');
        $entreeMedecinA = collect($ligne0800['medecins'])->firstWhere('medecin_id', $medecinA->id);
        $this->assertSame('rdv', $entreeMedecinA['statut']);
        $this->assertSame($rdv->id, $entreeMedecinA['rdv']['id']);

        $entreeMedecinB = collect($ligne0800['medecins'])->firstWhere('medecin_id', $medecinB->id);
        $this->assertSame('libre', $entreeMedecinB['statut']);

        Carbon::setTestNow();
    }

    public function test_le_planning_marque_ferme_un_medecin_sans_disponibilite_ce_jour(): void
    {
        Carbon::setTestNow(Carbon::now()->next(Carbon::MONDAY)->setTime(9, 0));

        $secretaire = $this->creerSecretaire();
        $this->creerMedecinAvecDisponibilite(1);
        $medecinFerme = $this->creerMedecinAvecDisponibilite(2); // mardi seulement

        $response = $this->actingAs($secretaire, 'sanctum')
            ->getJson('/api/secretaire/planning?date='.Carbon::today()->toDateString())
            ->assertOk();

        foreach ($response->json('data.planning') as $ligne) {
            $entree = collect($ligne['medecins'])->firstWhere('medecin_id', $medecinFerme->id);
            $this->assertSame('ferme', $entree['statut']);
        }

        Carbon::setTestNow();
    }

    public function test_le_planning_ne_duplique_aucun_rdv_en_changeant_de_jour(): void
    {
        Carbon::setTestNow(Carbon::now()->next(Carbon::MONDAY)->setTime(9, 0));

        $secretaire = $this->creerSecretaire();
        $medecin = $this->creerMedecinAvecDisponibilite(1);
        $rdvLundi = $this->creerRdv($medecin, Carbon::today()->setTime(8, 0));
        $rdvMardi = $this->creerRdv($medecin, Carbon::today()->addDay()->setTime(8, 0));

        $planningLundi = $this->actingAs($secretaire, 'sanctum')
            ->getJson('/api/secretaire/planning?date='.Carbon::today()->toDateString())
            ->json('data.planning');

        $rdvIdsLundi = collect($planningLundi)
            ->flatMap(fn ($l) => collect($l['medecins'])->pluck('rdv.id'))
            ->filter()
            ->values()
            ->all();

        $this->assertSame([$rdvLundi->id], $rdvIdsLundi);
        $this->assertNotContains($rdvMardi->id, $rdvIdsLundi);

        Carbon::setTestNow();
    }

    public function test_confirmer_un_rdv_en_attente_envoie_le_mail_de_decision(): void
    {
        Mail::fake();
        $secretaire = $this->creerSecretaire();
        $medecin = $this->creerMedecinAvecDisponibilite(1);
        $rdv = $this->creerRdv($medecin, Carbon::now()->addDay(), 'en_attente');

        $this->actingAs($secretaire, 'sanctum')
            ->patchJson("/api/rendez-vous/{$rdv->id}/confirmer")
            ->assertOk()
            ->assertJsonPath('data.statut', 'confirme');

        $this->assertSame('confirme', $rdv->fresh()->statut);

        Mail::assertSent(RdvDecisionPatient::class, function (RdvDecisionPatient $mail) use ($rdv) {
            return $mail->rendezVous->id === $rdv->id && $mail->accepte === true;
        });

        $this->assertDatabaseHas('notifications', [
            'rendez_vous_id' => $rdv->id,
            'type' => 'confirmation',
        ]);
    }

    public function test_confirmer_un_rdv_deja_confirme_est_refuse(): void
    {
        Mail::fake();
        $secretaire = $this->creerSecretaire();
        $medecin = $this->creerMedecinAvecDisponibilite(1);
        $rdv = $this->creerRdv($medecin, Carbon::now()->addDay(), 'confirme');

        $this->actingAs($secretaire, 'sanctum')
            ->patchJson("/api/rendez-vous/{$rdv->id}/confirmer")
            ->assertStatus(403);

        $this->assertSame('confirme', $rdv->fresh()->statut);
        Mail::assertNothingSent();
    }

    public function test_les_statistiques_sont_calculees_correctement(): void
    {
        Carbon::setTestNow(Carbon::now()->next(Carbon::MONDAY)->setTime(12, 0));

        $secretaire = $this->creerSecretaire();
        $medecin = $this->creerMedecinAvecDisponibilite(1);
        $this->creerRdv($medecin, Carbon::today()->setTime(9, 0), 'confirme');
        $this->creerRdv($medecin, Carbon::today()->setTime(10, 0), 'en_attente');

        $response = $this->actingAs($secretaire, 'sanctum')
            ->getJson('/api/secretaire/stats')
            ->assertOk();

        $this->assertSame(2, $response->json('data.rdv_aujourdhui'));
        $this->assertSame(1, $response->json('data.en_attente_confirmation'));
        $this->assertSame(1, $response->json('data.medecins_total_actifs'));

        $presents = collect($response->json('data.medecins_presents_aujourdhui'));
        $this->assertTrue($presents->contains(fn ($m) => $m['nom'] === 'Dr '.$medecin->user->nom));

        Carbon::setTestNow();
    }

    public function test_un_medecin_bloque_najourdhui_napparait_pas_parmi_les_presents(): void
    {
        Carbon::setTestNow(Carbon::now()->next(Carbon::MONDAY)->setTime(9, 0));

        $secretaire = $this->creerSecretaire();
        $medecin = $this->creerMedecinAvecDisponibilite(1);
        Blocage::create(['medecin_id' => $medecin->id, 'date' => Carbon::today()]);

        $response = $this->actingAs($secretaire, 'sanctum')
            ->getJson('/api/secretaire/stats')
            ->assertOk();

        $presents = collect($response->json('data.medecins_presents_aujourdhui'));
        $this->assertFalse($presents->contains(fn ($m) => $m['nom'] === 'Dr '.$medecin->user->nom));

        Carbon::setTestNow();
    }

    public function test_un_medecin_ne_peut_pas_acceder_aux_routes_secretaire(): void
    {
        $userMedecin = User::factory()->create(['role' => 'medecin']);

        $this->actingAs($userMedecin, 'sanctum')
            ->getJson('/api/secretaire/stats')
            ->assertStatus(403);
    }

    public function test_un_secretaire_ne_peut_pas_acceder_aux_routes_reservees_au_medecin(): void
    {
        $secretaire = $this->creerSecretaire();

        $this->actingAs($secretaire, 'sanctum')
            ->getJson('/api/medecin/agenda')
            ->assertStatus(403);

        $this->actingAs($secretaire, 'sanctum')
            ->getJson('/api/medecin/patients')
            ->assertStatus(403);

        $this->actingAs($secretaire, 'sanctum')
            ->getJson('/api/medecin/disponibilites')
            ->assertStatus(403);
    }

    public function test_un_patient_ne_peut_pas_acceder_aux_routes_secretaire(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);

        $this->actingAs($patient, 'sanctum')
            ->getJson('/api/secretaire/planning')
            ->assertStatus(403);
    }
}
