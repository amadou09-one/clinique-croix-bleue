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

class RendezVousTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Médecin avec une disponibilité lun 08h-12h (créneaux de 30 min),
     * plus la prochaine occurrence de ce jour de la semaine (toujours dans le futur).
     */
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

    public function test_un_creneau_deja_pris_devient_indisponible(): void
    {
        [$medecin, $jour] = $this->creerMedecinAvecDisponibilite();
        $patient = User::factory()->create(['role' => 'patient']);

        RendezVous::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'date_heure' => $jour->copy()->setTime(9, 0),
            'statut' => 'confirme',
            'cree_par' => $patient->id,
        ]);

        $response = $this->actingAs($patient, 'sanctum')
            ->getJson("/api/medecins/{$medecin->id}/creneaux?date=".$jour->toDateString());

        $response->assertOk();

        $creneaux = collect($response->json('data'))->keyBy('heure');
        $this->assertFalse($creneaux['09:00']['disponible']);
        $this->assertTrue($creneaux['09:30']['disponible']);
        $this->assertTrue($creneaux['08:00']['disponible']);
    }

    public function test_un_rdv_annule_ne_bloque_pas_le_creneau(): void
    {
        [$medecin, $jour] = $this->creerMedecinAvecDisponibilite();
        $patient = User::factory()->create(['role' => 'patient']);

        RendezVous::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'date_heure' => $jour->copy()->setTime(9, 0),
            'statut' => 'annule',
            'cree_par' => $patient->id,
        ]);

        $response = $this->actingAs($patient, 'sanctum')
            ->getJson("/api/medecins/{$medecin->id}/creneaux?date=".$jour->toDateString());

        $this->assertTrue(collect($response->json('data'))->keyBy('heure')['09:00']['disponible']);
    }

    public function test_un_patient_peut_reserver_un_creneau_libre(): void
    {
        [$medecin, $jour] = $this->creerMedecinAvecDisponibilite();
        $patient = User::factory()->create(['role' => 'patient']);

        $response = $this->actingAs($patient, 'sanctum')
            ->postJson('/api/rendez-vous', [
                'medecin_id' => $medecin->id,
                'date_heure' => $jour->copy()->setTime(9, 0)->toDateTimeString(),
                'motif' => 'Consultation',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('rendez_vous', [
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'statut' => 'en_attente',
        ]);
    }

    public function test_la_double_reservation_du_meme_creneau_est_refusee(): void
    {
        [$medecin, $jour] = $this->creerMedecinAvecDisponibilite();
        $patient1 = User::factory()->create(['role' => 'patient']);
        $patient2 = User::factory()->create(['role' => 'patient']);
        $dateHeure = $jour->copy()->setTime(9, 0)->toDateTimeString();

        $this->actingAs($patient1, 'sanctum')
            ->postJson('/api/rendez-vous', ['medecin_id' => $medecin->id, 'date_heure' => $dateHeure])
            ->assertCreated();

        $this->actingAs($patient2, 'sanctum')
            ->postJson('/api/rendez-vous', ['medecin_id' => $medecin->id, 'date_heure' => $dateHeure])
            ->assertStatus(422);

        $this->assertSame(1, RendezVous::where('medecin_id', $medecin->id)
            ->where('date_heure', $dateHeure)
            ->where('statut', '!=', 'annule')
            ->count());
    }

    public function test_un_patient_ne_peut_pas_avoir_deux_rdv_actifs_le_meme_jour_avec_le_meme_medecin(): void
    {
        [$medecin, $jour] = $this->creerMedecinAvecDisponibilite();
        $patient = User::factory()->create(['role' => 'patient']);

        $this->actingAs($patient, 'sanctum')
            ->postJson('/api/rendez-vous', [
                'medecin_id' => $medecin->id,
                'date_heure' => $jour->copy()->setTime(9, 0)->toDateTimeString(),
            ])->assertCreated();

        $this->actingAs($patient, 'sanctum')
            ->postJson('/api/rendez-vous', [
                'medecin_id' => $medecin->id,
                'date_heure' => $jour->copy()->setTime(10, 0)->toDateTimeString(),
            ])->assertStatus(422);
    }

    public function test_la_reservation_hors_disponibilites_est_refusee(): void
    {
        [$medecin, $jour] = $this->creerMedecinAvecDisponibilite();
        $patient = User::factory()->create(['role' => 'patient']);

        $this->actingAs($patient, 'sanctum')
            ->postJson('/api/rendez-vous', [
                'medecin_id' => $medecin->id,
                'date_heure' => $jour->copy()->setTime(20, 0)->toDateTimeString(),
            ])->assertStatus(422);
    }

    public function test_la_reservation_dans_le_passe_est_refusee(): void
    {
        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);

        $this->actingAs($patient, 'sanctum')
            ->postJson('/api/rendez-vous', [
                'medecin_id' => $medecin->id,
                'date_heure' => Carbon::now()->subDay()->toDateTimeString(),
            ])->assertStatus(422);
    }

    public function test_annulation_a_moins_de_6h_est_refusee(): void
    {
        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);

        $rdv = RendezVous::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'date_heure' => Carbon::now()->addHours(3),
            'statut' => 'confirme',
            'cree_par' => $patient->id,
        ]);

        $this->actingAs($patient, 'sanctum')
            ->patchJson("/api/rendez-vous/{$rdv->id}/annuler")
            ->assertStatus(422);

        $this->assertDatabaseHas('rendez_vous', ['id' => $rdv->id, 'statut' => 'confirme']);
    }

    public function test_annulation_a_plus_de_6h_est_acceptee(): void
    {
        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);

        $rdv = RendezVous::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'date_heure' => Carbon::now()->addDay(),
            'statut' => 'confirme',
            'cree_par' => $patient->id,
        ]);

        $this->actingAs($patient, 'sanctum')
            ->patchJson("/api/rendez-vous/{$rdv->id}/annuler")
            ->assertOk();

        $this->assertDatabaseHas('rendez_vous', ['id' => $rdv->id, 'statut' => 'annule']);
    }

    public function test_un_patient_ne_peut_pas_annuler_le_rdv_dun_autre_patient(): void
    {
        $medecin = $this->creerMedecin();
        $proprietaire = User::factory()->create(['role' => 'patient']);
        $autrePatient = User::factory()->create(['role' => 'patient']);

        $rdv = RendezVous::create([
            'patient_id' => $proprietaire->id,
            'medecin_id' => $medecin->id,
            'date_heure' => Carbon::now()->addDay(),
            'statut' => 'confirme',
            'cree_par' => $proprietaire->id,
        ]);

        $this->actingAs($autrePatient, 'sanctum')
            ->patchJson("/api/rendez-vous/{$rdv->id}/annuler")
            ->assertStatus(403);

        $this->assertDatabaseHas('rendez_vous', ['id' => $rdv->id, 'statut' => 'confirme']);
    }

    public function test_mes_rendez_vous_separe_a_venir_et_passes(): void
    {
        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);

        RendezVous::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'date_heure' => Carbon::now()->addDays(2),
            'statut' => 'confirme',
            'cree_par' => $patient->id,
        ]);

        RendezVous::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'date_heure' => Carbon::now()->subWeek(),
            'statut' => 'honore',
            'cree_par' => $patient->id,
        ]);

        $response = $this->actingAs($patient, 'sanctum')->getJson('/api/mes-rendez-vous');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.a_venir'));
        $this->assertCount(1, $response->json('data.passes'));
    }

    public function test_un_visiteur_non_authentifie_ne_peut_pas_reserver(): void
    {
        $medecin = $this->creerMedecin();

        $this->postJson('/api/rendez-vous', [
            'medecin_id' => $medecin->id,
            'date_heure' => Carbon::now()->addDay()->toDateTimeString(),
        ])->assertStatus(401);
    }
}
