<?php

namespace App\Notifications;

use App\Models\Notification;
use App\Models\RendezVous;

/**
 * Réutilise le type "annulation" de l'enum existant : un refus médecin n'a pas de valeur
 * dédiée dans le schéma (mêmes contraintes que `rendez_vous.statut`, voir DATA-DICTIONARY),
 * et correspond bien du point de vue du patient à une annulation de son rendez-vous.
 */
class RdvRefuseNotification
{
    public static function creer(RendezVous $rendezVous, string $canal): Notification
    {
        return Notification::create([
            'user_id' => $rendezVous->patient_id,
            'rendez_vous_id' => $rendezVous->id,
            'type' => 'annulation',
            'canal' => $canal,
            'contenu' => 'Votre rendez-vous du '.$rendezVous->date_heure->format('d/m/Y à H:i')
                .' a été refusé par le médecin.',
            'envoye_le' => now(),
        ]);
    }
}
