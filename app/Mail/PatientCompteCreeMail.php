<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PatientCompteCreeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $patient, public readonly string $urlDefinirMotDePasse)
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
            view: 'emails.patient-compte-cree',
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
