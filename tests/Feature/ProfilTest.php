<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_patient_peut_consulter_son_profil(): void
    {
        $patient = User::factory()->create(['role' => 'patient', 'prenom' => 'Awa']);

        $this->actingAs($patient, 'sanctum')
            ->getJson('/api/profil')
            ->assertOk()
            ->assertJsonPath('data.prenom', 'Awa')
            ->assertJsonPath('data.email', $patient->email);
    }

    public function test_le_patient_peut_mettre_a_jour_son_profil(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);

        $this->actingAs($patient, 'sanctum')
            ->putJson('/api/profil', [
                'prenom' => 'Fatou',
                'nom' => 'Diop',
                'email' => $patient->email,
                'telephone' => '+221771234567',
                'date_naissance' => '1995-05-12',
                'sexe' => 'F',
            ])
            ->assertOk()
            ->assertJsonPath('data.prenom', 'Fatou')
            ->assertJsonPath('data.nom', 'Diop');

        $this->assertDatabaseHas('users', ['id' => $patient->id, 'prenom' => 'Fatou', 'nom' => 'Diop']);
    }

    public function test_la_mise_a_jour_du_profil_avec_son_propre_email_ne_declenche_pas_lerreur_unique(): void
    {
        $patient = User::factory()->create(['role' => 'patient', 'email' => 'awa@example.com']);

        $this->actingAs($patient, 'sanctum')
            ->putJson('/api/profil', [
                'prenom' => $patient->prenom,
                'nom' => $patient->nom,
                'email' => 'awa@example.com',
                'telephone' => '+221771234567',
                'date_naissance' => '1995-05-12',
            ])
            ->assertOk();
    }

    public function test_la_mise_a_jour_du_profil_refuse_lemail_deja_utilise_par_un_autre_utilisateur(): void
    {
        User::factory()->create(['role' => 'patient', 'email' => 'dejapris@example.com']);
        $patient = User::factory()->create(['role' => 'patient']);

        $this->actingAs($patient, 'sanctum')
            ->putJson('/api/profil', [
                'prenom' => $patient->prenom,
                'nom' => $patient->nom,
                'email' => 'dejapris@example.com',
                'telephone' => '+221771234567',
                'date_naissance' => '1995-05-12',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_la_mise_a_jour_du_profil_refuse_un_telephone_qui_nest_pas_au_format_senegalais(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);

        $this->actingAs($patient, 'sanctum')
            ->putJson('/api/profil', [
                'prenom' => $patient->prenom,
                'nom' => $patient->nom,
                'email' => $patient->email,
                'telephone' => '0771234567',
                'date_naissance' => '1995-05-12',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('telephone');
    }

    public function test_le_changement_de_mot_de_passe_avec_un_mauvais_mot_de_passe_actuel_est_refuse(): void
    {
        $patient = User::factory()->create(['role' => 'patient', 'password' => bcrypt('ancien-mdp')]);

        $this->actingAs($patient, 'sanctum')
            ->putJson('/api/profil/mot-de-passe', [
                'mot_de_passe_actuel' => 'mauvais-mdp',
                'password' => 'nouveau-mdp-123',
                'password_confirmation' => 'nouveau-mdp-123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mot_de_passe_actuel');

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('ancien-mdp', $patient->fresh()->password));
    }

    public function test_le_changement_de_mot_de_passe_fonctionne_avec_le_bon_mot_de_passe_actuel(): void
    {
        $patient = User::factory()->create(['role' => 'patient', 'password' => bcrypt('ancien-mdp')]);

        $this->actingAs($patient, 'sanctum')
            ->putJson('/api/profil/mot-de-passe', [
                'mot_de_passe_actuel' => 'ancien-mdp',
                'password' => 'nouveau-mdp-123',
                'password_confirmation' => 'nouveau-mdp-123',
            ])
            ->assertOk();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('nouveau-mdp-123', $patient->fresh()->password));
    }

    public function test_le_changement_de_mot_de_passe_refuse_un_mot_de_passe_de_moins_de_8_caracteres(): void
    {
        $patient = User::factory()->create(['role' => 'patient', 'password' => bcrypt('ancien-mdp')]);

        $this->actingAs($patient, 'sanctum')
            ->putJson('/api/profil/mot-de-passe', [
                'mot_de_passe_actuel' => 'ancien-mdp',
                'password' => 'court1',
                'password_confirmation' => 'court1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_le_patient_peut_consulter_ses_preferences_de_notification(): void
    {
        $patient = User::factory()->create(['role' => 'patient', 'notif_email_rdv' => false]);

        $this->actingAs($patient, 'sanctum')
            ->getJson('/api/preferences')
            ->assertOk()
            ->assertJsonPath('data.notif_email_rdv', false)
            ->assertJsonPath('data.notif_email_rappel', true);
    }

    public function test_le_patient_peut_mettre_a_jour_ses_preferences_de_notification(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);

        $this->actingAs($patient, 'sanctum')
            ->putJson('/api/preferences', [
                'notif_email_rdv' => false,
                'notif_email_rappel' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.notif_email_rdv', false)
            ->assertJsonPath('data.notif_email_rappel', true);

        $this->assertDatabaseHas('users', [
            'id' => $patient->id,
            'notif_email_rdv' => false,
            'notif_email_rappel' => true,
        ]);
    }

    /**
     * /api/profil, /api/profil/mot-de-passe et /api/preferences sont génériques :
     * accessibles à tout rôle authentifié, puisqu'ils n'agissent jamais que sur
     * $request->user() (voir ProfilController). Un médecin doit donc pouvoir les
     * utiliser exactement comme un patient.
     */
    public function test_un_medecin_peut_aussi_consulter_et_modifier_son_profil(): void
    {
        $medecin = User::factory()->create(['role' => 'medecin', 'telephone' => '+221770000000']);

        $this->actingAs($medecin, 'sanctum')
            ->getJson('/api/profil')
            ->assertOk()
            ->assertJsonPath('data.id', $medecin->id);

        $this->actingAs($medecin, 'sanctum')
            ->putJson('/api/profil', [
                'prenom' => $medecin->prenom,
                'nom' => $medecin->nom,
                'email' => $medecin->email,
                'telephone' => '+221779998877',
                'date_naissance' => $medecin->date_naissance?->toDateString() ?? '1980-01-01',
                'sexe' => $medecin->sexe,
            ])
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $medecin->id, 'telephone' => '+221779998877']);
    }
}
