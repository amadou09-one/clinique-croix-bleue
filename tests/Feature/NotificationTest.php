<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function creerNotification(User $user, ?string $luLe = null): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => 'info',
            'canal' => 'in_app',
            'contenu' => 'Notification de test',
            'lu_le' => $luLe,
            'envoye_le' => now(),
        ]);
    }

    public function test_le_patient_voit_uniquement_ses_propres_notifications_paginees_recentes_dabord(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $autrePatient = User::factory()->create(['role' => 'patient']);

        $ancienne = $this->creerNotification($patient);
        $ancienne->forceFill(['created_at' => now()->subMinute()])->save();
        $recente = $this->creerNotification($patient);
        $notificationAutrePatient = $this->creerNotification($autrePatient);

        $response = $this->actingAs($patient, 'sanctum')
            ->getJson('/api/patient/notifications')
            ->assertOk();

        $ids = array_column($response->json('data.data'), 'id');
        $this->assertCount(2, $ids);
        $this->assertSame($recente->id, $ids[0]);
        $this->assertContains($ancienne->id, $ids);
        $this->assertNotContains($notificationAutrePatient->id, $ids);
    }

    public function test_le_compteur_de_notifications_non_lues_est_correct(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $this->creerNotification($patient);
        $this->creerNotification($patient);
        $this->creerNotification($patient, now());

        $this->actingAs($patient, 'sanctum')
            ->getJson('/api/patient/notifications/non-lues/count')
            ->assertOk()
            ->assertJsonPath('data.count', 2);
    }

    public function test_marquer_une_notification_comme_lue(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $notification = $this->creerNotification($patient);

        $this->actingAs($patient, 'sanctum')
            ->putJson("/api/patient/notifications/{$notification->id}/lue")
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id);

        $this->assertNotNull($notification->fresh()->lu_le);
    }

    public function test_un_patient_ne_peut_pas_marquer_comme_lue_la_notification_dun_autre(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $autrePatient = User::factory()->create(['role' => 'patient']);
        $notification = $this->creerNotification($autrePatient);

        $this->actingAs($patient, 'sanctum')
            ->putJson("/api/patient/notifications/{$notification->id}/lue")
            ->assertStatus(403);

        $this->assertNull($notification->fresh()->lu_le);
    }

    public function test_tout_marquer_comme_lu(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $this->creerNotification($patient);
        $this->creerNotification($patient);
        $dejaLue = $this->creerNotification($patient, now()->subDay());

        $this->actingAs($patient, 'sanctum')
            ->putJson('/api/patient/notifications/tout-lu')
            ->assertOk();

        $this->assertSame(0, Notification::where('user_id', $patient->id)->whereNull('lu_le')->count());
        $this->assertEqualsWithDelta($dejaLue->lu_le->timestamp, $dejaLue->fresh()->lu_le->timestamp, 1);
    }
}
