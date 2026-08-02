<?php

namespace App\Mail;

use App\Models\RendezVous;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AnnulationRdvMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly RendezVous $rendezVous)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre rendez-vous a été annulé — Clinique Croix Bleue',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.annulation-rdv',
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
