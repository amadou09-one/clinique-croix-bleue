<?php

namespace App\Listeners;

use App\Events\RendezVousAnnule;
use App\Mail\AnnulationRdvMail;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EnvoyerAnnulationRdvMail
{
    /**
     * Un échec d'envoi (SMTP en rate-limit, panne temporaire…) ne doit jamais faire
     * échouer l'annulation elle-même : capturé et journalisé, sans relancer.
     */
    public function handle(RendezVousAnnule $event): void
    {
        $rendezVous = $event->rendezVous->loadMissing(['patient', 'medecin.user', 'medecin.specialite']);

        // Le mail est conditionné à la préférence notif_email_rdv du patient (Paramètres) ;
        // la notification in-app (cloche) est, elle, toujours enregistrée, avec un canal
        // qui reflète honnêtement si l'e-mail a réellement été tenté ou non.
        $envoyerEmail = $rendezVous->patient->notif_email_rdv;

        if ($envoyerEmail) {
            try {
                Mail::to($rendezVous->patient->email)->send(new AnnulationRdvMail($rendezVous));
            } catch (Throwable $e) {
                Log::warning('Échec de l\'envoi de l\'e-mail d\'annulation de RDV.', [
                    'rendez_vous_id' => $rendezVous->id,
                    'erreur' => $e->getMessage(),
                ]);
            }
        }

        Notification::create([
            'user_id' => $rendezVous->patient_id,
            'rendez_vous_id' => $rendezVous->id,
            'type' => 'annulation',
            'canal' => $envoyerEmail ? 'email' : 'in_app',
            'contenu' => 'Annulation du rendez-vous du '.$rendezVous->date_heure->format('d/m/Y H:i'),
            'envoye_le' => now(),
        ]);
    }
}
