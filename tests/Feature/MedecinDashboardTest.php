<?php

namespace Tests\Feature;

use App\Models\Disponibilite;
use App\Models\Medecin;
use App\Models\RendezVous;
use App\Models\Specialite;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedecinDashboardTest extends TestCase
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

    private function creerRendezVous(Medecin $medecin, Carbon $dateHeure, string $statut = 'confirme'): RendezVous
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

    public function test_lagenda_ne_retourne_que_les_rdv_du_medecin_connecte(): void
    {
        $medecinA = $this->creerMedecin();
        $medecinB = $this->creerMedecin();
        $aujourdhui = Carbon::now()->setTime(9, 0);

        $rdvA = $this->creerRendezVous($medecinA, $aujourdhui);
        $this->creerRendezVous($medecinB, $aujourdhui->copy()->setTime(10, 0));

        $response = $this->actingAs($medecinA->user, 'sanctum')
            ->getJson('/api/medecin/agenda?date='.$aujourdhui->toDateString())
            ->assertOk();

        $ids = array_column($response->json('data'), 'id');
        $this->assertSame([$rdvA->id], $ids);
    }

    public function test_lagenda_par_defaut_retourne_aujourdhui(): void
    {
        $medecin = $this->creerMedecin();
        $rdvAujourdhui = $this->creerRendezVous($medecin, Carbon::now()->setTime(9, 0));
        $this->creerRendezVous($medecin, Carbon::now()->addDay()->setTime(9, 0));

        $response = $this->actingAs($medecin->user, 'sanctum')
            ->getJson('/api/medecin/agenda')
            ->assertOk();

        $ids = array_column($response->json('data'), 'id');
        $this->assertSame([$rdvAujourdhui->id], $ids);
    }

    public function test_lagenda_inclut_les_informations_du_patient(): void
    {
        $medecin = $this->creerMedecin();
        $rdv = $this->creerRendezVous($medecin, Carbon::now()->setTime(9, 0));

        $this->actingAs($medecin->user, 'sanctum')
            ->getJson('/api/medecin/agenda')
            ->assertOk()
            ->assertJsonPath('data.0.patient.prenom', $rdv->patient->prenom)
            ->assertJsonPath('data.0.patient.nom', $rdv->patient->nom);
    }

    public function test_un_secretaire_ne_peut_pas_acceder_a_lagenda_medecin(): void
    {
        $secretaire = User::factory()->create(['role' => 'secretaire']);

        $this->actingAs($secretaire, 'sanctum')
            ->getJson('/api/medecin/agenda')
            ->assertStatus(403);
    }

    public function test_les_statistiques_du_medecin_sont_calculees_correctement(): void
    {
        $medecin = $this->creerMedecin();
        $this->creerRendezVous($medecin, Carbon::now()->subHours(2), 'honore');
        $this->creerRendezVous($medecin, Carbon::now()->addHours(2), 'confirme');

        $response = $this->actingAs($medecin->user, 'sanctum')
            ->getJson('/api/medecin/stats')
            ->assertOk();

        $this->assertSame(2, $response->json('data.rdv_aujourdhui'));
        $this->assertSame(1, $response->json('data.patients_vus_aujourdhui'));
        $this->assertNotNull($response->json('data.prochain_patient'));
    }

    public function test_le_medecin_peut_marquer_son_propre_rdv_honore(): void
    {
        $medecin = $this->creerMedecin();
        $rdv = $this->creerRendezVous($medecin, Carbon::now()->subHour());

        $this->actingAs($medecin->user, 'sanctum')
            ->patchJson("/api/rendez-vous/{$rdv->id}/statut", ['statut' => 'honore'])
            ->assertOk()
            ->assertJsonPath('data.statut', 'honore');

        $this->assertSame('honore', $rdv->fresh()->statut);
    }

    public function test_un_medecin_ne_peut_pas_changer_le_statut_du_rdv_dun_confrere(): void
    {
        $medecinA = $this->creerMedecin();
        $medecinB = $this->creerMedecin();
        $rdv = $this->creerRendezVous($medecinB, Carbon::now()->subHour());

        $this->actingAs($medecinA->user, 'sanctum')
            ->patchJson("/api/rendez-vous/{$rdv->id}/statut", ['statut' => 'honore'])
            ->assertStatus(403);

        $this->assertSame('confirme', $rdv->fresh()->statut);
    }

    public function test_le_changement_de_statut_est_refuse_si_le_rdv_est_deja_dans_un_etat_final(): void
    {
        $medecin = $this->creerMedecin();
        $rdv = $this->creerRendezVous($medecin, Carbon::now()->subHour(), 'annule');

        $this->actingAs($medecin->user, 'sanctum')
            ->patchJson("/api/rendez-vous/{$rdv->id}/statut", ['statut' => 'honore'])
            ->assertStatus(422);

        $this->assertSame('annule', $rdv->fresh()->statut);
    }

    public function test_le_changement_de_statut_refuse_une_valeur_non_autorisee(): void
    {
        $medecin = $this->creerMedecin();
        $rdv = $this->creerRendezVous($medecin, Carbon::now()->subHour());

        $this->actingAs($medecin->user, 'sanctum')
            ->patchJson("/api/rendez-vous/{$rdv->id}/statut", ['statut' => 'confirme'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('statut');
    }
}
