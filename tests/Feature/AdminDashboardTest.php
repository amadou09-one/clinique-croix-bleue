<?php

namespace Tests\Feature;

use App\Models\Medecin;
use App\Models\MessageContact;
use App\Models\RendezVous;
use App\Models\Specialite;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_non_admin_ne_peut_pas_acceder_aux_stats(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);

        $this->actingAs($patient, 'sanctum')
            ->getJson('/api/admin/stats')
            ->assertStatus(403);
    }

    public function test_les_stats_sont_calculees_correctement(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        User::factory()->count(3)->create(['role' => 'patient']);
        User::factory()->create(['role' => 'medecin', 'est_actif' => true]);
        User::factory()->create(['role' => 'medecin', 'est_actif' => false]);

        $specialite = Specialite::create(['nom' => 'Cardiologie '.uniqid()]);
        $userMedecin = User::factory()->create(['role' => 'medecin', 'est_actif' => true]);
        $medecin = Medecin::create(['user_id' => $userMedecin->id, 'specialite_id' => $specialite->id, 'annees_experience' => 5]);
        $patient = User::factory()->create(['role' => 'patient']);

        RendezVous::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'date_heure' => Carbon::now()->startOfMonth()->addDays(2),
            'statut' => 'confirme',
            'cree_par' => $patient->id,
        ]);
        RendezVous::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'date_heure' => Carbon::now()->startOfMonth()->addDays(3),
            'statut' => 'annule',
            'cree_par' => $patient->id,
        ]);

        MessageContact::create(['nom' => 'Test', 'email' => 't@t.com', 'message' => 'Bonjour', 'traite' => false]);
        MessageContact::create(['nom' => 'Test2', 'email' => 't2@t.com', 'message' => 'Bonjour2', 'traite' => true]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/stats')
            ->assertOk();

        $data = $response->json('data');

        $this->assertSame(4, $data['total_patients']); // 3 + le patient créé pour le RDV
        $this->assertSame(2, $data['medecins_actifs']);
        $this->assertSame(1, $data['rdv_ce_mois']); // le RDV annulé ne compte pas
        $this->assertSame(1, $data['messages_non_traites']);
        $this->assertCount(6, $data['evolution_rdv_6_mois']);
        $this->assertArrayHasKey('mois', $data['evolution_rdv_6_mois'][0]);
        $this->assertArrayHasKey('total', $data['evolution_rdv_6_mois'][0]);
        $this->assertNotEmpty($data['repartition_specialites']);
        $this->assertSame($specialite->nom, $data['repartition_specialites'][0]['nom']);
        $this->assertSame(1, $data['repartition_specialites'][0]['medecins']);
        $this->assertSame(1, $data['repartition_specialites'][0]['rdv_mois']);
    }
}
