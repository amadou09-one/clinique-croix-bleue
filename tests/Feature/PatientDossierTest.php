<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\DocumentMedical;
use App\Models\DossierMedical;
use App\Models\Medecin;
use App\Models\RendezVous;
use App\Models\Specialite;
use App\Models\Traitement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientDossierTest extends TestCase
{
    use RefreshDatabase;

    private function creerMedecin(): Medecin
    {
        $userMedecin = User::factory()->create(['role' => 'medecin']);
        $specialite = Specialite::create(['nom' => 'Cardiologie '.uniqid()]);

        return Medecin::create(['user_id' => $userMedecin->id, 'specialite_id' => $specialite->id, 'annees_experience' => 5]);
    }

    public function test_un_non_patient_recoit_403(): void
    {
        $medecin = $this->creerMedecin();

        $this->actingAs($medecin->user, 'sanctum')
            ->getJson('/api/patient/dossier')
            ->assertStatus(403);
    }

    public function test_le_patient_voit_ses_traitements_documents_et_consultations_honorees(): void
    {
        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);

        $rdv = RendezVous::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'date_heure' => Carbon::now()->subWeek(),
            'statut' => 'honore',
            'cree_par' => $patient->id,
        ]);

        Consultation::create([
            'rendez_vous_id' => $rdv->id,
            'medecin_id' => $medecin->id,
            'patient_id' => $patient->id,
            'diagnostic' => 'Tension normalisée',
            'observations' => 'RAS',
        ]);

        Traitement::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'rendez_vous_id' => $rdv->id,
            'medicament' => 'Amlodipine 5 mg',
            'posologie' => '1 comprimé par jour',
            'date_debut' => Carbon::now()->toDateString(),
        ]);

        DocumentMedical::create([
            'patient_id' => $patient->id,
            'rendez_vous_id' => $rdv->id,
            'type' => 'ordonnance',
            'titre' => 'Ordonnance du 08/08/2026',
            'fichier_url' => 'ordonnances/1/test.pdf',
        ]);

        DossierMedical::create(['patient_id' => $patient->id, 'allergies' => 'Pénicilline']);

        $response = $this->actingAs($patient, 'sanctum')
            ->getJson('/api/patient/dossier')
            ->assertOk();

        $this->assertSame('Pénicilline', $response->json('data.dossier_medical.allergies'));
        $this->assertCount(1, $response->json('data.historique_consultations'));
        $this->assertSame('Tension normalisée', $response->json('data.historique_consultations.0.consultation.diagnostic'));
        $this->assertCount(1, $response->json('data.traitements'));
        $this->assertCount(1, $response->json('data.documents'));
    }

    public function test_le_dossier_ne_contient_pas_les_rdv_non_honores(): void
    {
        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);

        RendezVous::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'date_heure' => Carbon::now()->addWeek(),
            'statut' => 'confirme',
            'cree_par' => $patient->id,
        ]);

        $response = $this->actingAs($patient, 'sanctum')
            ->getJson('/api/patient/dossier')
            ->assertOk();

        $this->assertCount(0, $response->json('data.historique_consultations'));
    }
}
