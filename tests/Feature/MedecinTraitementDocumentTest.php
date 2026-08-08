<?php

namespace Tests\Feature;

use App\Models\DocumentMedical;
use App\Models\Medecin;
use App\Models\RendezVous;
use App\Models\Specialite;
use App\Models\Traitement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MedecinTraitementDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function creerMedecin(): Medecin
    {
        $userMedecin = User::factory()->create(['role' => 'medecin']);
        $specialite = Specialite::create(['nom' => 'Cardiologie '.uniqid()]);

        return Medecin::create(['user_id' => $userMedecin->id, 'specialite_id' => $specialite->id, 'annees_experience' => 5]);
    }

    private function creerRdv(Medecin $medecin, User $patient, string $statut = 'honore', ?Carbon $dateHeure = null): RendezVous
    {
        return RendezVous::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'date_heure' => $dateHeure ?? Carbon::now()->subDay(),
            'statut' => $statut,
            'cree_par' => $patient->id,
        ]);
    }

    public function test_un_medecin_peut_ajouter_un_traitement_a_un_patient_deja_vu(): void
    {
        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);
        $rdv = $this->creerRdv($medecin, $patient);

        $this->actingAs($medecin->user, 'sanctum')
            ->postJson("/api/medecin/patients/{$patient->id}/traitements", [
                'medicament' => 'Amlodipine 5 mg',
                'posologie' => '1 comprimé par jour',
                'date_debut' => Carbon::now()->toDateString(),
                'rendez_vous_id' => $rdv->id,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('traitements', [
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'medicament' => 'Amlodipine 5 mg',
            'rendez_vous_id' => $rdv->id,
        ]);
    }

    public function test_un_medecin_ne_peut_pas_ajouter_de_traitement_a_un_patient_jamais_vu(): void
    {
        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);

        $this->actingAs($medecin->user, 'sanctum')
            ->postJson("/api/medecin/patients/{$patient->id}/traitements", [
                'medicament' => 'Amlodipine 5 mg',
                'posologie' => '1 comprimé par jour',
                'date_debut' => Carbon::now()->toDateString(),
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('traitements', ['patient_id' => $patient->id]);
    }

    public function test_un_rendez_vous_id_appartenant_a_un_autre_patient_est_refuse(): void
    {
        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);
        $autrePatient = User::factory()->create(['role' => 'patient']);
        $this->creerRdv($medecin, $patient);
        $rdvAutrePatient = $this->creerRdv($medecin, $autrePatient, dateHeure: Carbon::now()->subDays(2));

        $this->actingAs($medecin->user, 'sanctum')
            ->postJson("/api/medecin/patients/{$patient->id}/traitements", [
                'medicament' => 'Amlodipine 5 mg',
                'posologie' => '1 comprimé par jour',
                'date_debut' => Carbon::now()->toDateString(),
                'rendez_vous_id' => $rdvAutrePatient->id,
            ])
            ->assertStatus(422);
    }

    public function test_generer_une_ordonnance_cree_un_document_medical_et_le_fichier_pdf(): void
    {
        Storage::fake('local');

        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);
        $rdv = $this->creerRdv($medecin, $patient);

        Traitement::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'rendez_vous_id' => $rdv->id,
            'medicament' => 'Amlodipine 5 mg',
            'posologie' => '1 comprimé par jour',
            'date_debut' => Carbon::now()->toDateString(),
        ]);

        $response = $this->actingAs($medecin->user, 'sanctum')
            ->postJson("/api/medecin/patients/{$patient->id}/documents", ['rendez_vous_id' => $rdv->id])
            ->assertCreated()
            ->assertJsonPath('data.type', 'ordonnance');

        $this->assertDatabaseHas('documents_medicaux', [
            'patient_id' => $patient->id,
            'rendez_vous_id' => $rdv->id,
            'type' => 'ordonnance',
        ]);

        $chemin = $response->json('data.fichier_url');
        Storage::disk('local')->assertExists($chemin);
    }

    public function test_generer_une_ordonnance_sans_traitement_est_refuse(): void
    {
        Storage::fake('local');

        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);
        $this->creerRdv($medecin, $patient);

        $this->actingAs($medecin->user, 'sanctum')
            ->postJson("/api/medecin/patients/{$patient->id}/documents", [])
            ->assertStatus(422);

        $this->assertDatabaseMissing('documents_medicaux', ['patient_id' => $patient->id]);
    }

    public function test_le_patient_proprietaire_peut_telecharger_son_document(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ordonnances/1/test.pdf', '%PDF-1.4 contenu factice');

        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);
        $this->creerRdv($medecin, $patient);

        $document = DocumentMedical::create([
            'patient_id' => $patient->id,
            'type' => 'ordonnance',
            'titre' => 'Ordonnance du 08/08/2026',
            'fichier_url' => 'ordonnances/1/test.pdf',
        ]);

        $this->actingAs($patient, 'sanctum')
            ->get("/api/documents/{$document->id}/telecharger")
            ->assertOk();
    }

    public function test_un_autre_patient_ne_peut_pas_telecharger_le_document(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ordonnances/1/test.pdf', '%PDF-1.4 contenu factice');

        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);
        $autrePatient = User::factory()->create(['role' => 'patient']);
        $this->creerRdv($medecin, $patient);

        $document = DocumentMedical::create([
            'patient_id' => $patient->id,
            'type' => 'ordonnance',
            'titre' => 'Ordonnance',
            'fichier_url' => 'ordonnances/1/test.pdf',
        ]);

        $this->actingAs($autrePatient, 'sanctum')
            ->get("/api/documents/{$document->id}/telecharger")
            ->assertStatus(403);
    }

    public function test_le_medecin_traitant_peut_telecharger_le_document_de_son_patient(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ordonnances/1/test.pdf', '%PDF-1.4 contenu factice');

        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);
        $this->creerRdv($medecin, $patient);

        $document = DocumentMedical::create([
            'patient_id' => $patient->id,
            'type' => 'ordonnance',
            'titre' => 'Ordonnance',
            'fichier_url' => 'ordonnances/1/test.pdf',
        ]);

        $this->actingAs($medecin->user, 'sanctum')
            ->get("/api/documents/{$document->id}/telecharger")
            ->assertOk();
    }

    public function test_les_allergies_restent_visibles_dans_la_fiche_pendant_la_saisie_de_traitement(): void
    {
        $medecin = $this->creerMedecin();
        $patient = User::factory()->create(['role' => 'patient']);
        $this->creerRdv($medecin, $patient);
        \App\Models\DossierMedical::create(['patient_id' => $patient->id, 'allergies' => 'Pénicilline']);

        $this->actingAs($medecin->user, 'sanctum')
            ->postJson("/api/medecin/patients/{$patient->id}/traitements", [
                'medicament' => 'Amlodipine 5 mg',
                'posologie' => '1 comprimé par jour',
                'date_debut' => Carbon::now()->toDateString(),
            ])
            ->assertCreated();

        $response = $this->actingAs($medecin->user, 'sanctum')
            ->getJson("/api/medecin/patients/{$patient->id}")
            ->assertOk();

        $this->assertSame('Pénicilline', $response->json('data.dossier_medical.allergies'));
    }
}
