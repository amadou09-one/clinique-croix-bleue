<?php

namespace App\Mail;

use App\Models\MessageContact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifie l'administration qu'un visiteur a soumis le formulaire de contact
 * du site vitrine — envoyée à config('app.contact_admin_email').
 */
class NouveauMessageContactMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly MessageContact $messageContact)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nouveau message de contact — '.$this->messageContact->nom,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.nouveau-message-contact',
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
