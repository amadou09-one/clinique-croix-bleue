<?php

namespace Tests\Feature;

use App\Models\Medecin;
use App\Models\Specialite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedecinProfilTest extends TestCase
{
    use RefreshDatabase;

    private function creerMedecin(): Medecin
    {
        $userMedecin = User::factory()->create(['role' => 'medecin']);
        $specialite = Specialite::create(['nom' => 'Cardiologie '.uniqid()]);

        return Medecin::create([
            'user_id' => $userMedecin->id,
            'specialite_id' => $specialite->id,
            'titre' => 'Doctorat UCAD',
            'annees_experience' => 5,
            'bio' => 'Ancienne bio.',
        ]);
    }

    public function test_le_medecin_peut_modifier_sa_biographie(): void
    {
        $medecin = $this->creerMedecin();

        $this->actingAs($medecin->user, 'sanctum')
            ->putJson('/api/medecin/profil', ['bio' => 'Nouvelle biographie.'])
            ->assertOk()
            ->assertJsonPath('data.bio', 'Nouvelle biographie.');

        $this->assertDatabaseHas('medecins', ['id' => $medecin->id, 'bio' => 'Nouvelle biographie.']);
    }

    public function test_la_specialite_et_le_titre_ne_sont_pas_modifiables_via_cet_endpoint(): void
    {
        $medecin = $this->creerMedecin();
        $autreSpecialite = Specialite::create(['nom' => 'Pédiatrie '.uniqid()]);

        $this->actingAs($medecin->user, 'sanctum')
            ->putJson('/api/medecin/profil', [
                'bio' => 'Bio mise à jour.',
                'specialite_id' => $autreSpecialite->id,
                'titre' => 'Autre titre',
            ])
            ->assertOk();

        $this->assertDatabaseHas('medecins', [
            'id' => $medecin->id,
            'specialite_id' => $medecin->specialite_id,
            'titre' => 'Doctorat UCAD',
        ]);
    }

    public function test_un_patient_ne_peut_pas_acceder_a_lendpoint_profil_medecin(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);

        $this->actingAs($patient, 'sanctum')
            ->putJson('/api/medecin/profil', ['bio' => 'Test'])
            ->assertStatus(403);
    }
}
