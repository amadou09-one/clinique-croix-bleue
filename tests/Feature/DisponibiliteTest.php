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

class DisponibiliteTest extends TestCase
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

    private function creerDisponibilite(Medecin $medecin, int $jourSemaine, string $debut, string $fin): Disponibilite
    {
        return Disponibilite::create([
            'medecin_id' => $medecin->id,
            'jour_semaine' => $jourSemaine,
            'heure_debut' => $debut,
            'heure_fin' => $fin,
            'duree_creneau_min' => 30,
        ]);
    }

    /** Le prochain lundi (jour_semaine=1) à partir de maintenant. */
    private function prochainLundi(): Carbon
    {
        return Carbon::now()->next(Carbon::MONDAY)->startOfDay();
    }

    public function test_lindex_retourne_les_7_jours_avec_les_plages_du_medecin_connecte(): void
    {
        $medecin = $this->creerMedecin();
        $this->creerDisponibilite($medecin, 1, '08:00:00', '12:00:00');

        $response = $this->actingAs($medecin->user, 'sanctum')
            ->getJson('/api/medecin/disponibilites')
            ->assertOk();

        $jours = $response->json('data');
        $this->assertCount(7, $jours);
        $this->assertSame(1, $jours[0]['jour_semaine']);
        $this->assertCount(1, $jours[0]['plages']);
        $this->assertSame('08:00', $jours[0]['plages'][0]['heure_debut']);
        $this->assertCount(0, $jours[1]['plages']);
    }

    public function test_modifier_un_jour_sans_rdv_futur_fonctionne(): void
    {
        $medecin = $this->creerMedecin();
        $this->creerDisponibilite($medecin, 1, '08:00:00', '12:00:00');

        $this->actingAs($medecin->user, 'sanctum')
            ->putJson('/api/medecin/disponibilites', [
                'jour_semaine' => 1,
                'plages' => [['heure_debut' => '09:00', 'heure_fin' => '13:00']],
                'duree_creneau_min' => 20,
            ])
            ->assertOk();

        $this->assertDatabaseHas('disponibilites', [
            'medecin_id' => $medecin->id,
            'jour_semaine' => 1,
            'heure_debut' => '09:00',
            'heure_fin' => '13:00',
            'duree_creneau_min' => 20,
        ]);
        $this->assertSame(1, Disponibilite::where('medecin_id', $medecin->id)->where('jour_semaine', 1)->count());
    }

    public function test_modifier_un_jour_avec_un_rdv_futur_sur_un_creneau_supprime_est_refuse(): void
    {
        $medecin = $this->creerMedecin();
        $this->creerDisponibilite($medecin, 1, '08:00:00', '12:00:00');
        $patient = User::factory()->create(['role' => 'patient']);

        $lundi = $this->prochainLundi();
        $rdv = RendezVous::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'date_heure' => $lundi->copy()->setTime(10, 0),
            'statut' => 'confirme',
            'cree_par' => $patient->id,
        ]);

        $this->actingAs($medecin->user, 'sanctum')
            ->putJson('/api/medecin/disponibilites', [
                'jour_semaine' => 1,
                'plages' => [['heure_debut' => '08:00', 'heure_fin' => '10:00']],
                'duree_creneau_min' => 30,
            ])
            ->assertStatus(422)
            ->assertJsonPath('data.rendez_vous_concernes.0.id', $rdv->id);

        // Rien n'a été modifié : ni le RDV, ni les disponibilités.
        $this->assertSame('confirme', $rdv->fresh()->statut);
        $this->assertDatabaseHas('disponibilites', ['medecin_id' => $medecin->id, 'jour_semaine' => 1, 'heure_debut' => '08:00:00']);
    }

    public function test_un_rdv_annule_ne_bloque_pas_la_modification(): void
    {
        $medecin = $this->creerMedecin();
        $this->creerDisponibilite($medecin, 1, '08:00:00', '12:00:00');
        $patient = User::factory()->create(['role' => 'patient']);

        $lundi = $this->prochainLundi();
        RendezVous::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'date_heure' => $lundi->copy()->setTime(10, 0),
            'statut' => 'annule',
            'cree_par' => $patient->id,
        ]);

        $this->actingAs($medecin->user, 'sanctum')
            ->putJson('/api/medecin/disponibilites', [
                'jour_semaine' => 1,
                'plages' => [['heure_debut' => '08:00', 'heure_fin' => '10:00']],
                'duree_creneau_min' => 30,
            ])
            ->assertOk();
    }

    public function test_un_rdv_passe_ne_bloque_pas_la_modification(): void
    {
        $medecin = $this->creerMedecin();
        $this->creerDisponibilite($medecin, 1, '08:00:00', '12:00:00');
        $patient = User::factory()->create(['role' => 'patient']);

        $lundiDernier = Carbon::now()->previous(Carbon::MONDAY)->setTime(10, 0);
        RendezVous::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'date_heure' => $lundiDernier,
            'statut' => 'honore',
            'cree_par' => $patient->id,
        ]);

        $this->actingAs($medecin->user, 'sanctum')
            ->putJson('/api/medecin/disponibilites', [
                'jour_semaine' => 1,
                'plages' => [['heure_debut' => '08:00', 'heure_fin' => '10:00']],
                'duree_creneau_min' => 30,
            ])
            ->assertOk();
    }

    public function test_les_plages_qui_se_chevauchent_sont_refusees(): void
    {
        $medecin = $this->creerMedecin();

        $this->actingAs($medecin->user, 'sanctum')
            ->putJson('/api/medecin/disponibilites', [
                'jour_semaine' => 1,
                'plages' => [
                    ['heure_debut' => '08:00', 'heure_fin' => '12:00'],
                    ['heure_debut' => '11:00', 'heure_fin' => '14:00'],
                ],
                'duree_creneau_min' => 30,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('plages');
    }

    public function test_un_medecin_ne_peut_pas_modifier_les_disponibilites_dun_confrere(): void
    {
        $medecinA = $this->creerMedecin();
        $medecinB = $this->creerMedecin();
        $this->creerDisponibilite($medecinB, 1, '08:00:00', '12:00:00');

        $this->actingAs($medecinA->user, 'sanctum')
            ->putJson('/api/medecin/disponibilites', [
                'jour_semaine' => 1,
                'plages' => [['heure_debut' => '09:00', 'heure_fin' => '10:00']],
                'duree_creneau_min' => 30,
            ])
            ->assertOk();

        // Les dispos du médecin B ne doivent pas avoir bougé.
        $this->assertDatabaseHas('disponibilites', ['medecin_id' => $medecinB->id, 'heure_debut' => '08:00:00']);
    }
}
