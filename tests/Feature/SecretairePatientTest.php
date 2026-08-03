<?php

namespace Tests\Feature;

use App\Mail\PatientCompteCreeMail;
use App\Models\Disponibilite;
use App\Models\Medecin;
use App\Models\RendezVous;
use App\Models\Specialite;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class SecretairePatientTest extends TestCase
{
    use RefreshDatabase;

    private function creerSecretaire(): User
    {
        return User::factory()->create(['role' => 'secretaire']);
    }

    private function creerMedecinAvecDisponibilite(): array
    {
        $userMedecin = User::factory()->create(['role' => 'medecin']);
        $specialite = Specialite::create(['nom' => 'Cardiologie '.uniqid()]);
        $medecin = Medecin::create([
            'user_id' => $userMedecin->id,
            'specialite_id' => $specialite->id,
            'annees_experience' => 5,
        ]);

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

    public function test_une_secretaire_peut_creer_un_rdv_pour_un_patient_tiers(): void
    {
        Mail::fake();
        $secretaire = $this->creerSecretaire();
        [$medecin, $jour] = $this->creerMedecinAvecDisponibilite();
        $patient = User::factory()->create(['role' => 'patient']);

        $response = $this->actingAs($secretaire, 'sanctum')
            ->postJson('/api/rendez-vous', [
                'medecin_id' => $medecin->id,
                'patient_id' => $patient->id,
                'date_heure' => $jour->copy()->setTime(9, 0)->toDateTimeString(),
                'motif' => 'Consultation au comptoir',
            ])
            ->assertCreated();

        $rendezVousId = $response->json('data.id');
        $rendezVous = RendezVous::find($rendezVousId);

        $this->assertSame($patient->id, $rendezVous->patient_id);
        $this->assertSame($secretaire->id, $rendezVous->cree_par);
    }

    public function test_un_patient_ne_peut_pas_creer_de_rdv_pour_un_autre_patient(): void
    {
        Mail::fake();
        [$medecin, $jour] = $this->creerMedecinAvecDisponibilite();
        $patient = User::factory()->create(['role' => 'patient']);
        $autrePatient = User::factory()->create(['role' => 'patient']);

        $response = $this->actingAs($patient, 'sanctum')
            ->postJson('/api/rendez-vous', [
                'medecin_id' => $medecin->id,
                'patient_id' => $autrePatient->id, // tentative d'usurpation
                'date_heure' => $jour->copy()->setTime(9, 0)->toDateTimeString(),
            ])
            ->assertCreated();

        $rendezVous = RendezVous::find($response->json('data.id'));

        $this->assertSame($patient->id, $rendezVous->patient_id);
        $this->assertNotSame($autrePatient->id, $rendezVous->patient_id);
        $this->assertSame($patient->id, $rendezVous->cree_par);
    }

    public function test_une_secretaire_doit_fournir_un_patient_id_pour_creer_un_rdv(): void
    {
        [$medecin, $jour] = $this->creerMedecinAvecDisponibilite();
        $secretaire = $this->creerSecretaire();

        $this->actingAs($secretaire, 'sanctum')
            ->postJson('/api/rendez-vous', [
                'medecin_id' => $medecin->id,
                'date_heure' => $jour->copy()->setTime(9, 0)->toDateTimeString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('patient_id');
    }

    public function test_une_secretaire_ne_peut_pas_creer_de_rdv_pour_un_patient_inexistant(): void
    {
        [$medecin, $jour] = $this->creerMedecinAvecDisponibilite();
        $secretaire = $this->creerSecretaire();

        $this->actingAs($secretaire, 'sanctum')
            ->postJson('/api/rendez-vous', [
                'medecin_id' => $medecin->id,
                'patient_id' => 999999,
                'date_heure' => $jour->copy()->setTime(9, 0)->toDateTimeString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('patient_id');
    }

    public function test_une_secretaire_ne_peut_pas_creer_de_rdv_pour_un_compte_non_patient(): void
    {
        [$medecin, $jour] = $this->creerMedecinAvecDisponibilite();
        $secretaire = $this->creerSecretaire();
        $autreSecretaire = User::factory()->create(['role' => 'secretaire']);

        $this->actingAs($secretaire, 'sanctum')
            ->postJson('/api/rendez-vous', [
                'medecin_id' => $medecin->id,
                'patient_id' => $autreSecretaire->id,
                'date_heure' => $jour->copy()->setTime(9, 0)->toDateTimeString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('patient_id');
    }

    public function test_la_secretaire_peut_enregistrer_un_nouveau_patient_au_comptoir(): void
    {
        Mail::fake();
        $secretaire = $this->creerSecretaire();

        $response = $this->actingAs($secretaire, 'sanctum')
            ->postJson('/api/secretaire/patients', [
                'prenom' => 'Awa',
                'nom' => 'Cissé',
                'email' => 'awa.cisse@example.com',
                'telephone' => '+221771234567',
                'date_naissance' => '1994-03-10',
                'sexe' => 'F',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'awa.cisse@example.com',
            'role' => 'patient',
        ]);

        // Aucun mot de passe en clair n'apparaît jamais dans la réponse API.
        $this->assertArrayNotHasKey('password', $response->json('data'));

        Mail::assertSent(PatientCompteCreeMail::class, function (PatientCompteCreeMail $mail) {
            return $mail->hasTo('awa.cisse@example.com')
                && str_contains($mail->urlDefinirMotDePasse, '/definir-mot-de-passe')
                && str_contains($mail->urlDefinirMotDePasse, 'token=');
        });
    }

    public function test_le_patient_cree_au_comptoir_ne_peut_pas_se_connecter_avant_de_definir_son_mot_de_passe(): void
    {
        Mail::fake();
        $secretaire = $this->creerSecretaire();

        $this->actingAs($secretaire, 'sanctum')->postJson('/api/secretaire/patients', [
            'prenom' => 'Awa',
            'nom' => 'Cissé',
            'email' => 'awa.cisse@example.com',
            'telephone' => '+221771234567',
            'date_naissance' => '1994-03-10',
        ])->assertCreated();

        // Impossible de deviner le mot de passe aléatoire généré côté serveur.
        $this->postJson('/api/login', [
            'email' => 'awa.cisse@example.com',
            'password' => 'password',
        ])->assertStatus(401);
    }

    public function test_le_patient_peut_definir_son_mot_de_passe_via_le_lien_recu_par_mail(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $token = Password::broker()->createToken($patient);

        $this->postJson('/api/definir-mot-de-passe', [
            'email' => $patient->email,
            'token' => $token,
            'password' => 'NouveauMdp123',
            'password_confirmation' => 'NouveauMdp123',
        ])->assertOk();

        $this->assertTrue(Hash::check('NouveauMdp123', $patient->fresh()->password));

        $this->postJson('/api/login', [
            'email' => $patient->email,
            'password' => 'NouveauMdp123',
        ])->assertOk();
    }

    public function test_un_token_invalide_est_refuse(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);

        $this->postJson('/api/definir-mot-de-passe', [
            'email' => $patient->email,
            'token' => 'token-invalide',
            'password' => 'NouveauMdp123',
            'password_confirmation' => 'NouveauMdp123',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_la_recherche_de_patients_fonctionne(): void
    {
        $secretaire = $this->creerSecretaire();
        User::factory()->create(['role' => 'patient', 'prenom' => 'Awa', 'nom' => 'Cissé']);
        User::factory()->create(['role' => 'patient', 'prenom' => 'Ousmane', 'nom' => 'Gueye']);

        $response = $this->actingAs($secretaire, 'sanctum')
            ->getJson('/api/secretaire/patients/recherche?q=awa')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Awa', $response->json('data.0.prenom'));
    }

    public function test_la_liste_paginee_des_patients_fonctionne(): void
    {
        $secretaire = $this->creerSecretaire();
        User::factory()->count(3)->create(['role' => 'patient']);
        User::factory()->create(['role' => 'medecin']);

        $response = $this->actingAs($secretaire, 'sanctum')
            ->getJson('/api/secretaire/patients')
            ->assertOk();

        $this->assertSame(3, $response->json('data.total'));
    }

    public function test_un_medecin_ne_peut_pas_acceder_aux_routes_patients_secretaire(): void
    {
        $userMedecin = User::factory()->create(['role' => 'medecin']);

        $this->actingAs($userMedecin, 'sanctum')
            ->getJson('/api/secretaire/patients')
            ->assertStatus(403);

        $this->actingAs($userMedecin, 'sanctum')
            ->postJson('/api/secretaire/patients', [])
            ->assertStatus(403);
    }
}
