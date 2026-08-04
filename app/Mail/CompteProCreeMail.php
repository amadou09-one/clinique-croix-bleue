<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invitation envoyée par l'administration à un nouveau compte medecin/secretaire/
 * admin — même mécanisme que PatientCompteCreeMail (mot de passe temporaire jamais
 * transmis, lien de définition), mais texte générique adapté à un compte
 * professionnel plutôt qu'à un patient.
 */
class CompteProCreeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $utilisateur, public readonly string $urlDefinirMotDePasse)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre compte a été créé — Clinique Croix Bleue',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.compte-pro-cree',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
