<?php

namespace App\Mail;

use App\Models\RendezVous;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RdvDemandeValidationMedecin extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly RendezVous $rendezVous,
        public readonly string $urlAccepter,
        public readonly string $urlRefuser,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nouvelle demande de rendez-vous à valider — Clinique Croix Bleue',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.rdv-demande-validation-medecin',
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
