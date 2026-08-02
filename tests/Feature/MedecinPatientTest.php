<?php

namespace Tests\Feature;

use App\Models\DossierMedical;
use App\Models\Medecin;
use App\Models\RendezVous;
use App\Models\Specialite;
use App\Models\Traitement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedecinPatientTest extends TestCase
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

    private function creerRdv(Medecin $medecin, User $patient, Carbon $dateHeure, string $statut): RendezVous
    {
        return RendezVous::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'date_heure' => $dateHeure,
            'statut' => $statut,
            'cree_par' => $patient->id,
        ]);
    }

    public function test_la_liste_ne_montre_que_les_patients_du_medecin_connecte(): void
    {
        $medecinA = $this->creerMedecin();
        $medecinB = $this->creerMedecin();
        $patientA = User::factory()->create(['role' => 'patient']);
        $patientB = User::factory()->create(['role' => 'patient']);

        $this->creerRdv($medecinA, $patientA, Carbon::now()->addDay(), 'confirme');
        $this->creerRdv($medecinB, $patientB, Carbon::now()->addDay(), 'confirme');

        $response = $this->actingAs($medecinA->user, 'sanctum')
            ->getJson('/api/medecin/patients')
            ->assertOk();

        $ids = array_column($response->json('data'), 'id');
        $this->assertSame([$patientA->id], $ids);
    }

    public function test_la_fiche_est_interdite_pour_un_patient_jamais_vu_par_ce_medecin(): void
    {
        $medecinA = $this->creerMedecin();
        $medecinB = $this->creerMedecin();
        $patientB = User::factory()->create(['role' => 'patient']);
        $this->creerRdv($medecinB, $patientB, Carbon::now()->addDay(), 'confirme');

        $this->actingAs($medecinA->user, 'sanctum')
            ->getJson("/api/medecin/patients/{$patientB->id}")
            ->assertStatus(403);
    }

    public function test_la_fiche_est_accessible_pour_un_patient_deja_vu(): void
    {
        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);
        $this->creerRdv($medecin, $patient, Carbon::now()->addDay(), 'confirme');

        $this->actingAs($medecin->user, 'sanctum')
            ->getJson("/api/medecin/patients/{$patient->id}")
            ->assertOk()
            ->assertJsonPath('data.patient.id', $patient->id);
    }

    public function test_les_allergies_sont_toujours_presentes_meme_sans_dossier_medical(): void
    {
        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);
        $this->creerRdv($medecin, $patient, Carbon::now()->addDay(), 'confirme');

        $this->actingAs($medecin->user, 'sanctum')
            ->getJson('/api/medecin/patients')
            ->assertOk()
            ->assertJsonPath('data.0.allergies', null);

        $this->actingAs($medecin->user, 'sanctum')
            ->getJson("/api/medecin/patients/{$patient->id}")
            ->assertOk()
            ->assertJsonPath('data.dossier_medical.allergies', null);
    }

    public function test_les_allergies_du_dossier_medical_sont_bien_renvoyees(): void
    {
        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);
        $this->creerRdv($medecin, $patient, Carbon::now()->addDay(), 'confirme');
        DossierMedical::create(['patient_id' => $patient->id, 'allergies' => 'Pénicilline', 'groupe_sanguin' => 'O+']);

        $response = $this->actingAs($medecin->user, 'sanctum')
            ->getJson('/api/medecin/patients')
            ->assertOk();

        $this->assertSame('Pénicilline', $response->json('data.0.allergies'));
        $this->assertSame('O+', $response->json('data.0.groupe_sanguin'));
    }

    public function test_le_statut_actif_est_calcule_pour_une_consultation_honoree_recente(): void
    {
        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);
        $this->creerRdv($medecin, $patient, Carbon::now()->subMonth(), 'honore');

        $response = $this->actingAs($medecin->user, 'sanctum')->getJson('/api/medecin/patients')->assertOk();

        $this->assertSame('actif', $response->json('data.0.statut'));
    }

    public function test_le_statut_nouveau_est_calcule_pour_un_seul_rdv_a_venir_sans_historique(): void
    {
        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);
        $this->creerRdv($medecin, $patient, Carbon::now()->addWeek(), 'confirme');

        $response = $this->actingAs($medecin->user, 'sanctum')->getJson('/api/medecin/patients')->assertOk();

        $this->assertSame('nouveau', $response->json('data.0.statut'));
    }

    public function test_le_statut_inactif_est_calcule_pour_une_derniere_consultation_ancienne(): void
    {
        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);
        $this->creerRdv($medecin, $patient, Carbon::now()->subMonths(8), 'honore');

        $response = $this->actingAs($medecin->user, 'sanctum')->getJson('/api/medecin/patients')->assertOk();

        $this->assertSame('inactif', $response->json('data.0.statut'));
    }

    public function test_la_recherche_filtre_par_nom(): void
    {
        $medecin = $this->creerMedecin();
        $awa = User::factory()->create(['role' => 'patient', 'prenom' => 'Awa', 'nom' => 'Cissé']);
        $ousmane = User::factory()->create(['role' => 'patient', 'prenom' => 'Ousmane', 'nom' => 'Gueye']);
        $this->creerRdv($medecin, $awa, Carbon::now()->addDay()->setTime(9, 0), 'confirme');
        $this->creerRdv($medecin, $ousmane, Carbon::now()->addDay()->setTime(10, 0), 'confirme');

        $response = $this->actingAs($medecin->user, 'sanctum')
            ->getJson('/api/medecin/patients?recherche=awa')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Awa', $response->json('data.0.prenom'));
    }

    public function test_le_filtre_par_statut_fonctionne(): void
    {
        $medecin = $this->creerMedecin();
        $actif = User::factory()->create(['role' => 'patient']);
        $nouveau = User::factory()->create(['role' => 'patient']);
        $this->creerRdv($medecin, $actif, Carbon::now()->subWeek(), 'honore');
        $this->creerRdv($medecin, $nouveau, Carbon::now()->addWeek(), 'confirme');

        $response = $this->actingAs($medecin->user, 'sanctum')
            ->getJson('/api/medecin/patients?statut=nouveau')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($nouveau->id, $response->json('data.0.id'));
    }

    public function test_lhistorique_de_la_fiche_ne_contient_pas_les_rdv_dun_autre_medecin(): void
    {
        $medecinA = $this->creerMedecin();
        $medecinB = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);
        $rdvA = $this->creerRdv($medecinA, $patient, Carbon::now()->addDay(), 'confirme');
        $this->creerRdv($medecinB, $patient, Carbon::now()->addDays(2), 'confirme');

        $response = $this->actingAs($medecinA->user, 'sanctum')
            ->getJson("/api/medecin/patients/{$patient->id}")
            ->assertOk();

        $ids = array_column($response->json('data.historique_rendez_vous'), 'id');
        $this->assertSame([$rdvA->id], $ids);
    }

    public function test_les_traitements_de_la_fiche_ne_contiennent_pas_ceux_dun_autre_medecin(): void
    {
        $medecinA = $this->creerMedecin();
        $medecinB = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);
        $this->creerRdv($medecinA, $patient, Carbon::now()->addDay(), 'confirme');

        Traitement::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecinA->id,
            'medicament' => 'Paracétamol',
            'posologie' => '1 comprimé matin et soir',
            'date_debut' => Carbon::now()->toDateString(),
        ]);
        Traitement::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecinB->id,
            'medicament' => 'Ibuprofène',
            'posologie' => '1 comprimé si douleur',
            'date_debut' => Carbon::now()->toDateString(),
        ]);

        $response = $this->actingAs($medecinA->user, 'sanctum')
            ->getJson("/api/medecin/patients/{$patient->id}")
            ->assertOk();

        $medicaments = array_column($response->json('data.traitements'), 'medicament');
        $this->assertSame(['Paracétamol'], $medicaments);
    }
}
