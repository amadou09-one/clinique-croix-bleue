<?php

namespace Tests\Feature;

use App\Models\Medecin;
use App\Models\RendezVous;
use App\Models\Specialite;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedecinConsultationTest extends TestCase
{
    use RefreshDatabase;

    private function creerMedecin(): Medecin
    {
        $user = User::factory()->create(['role' => 'medecin']);
        $specialite = Specialite::create(['nom' => 'Cardiologie '.uniqid()]);

        return Medecin::create(['user_id' => $user->id, 'specialite_id' => $specialite->id, 'annees_experience' => 5]);
    }

    private function creerRendezVous(Medecin $medecin, ?User $patient = null, string $statut = 'honore', ?Carbon $dateHeure = null): RendezVous
    {
        $patient ??= User::factory()->create(['role' => 'patient']);

        return RendezVous::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'date_heure' => $dateHeure ?? Carbon::now()->subDay(),
            'statut' => $statut,
            'cree_par' => $patient->id,
        ]);
    }

    public function test_un_medecin_peut_creer_une_consultation_sur_son_rdv_honore(): void
    {
        $medecin = $this->creerMedecin();
        $rdv = $this->creerRendezVous($medecin);

        $this->actingAs($medecin->user, 'sanctum')
            ->postJson("/api/medecin/rendez-vous/{$rdv->id}/consultation", [
                'diagnostic' => 'Hypertension légère',
                'observations' => 'Tension à surveiller, retour dans 1 mois.',
            ])
            ->assertOk()
            ->assertJsonPath('data.diagnostic', 'Hypertension légère');

        $this->assertDatabaseHas('consultations', [
            'rendez_vous_id' => $rdv->id,
            'medecin_id' => $medecin->id,
            'patient_id' => $rdv->patient_id,
            'diagnostic' => 'Hypertension légère',
        ]);
    }

    public function test_la_consultation_est_mise_a_jour_si_elle_existe_deja(): void
    {
        $medecin = $this->creerMedecin();
        $rdv = $this->creerRendezVous($medecin);

        $this->actingAs($medecin->user, 'sanctum')
            ->postJson("/api/medecin/rendez-vous/{$rdv->id}/consultation", ['diagnostic' => 'Premier diagnostic'])
            ->assertOk();

        $this->actingAs($medecin->user, 'sanctum')
            ->postJson("/api/medecin/rendez-vous/{$rdv->id}/consultation", ['diagnostic' => 'Diagnostic corrigé'])
            ->assertOk()
            ->assertJsonPath('data.diagnostic', 'Diagnostic corrigé');

        $this->assertSame(1, \App\Models\Consultation::where('rendez_vous_id', $rdv->id)->count());
        $this->assertDatabaseHas('consultations', ['rendez_vous_id' => $rdv->id, 'diagnostic' => 'Diagnostic corrigé']);
    }

    public function test_un_medecin_ne_peut_pas_creer_de_consultation_sur_le_rdv_dun_autre_medecin(): void
    {
        $medecin = $this->creerMedecin();
        $autreMedecin = $this->creerMedecin();
        $rdv = $this->creerRendezVous($medecin);

        $this->actingAs($autreMedecin->user, 'sanctum')
            ->postJson("/api/medecin/rendez-vous/{$rdv->id}/consultation", ['diagnostic' => 'Tentative non autorisée'])
            ->assertStatus(403);

        $this->assertDatabaseMissing('consultations', ['rendez_vous_id' => $rdv->id]);
    }

    public function test_impossible_de_creer_une_consultation_sur_un_rdv_futur_non_honore(): void
    {
        $medecin = $this->creerMedecin();
        $rdv = $this->creerRendezVous($medecin, statut: 'confirme', dateHeure: Carbon::now()->addDays(3));

        $this->actingAs($medecin->user, 'sanctum')
            ->postJson("/api/medecin/rendez-vous/{$rdv->id}/consultation", ['diagnostic' => 'Trop tôt'])
            ->assertStatus(422);

        $this->assertDatabaseMissing('consultations', ['rendez_vous_id' => $rdv->id]);
    }

    public function test_impossible_de_creer_une_consultation_sur_un_rdv_absent(): void
    {
        $medecin = $this->creerMedecin();
        $rdv = $this->creerRendezVous($medecin, statut: 'absent');

        $this->actingAs($medecin->user, 'sanctum')
            ->postJson("/api/medecin/rendez-vous/{$rdv->id}/consultation", ['diagnostic' => 'Patient absent'])
            ->assertStatus(422);
    }

    public function test_un_non_medecin_recoit_403(): void
    {
        $medecin = $this->creerMedecin();
        $rdv = $this->creerRendezVous($medecin);
        $patient = User::find($rdv->patient_id);

        $this->actingAs($patient, 'sanctum')
            ->postJson("/api/medecin/rendez-vous/{$rdv->id}/consultation", ['diagnostic' => 'x'])
            ->assertStatus(403);
    }
}
