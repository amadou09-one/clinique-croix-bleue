<?php

namespace Tests\Feature;

use App\Mail\AccuseReceptionContactMail;
use App\Mail\NouveauMessageContactMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    private function payloadValide(array $overrides = []): array
    {
        return array_merge([
            'nom' => 'Awa Cissé',
            'email' => 'awa.cisse@example.com',
            'telephone' => '+221771234567',
            'sujet' => 'Question sur les assurances',
            'message' => 'Bonjour, acceptez-vous la mutuelle IPM ?',
        ], $overrides);
    }

    public function test_un_visiteur_peut_envoyer_un_message_de_contact_valide(): void
    {
        Mail::fake();

        $this->postJson('/api/contact', $this->payloadValide())
            ->assertCreated()
            ->assertJsonPath('data.nom', 'Awa Cissé');

        $this->assertDatabaseHas('messages_contact', [
            'nom' => 'Awa Cissé',
            'email' => 'awa.cisse@example.com',
            'traite' => false,
        ]);

        Mail::assertSent(NouveauMessageContactMail::class, fn ($mail) => $mail->hasTo(config('app.contact_admin_email')));
        Mail::assertSent(AccuseReceptionContactMail::class, fn ($mail) => $mail->hasTo('awa.cisse@example.com'));
    }

    public function test_le_nom_lemail_et_le_message_sont_obligatoires(): void
    {
        $this->postJson('/api/contact', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nom', 'email', 'message']);
    }

    public function test_le_telephone_doit_respecter_le_format_senegalais_si_fourni(): void
    {
        $this->postJson('/api/contact', $this->payloadValide(['telephone' => '0612345678']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['telephone']);
    }

    public function test_le_message_est_enregistre_meme_si_lenvoi_du_mail_echoue(): void
    {
        Mail::shouldReceive('to')->andThrow(new \Exception('SMTP indisponible'));

        $this->postJson('/api/contact', $this->payloadValide())
            ->assertCreated();

        $this->assertDatabaseHas('messages_contact', ['email' => 'awa.cisse@example.com']);
    }

    public function test_le_rate_limiting_bloque_apres_5_requetes_par_minute(): void
    {
        Cache::flush();
        Mail::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/contact', $this->payloadValide(['email' => "visiteur{$i}@example.com"]))
                ->assertCreated();
        }

        $this->postJson('/api/contact', $this->payloadValide(['email' => 'visiteur.bloque@example.com']))
            ->assertStatus(429);
    }
}
