<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_visiteur_peut_s_inscrire_et_recoit_un_token(): void
    {
        $response = $this->postJson('/api/register', [
            'prenom' => 'Khady',
            'nom' => 'Sow',
            'email' => 'khady.sow@example.com',
            'telephone' => '+221771112233',
            'date_naissance' => '1998-03-10',
            'sexe' => 'F',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.role', 'patient')
            ->assertJsonStructure(['data' => ['token', 'user'], 'message']);

        $this->assertDatabaseHas('users', [
            'email' => 'khady.sow@example.com',
            'role' => 'patient',
        ]);
    }

    public function test_l_inscription_ignore_le_role_injecte_par_le_client(): void
    {
        $response = $this->postJson('/api/register', [
            'prenom' => 'Khady',
            'nom' => 'Sow',
            'email' => 'khady.admin@example.com',
            'telephone' => '+221771112233',
            'date_naissance' => '1998-03-10',
            'sexe' => 'F',
            'role' => 'admin',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.role', 'patient');

        $this->assertDatabaseHas('users', [
            'email' => 'khady.admin@example.com',
            'role' => 'patient',
        ]);
    }

    public function test_un_utilisateur_peut_se_connecter_avec_les_bons_identifiants(): void
    {
        User::factory()->create([
            'email' => 'connexion@example.com',
            'password' => 'Password123!',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'connexion@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['token', 'user'], 'message']);
    }

    public function test_la_connexion_echoue_avec_un_mauvais_mot_de_passe(): void
    {
        User::factory()->create([
            'email' => 'connexion2@example.com',
            'password' => 'Password123!',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'connexion2@example.com',
            'password' => 'MauvaisMotDePasse',
        ]);

        $response->assertStatus(401);
    }

    public function test_un_compte_inactif_ne_peut_pas_se_connecter(): void
    {
        User::factory()->create([
            'email' => 'inactif@example.com',
            'password' => 'Password123!',
            'est_actif' => false,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'inactif@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(403);
    }

    public function test_une_route_admin_refuse_un_token_patient(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);

        $response = $this->actingAs($patient, 'sanctum')->getJson('/api/admin/ping');

        $response->assertStatus(403);
    }

    public function test_une_route_admin_accepte_un_token_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/ping');

        $response->assertOk();
    }
}
