<?php

namespace App\Mail;

use App\Models\MessageContact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Accusé de réception envoyé au visiteur ayant soumis le formulaire de contact
 * — rassure sur la bonne réception sans dévoiler d'information médicale
 * (règle métier n°8 : ce mail ne contient jamais le contenu du message).
 */
class AccuseReceptionContactMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly MessageContact $messageContact)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nous avons bien reçu votre message — Clinique Croix Bleue',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.accuse-reception-contact',
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
