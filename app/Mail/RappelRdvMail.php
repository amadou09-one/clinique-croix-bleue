<?php

namespace App\Mail;

use App\Models\RendezVous;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RappelRdvMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly RendezVous $rendezVous)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Rappel : rendez-vous demain — Clinique Croix Bleue',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.rappel-rdv',
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
