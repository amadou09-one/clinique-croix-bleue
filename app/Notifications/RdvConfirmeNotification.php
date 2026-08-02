<?php

namespace App\Notifications;

use App\Models\Notification;
use App\Models\RendezVous;

/**
 * Une table `notifications` (App\Models\Notification) existe déjà dans le projet — créée
 * pour l'audit des e-mails d'annulation/rappel, avec son propre schéma (type/canal/contenu).
 * Pour éviter toute collision de nom avec la table native de `php artisan notifications:table`
 * (schéma incompatible : uuid, notifiable_type/id, data JSON), ces classes réutilisent le
 * modèle et la table existants plutôt que Illuminate\Notifications\Notification.
 */
class RdvConfirmeNotification
{
    public static function creer(RendezVous $rendezVous, string $canal): Notification
    {
        return Notification::create([
            'user_id' => $rendezVous->patient_id,
            'rendez_vous_id' => $rendezVous->id,
            'type' => 'confirmation',
            'canal' => $canal,
            'contenu' => 'Votre rendez-vous du '.$rendezVous->date_heure->format('d/m/Y à H:i')
                .' avec Dr '.$rendezVous->medecin->user->nom.' a été confirmé.',
            'envoye_le' => now(),
        ]);
    }
}
