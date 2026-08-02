<?php

namespace App\Mail;

use App\Models\RendezVous;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RdvConfirmationPatient extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly RendezVous $rendezVous)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre demande de rendez-vous a bien été reçue — Clinique Croix Bleue',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.rdv-confirmation-patient',
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
