<?php

namespace Tests\Feature;

use App\Mail\CompteProCreeMail;
use App\Mail\PatientCompteCreeMail;
use App\Models\Medecin;
use App\Models\RendezVous;
use App\Models\Specialite;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminUtilisateurTest extends TestCase
{
    use RefreshDatabase;

    private function creerAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_un_admin_peut_creer_un_compte_medecin_avec_specialite(): void
    {
        Mail::fake();
        $admin = $this->creerAdmin();
        $specialite = Specialite::create(['nom' => 'Cardiologie '.uniqid()]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/utilisateurs', [
                'prenom' => 'Khady',
                'nom' => 'Sène',
                'email' => 'k.sene@croixbleue.sn',
                'telephone' => '+221771234567',
                'role' => 'medecin',
                'specialite_id' => $specialite->id,
                'titre' => 'Docteur en médecine',
                'annees_experience' => 8,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'k.sene@croixbleue.sn', 'role' => 'medecin']);
        $this->assertDatabaseHas('medecins', ['specialite_id' => $specialite->id, 'annees_experience' => 8]);
        $this->assertArrayNotHasKey('password', $response->json('data'));

        Mail::assertSent(CompteProCreeMail::class, fn (CompteProCreeMail $mail) => $mail->hasTo('k.sene@croixbleue.sn'));
    }

    public function test_creer_un_medecin_sans_specialite_est_refuse(): void
    {
        $admin = $this->creerAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/utilisateurs', [
                'prenom' => 'Khady',
                'nom' => 'Sène',
                'email' => 'k.sene@croixbleue.sn',
                'telephone' => '+221771234567',
                'role' => 'medecin',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['specialite_id', 'annees_experience']);
    }

    public function test_un_admin_peut_creer_un_compte_secretaire(): void
    {
        Mail::fake();
        $admin = $this->creerAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/utilisateurs', [
                'prenom' => 'Fatou',
                'nom' => 'Ndao',
                'email' => 'f.ndao@croixbleue.sn',
                'telephone' => '+221771234568',
                'role' => 'secretaire',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'f.ndao@croixbleue.sn', 'role' => 'secretaire']);
        $this->assertDatabaseMissing('medecins', ['user_id' => User::where('email', 'f.ndao@croixbleue.sn')->first()->id]);
        Mail::assertSent(CompteProCreeMail::class);
    }

    public function test_un_admin_peut_creer_un_compte_patient_avec_le_mail_patient(): void
    {
        Mail::fake();
        $admin = $this->creerAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/utilisateurs', [
                'prenom' => 'Awa',
                'nom' => 'Cissé',
                'email' => 'awa.cisse@example.com',
                'telephone' => '+221771234569',
                'role' => 'patient',
            ])
            ->assertCreated();

        Mail::assertSent(PatientCompteCreeMail::class);
        Mail::assertNotSent(CompteProCreeMail::class);
    }

    public function test_un_medecin_ne_peut_pas_creer_de_compte(): void
    {
        $medecin = User::factory()->create(['role' => 'medecin']);

        $this->actingAs($medecin, 'sanctum')
            ->postJson('/api/admin/utilisateurs', ['prenom' => 'X', 'nom' => 'Y', 'email' => 'x@y.com', 'telephone' => '+221771234567', 'role' => 'secretaire'])
            ->assertStatus(403);
    }

    public function test_une_secretaire_ne_peut_pas_creer_de_compte_medecin(): void
    {
        $secretaire = User::factory()->create(['role' => 'secretaire']);

        $this->actingAs($secretaire, 'sanctum')
            ->postJson('/api/admin/utilisateurs', ['prenom' => 'X', 'nom' => 'Y', 'email' => 'x@y.com', 'telephone' => '+221771234567', 'role' => 'medecin'])
            ->assertStatus(403);
    }

    public function test_un_patient_ne_peut_pas_creer_de_compte(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);

        $this->actingAs($patient, 'sanctum')
            ->postJson('/api/admin/utilisateurs', ['prenom' => 'X', 'nom' => 'Y', 'email' => 'x@y.com', 'telephone' => '+221771234567', 'role' => 'medecin'])
            ->assertStatus(403);
    }

    public function test_un_non_admin_recoit_403_sur_la_liste_et_les_actions(): void
    {
        $medecin = User::factory()->create(['role' => 'medecin']);
        $autre = User::factory()->create(['role' => 'patient']);

        $this->actingAs($medecin, 'sanctum')->getJson('/api/admin/utilisateurs')->assertStatus(403);
        $this->actingAs($medecin, 'sanctum')->putJson("/api/admin/utilisateurs/{$autre->id}", [])->assertStatus(403);
        $this->actingAs($medecin, 'sanctum')->deleteJson("/api/admin/utilisateurs/{$autre->id}")->assertStatus(403);
    }

    public function test_desactiver_un_compte_medecin_ne_supprime_pas_ses_rdv_passes(): void
    {
        $admin = $this->creerAdmin();
        $userMedecin = User::factory()->create(['role' => 'medecin']);
        $specialite = Specialite::create(['nom' => 'Cardiologie '.uniqid()]);
        $medecin = Medecin::create(['user_id' => $userMedecin->id, 'specialite_id' => $specialite->id, 'annees_experience' => 5]);
        $patient = User::factory()->create(['role' => 'patient']);

        $rdv = RendezVous::create([
            'patient_id' => $patient->id,
            'medecin_id' => $medecin->id,
            'date_heure' => Carbon::now()->subMonth(),
            'statut' => 'honore',
            'cree_par' => $patient->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/utilisateurs/{$userMedecin->id}")
            ->assertOk();

        $this->assertFalse($userMedecin->fresh()->est_actif);
        $this->assertDatabaseHas('rendez_vous', ['id' => $rdv->id, 'statut' => 'honore']);
        $this->assertDatabaseHas('medecins', ['id' => $medecin->id]);
        $this->assertDatabaseHas('users', ['id' => $userMedecin->id]); // jamais de suppression physique
    }

    public function test_un_admin_ne_peut_pas_se_desactiver_lui_meme(): void
    {
        $admin = $this->creerAdmin();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/utilisateurs/{$admin->id}")
            ->assertStatus(403);

        $this->assertTrue($admin->fresh()->est_actif);
    }

    public function test_un_compte_medecin_desactive_ne_peut_plus_se_connecter(): void
    {
        $admin = $this->creerAdmin();
        $userMedecin = User::factory()->create(['role' => 'medecin', 'password' => Hash::make('Password123!')]);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/utilisateurs/{$userMedecin->id}")->assertOk();

        $this->postJson('/api/login', [
            'email' => $userMedecin->email,
            'password' => 'Password123!',
        ])->assertStatus(403);
    }

    public function test_reactiver_un_compte_via_update(): void
    {
        $admin = $this->creerAdmin();
        $userMedecin = User::factory()->create(['role' => 'medecin', 'est_actif' => false]);
        $specialite = Specialite::create(['nom' => 'Cardiologie '.uniqid()]);
        Medecin::create(['user_id' => $userMedecin->id, 'specialite_id' => $specialite->id, 'annees_experience' => 5]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/utilisateurs/{$userMedecin->id}", [
                'prenom' => $userMedecin->prenom,
                'nom' => $userMedecin->nom,
                'email' => $userMedecin->email,
                'telephone' => $userMedecin->telephone,
                'role' => 'medecin',
                'est_actif' => true,
                'specialite_id' => $specialite->id,
                'annees_experience' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('data.est_actif', true);

        $this->assertTrue($userMedecin->fresh()->est_actif);
    }

    public function test_la_liste_filtre_par_role_et_recherche(): void
    {
        $admin = $this->creerAdmin();
        User::factory()->create(['role' => 'medecin', 'prenom' => 'Khady', 'nom' => 'Sène']);
        User::factory()->create(['role' => 'patient', 'prenom' => 'Awa', 'nom' => 'Cissé']);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/utilisateurs?role=medecin&recherche=Khady')
            ->assertOk();

        $this->assertSame(1, $response->json('data.total'));
        $this->assertSame('Khady', $response->json('data.data.0.prenom'));
    }
}
