<?php

namespace App\Notifications;

use App\Models\Notification;
use App\Models\RendezVous;

class RdvRappelNotification
{
    public static function creer(RendezVous $rendezVous, string $canal): Notification
    {
        return Notification::create([
            'user_id' => $rendezVous->patient_id,
            'rendez_vous_id' => $rendezVous->id,
            'type' => 'rappel',
            'canal' => $canal,
            'contenu' => 'Rappel : rendez-vous du '.$rendezVous->date_heure->format('d/m/Y à H:i').' dans moins de 24 h.',
            'envoye_le' => now(),
        ]);
    }
}
