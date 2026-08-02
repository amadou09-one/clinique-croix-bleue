<?php

namespace App\Mail;

use App\Models\RendezVous;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RdvDecisionPatient extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly RendezVous $rendezVous,
        public readonly bool $accepte,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->accepte
                ? 'Votre rendez-vous est confirmé — Clinique Croix Bleue'
                : "Votre demande de rendez-vous n'a pas été retenue — Clinique Croix Bleue",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.rdv-decision-patient',
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
