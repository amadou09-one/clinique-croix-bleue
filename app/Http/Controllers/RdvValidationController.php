<?php

namespace App\Http\Controllers;

use App\Mail\RdvDecisionPatient;
use App\Models\RendezVous;
use App\Notifications\RdvConfirmeNotification;
use App\Notifications\RdvRefuseNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class RdvValidationController extends Controller
{
    /**
     * Traite le clic du médecin sur le lien signé "Accepter" / "Refuser" reçu par e-mail.
     *
     * Statuts réutilisés tels quels (voir DATA-DICTIONARY, aucune valeur ajoutée à l'enum) :
     * "accepter" → statut = confirme ; "refuser" → statut = annule (avec motif_annulation
     * explicite "Refusé par le médecin.") faute d'un statut "refuse" dédié dans le schéma.
     */
    public function traiter(Request $request, RendezVous $rendezVous): View
    {
        $action = $request->query('action');

        if (! in_array($action, ['accepter', 'refuser'], true)) {
            return $this->vueResultat('⚠️', 'Lien invalide', "Ce lien de validation n'est pas reconnu.");
        }

        $rendezVous->loadMissing(['patient', 'medecin.user', 'medecin.specialite']);

        if ($rendezVous->statut !== 'en_attente') {
            return $this->vueResultat(
                'ℹ️',
                'Ce rendez-vous a déjà été traité',
                'Aucune action supplémentaire n\'est nécessaire.',
                $this->details($rendezVous, $this->libelleStatut($rendezVous->statut))
            );
        }

        $accepte = $action === 'accepter';

        if ($accepte) {
            $rendezVous->update(['statut' => 'confirme']);
        } else {
            $rendezVous->update([
                'statut' => 'annule',
                'annule_le' => now(),
                'motif_annulation' => 'Refusé par le médecin.',
            ]);
        }

        // Le mail est conditionné à la préférence notif_email_rdv du patient (Paramètres),
        // mais la notification in-app (cloche) est créée dans tous les cas : ce sont deux
        // canaux indépendants, désactiver l'un ne doit pas faire disparaître l'autre.
        $envoyerEmail = $rendezVous->patient->notif_email_rdv;

        if ($envoyerEmail) {
            try {
                Mail::to($rendezVous->patient->email)->send(new RdvDecisionPatient($rendezVous, $accepte));
            } catch (Throwable $e) {
                Log::warning('Échec de l\'envoi de l\'e-mail de décision de RDV.', [
                    'rendez_vous_id' => $rendezVous->id,
                    'erreur' => $e->getMessage(),
                ]);
            }
        }

        $canal = $envoyerEmail ? 'email' : 'in_app';

        if ($accepte) {
            RdvConfirmeNotification::creer($rendezVous, $canal);
        } else {
            RdvRefuseNotification::creer($rendezVous, $canal);
        }

        return $accepte
            ? $this->vueResultat('✅', 'Rendez-vous confirmé', 'Le patient a été informé par e-mail.', $this->details($rendezVous, 'Confirmé'))
            : $this->vueResultat('🚫', 'Rendez-vous refusé', 'Le patient a été informé par e-mail.', $this->details($rendezVous, 'Refusé'));
    }

    /**
     * @return array<string, string>
     */
    private function details(RendezVous $rendezVous, string $statutLabel): array
    {
        return [
            'Patient' => $rendezVous->patient->prenom.' '.$rendezVous->patient->nom,
            'Date' => ucfirst($rendezVous->date_heure->locale('fr')->translatedFormat('l j F Y')),
            'Heure' => $rendezVous->date_heure->format('H \h i'),
            'Statut' => $statutLabel,
        ];
    }

    private function libelleStatut(string $statut): string
    {
        return match ($statut) {
            'confirme' => 'Confirmé',
            'annule' => 'Annulé / refusé',
            'honore' => 'Honoré',
            'absent' => 'Absent',
            default => $statut,
        };
    }

    /**
     * @param  array<string, string>  $details
     */
    private function vueResultat(string $icone, string $titre, string $message, array $details = []): View
    {
        return view('rdv.resultat', compact('icone', 'titre', 'message', 'details'));
    }
}
