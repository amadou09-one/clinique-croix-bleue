<?php

namespace App\Console\Commands;

use App\Mail\RappelRdvMail;
use App\Models\RendezVous;
use App\Notifications\RdvRappelNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EnvoyerRappelsRdv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rdv:envoyer-rappels';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Envoie un rappel par e-mail pour les rendez-vous en_attente/confirmé dans les prochaines 24h, sans doublon.";

    /**
     * Execute the console command.
     *
     * Idempotence : un rendez-vous n'est retenu que s'il n'a encore aucune notification
     * de type "rappel" enregistrée — relancer la commande plusieurs fois ne renvoie donc
     * jamais de rappel en double pour le même rendez-vous.
     */
    public function handle(): int
    {
        $maintenant = Carbon::now();
        $limite = $maintenant->copy()->addHours(24);

        $rendezVousAPrevenir = RendezVous::with(['patient', 'medecin.user', 'medecin.specialite'])
            ->whereIn('statut', ['en_attente', 'confirme'])
            ->whereBetween('date_heure', [$maintenant, $limite])
            ->whereDoesntHave('notifications', function ($query) {
                $query->where('type', 'rappel');
            })
            ->get();

        $envoyes = 0;

        foreach ($rendezVousAPrevenir as $rendezVous) {
            $envoyerEmail = $rendezVous->patient->notif_email_rappel;

            try {
                if ($envoyerEmail) {
                    Mail::to($rendezVous->patient->email)->send(new RappelRdvMail($rendezVous));
                }

                RdvRappelNotification::creer($rendezVous, $envoyerEmail ? 'email' : 'in_app');

                $envoyes++;
            } catch (Throwable $e) {
                // On journalise et on continue avec les rendez-vous suivants : l'échec du
                // rappel d'un patient ne doit pas empêcher l'envoi aux autres. Comme aucune
                // notification "rappel" n'est enregistrée, ce rendez-vous sera retenté au
                // prochain passage de la commande (idempotence naturelle en cas d'échec).
                Log::warning('Échec de l\'envoi d\'un rappel de RDV.', [
                    'rendez_vous_id' => $rendezVous->id,
                    'erreur' => $e->getMessage(),
                ]);
            }
        }

        $this->info($envoyes.' rappel(s) envoyé(s) sur '.$rendezVousAPrevenir->count().' rendez-vous éligible(s).');

        return self::SUCCESS;
    }
}
