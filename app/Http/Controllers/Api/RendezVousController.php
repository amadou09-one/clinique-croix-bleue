<?php

namespace App\Http\Controllers\Api;

use App\Events\RendezVousAnnule;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRendezVousRequest;
use App\Http\Requests\UpdateStatutRendezVousRequest;
use App\Mail\RdvConfirmationPatient;
use App\Mail\RdvDecisionPatient;
use App\Mail\RdvDemandeValidationMedecin;
use App\Models\Medecin;
use App\Models\RendezVous;
use App\Models\User;
use App\Notifications\RdvConfirmeNotification;
use App\Services\CreneauxCalculator;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Throwable;

class RendezVousController extends Controller
{
    public function __construct(private readonly CreneauxCalculator $calculator)
    {
    }

    /**
     * Création d'un rendez-vous — par le patient lui-même, ou par une secrétaire
     * au comptoir pour un patient tiers (patient_id explicite dans ce cas).
     * Toute la logique de validité du créneau est recalculée côté serveur —
     * on ne fait jamais confiance à ce que le client prétend disponible.
     *
     * Sécurité : patient_id envoyé par un PATIENT est totalement ignoré — un
     * patient authentifié ne peut jamais réserver "pour" un autre patient_id,
     * quoi qu'il envoie dans le corps de la requête.
     */
    public function store(StoreRendezVousRequest $request): JsonResponse
    {
        $data = $request->validated();
        $medecin = Medecin::findOrFail($data['medecin_id']);
        $dateHeure = Carbon::parse($data['date_heure']);
        $acteur = $request->user();

        if ($acteur->role === 'secretaire') {
            $patient = User::where('id', $data['patient_id'])->where('role', 'patient')->first();

            if (! $patient) {
                throw ValidationException::withMessages([
                    'patient_id' => ['Ce patient est introuvable.'],
                ]);
            }
        } else {
            $patient = $acteur;
        }

        if ($dateHeure->lte(Carbon::now())) {
            throw ValidationException::withMessages([
                'date_heure' => ['Impossible de réserver un créneau dans le passé.'],
            ]);
        }

        if (! $this->calculator->estCreneauValide($medecin, $dateHeure)) {
            throw ValidationException::withMessages([
                'date_heure' => ["Ce créneau n'est pas disponible chez ce médecin."],
            ]);
        }

        $creneauPris = RendezVous::where('medecin_id', $medecin->id)
            ->where('date_heure', $dateHeure)
            ->where('statut', '!=', 'annule')
            ->exists();

        if ($creneauPris) {
            throw ValidationException::withMessages([
                'date_heure' => ['Ce créneau vient d\'être réservé par un autre patient.'],
            ]);
        }

        $memeJourActif = RendezVous::where('medecin_id', $medecin->id)
            ->where('patient_id', $patient->id)
            ->whereDate('date_heure', $dateHeure->toDateString())
            ->whereIn('statut', ['en_attente', 'confirme'])
            ->exists();

        if ($memeJourActif) {
            throw ValidationException::withMessages([
                'date_heure' => ['Vous avez déjà un rendez-vous actif avec ce médecin ce jour-là.'],
            ]);
        }

        try {
            $rendezVous = RendezVous::create([
                'patient_id' => $patient->id,
                'medecin_id' => $medecin->id,
                'date_heure' => $dateHeure,
                'duree_min' => $this->calculator->dureeCreneau($medecin, $dateHeure),
                'statut' => 'en_attente',
                'motif' => $data['motif'] ?? null,
                'cree_par' => $acteur->id,
            ]);
        } catch (QueryException $e) {
            // Filet de sécurité : la contrainte UNIQUE(medecin_id, date_heure) protège
            // contre une réservation concurrente passée entre les vérifications ci-dessus
            // et l'insertion (deux requêtes quasi simultanées sur le même créneau).
            throw ValidationException::withMessages([
                'date_heure' => ['Ce créneau vient d\'être réservé par un autre patient.'],
            ]);
        }

        $urlAccepter = URL::temporarySignedRoute('rdv.validation', now()->addHours(48), [
            'rendezVous' => $rendezVous->id,
            'action' => 'accepter',
        ]);
        $urlRefuser = URL::temporarySignedRoute('rdv.validation', now()->addHours(48), [
            'rendezVous' => $rendezVous->id,
            'action' => 'refuser',
        ]);

        // En file d'attente (jobs table) pour ne jamais bloquer la réponse HTTP sur
        // l'envoi d'e-mail — nécessite qu'un worker tourne (`php artisan queue:work`)
        // pour être effectivement délivré. Les Mailables re-chargent leurs relations
        // eux-mêmes au moment de l'envoi (SerializesModels ne conserve pas l'eager load).
        // Le mail au médecin n'est pas concerné par la préférence notif_email_rdv du
        // patient : c'est une notification destinée au médecin, pas à lui.
        if ($patient->notif_email_rdv) {
            Mail::to($patient->email)->later(now(), new RdvConfirmationPatient($rendezVous));
        }
        Mail::to($medecin->loadMissing('user')->user->email)
            ->later(now(), new RdvDemandeValidationMedecin($rendezVous, $urlAccepter, $urlRefuser));

        return response()->json([
            'data' => $rendezVous->load(['medecin.user:id,prenom,nom', 'medecin.specialite']),
            'message' => 'Rendez-vous créé avec succès.',
        ], 201);
    }

    /**
     * Rendez-vous du patient authentifié, séparés en à venir / passés.
     */
    public function mesRendezVous(Request $request): JsonResponse
    {
        $patient = $request->user();
        $maintenant = Carbon::now();

        $rendezVous = RendezVous::with(['medecin.user:id,prenom,nom', 'medecin.specialite'])
            ->where('patient_id', $patient->id)
            ->orderBy('date_heure')
            ->get();

        return response()->json([
            'data' => [
                'a_venir' => $rendezVous
                    ->filter(fn (RendezVous $r) => $r->date_heure->gt($maintenant) && $r->statut !== 'annule')
                    ->values(),
                'passes' => $rendezVous
                    ->filter(fn (RendezVous $r) => $r->date_heure->lte($maintenant) || $r->statut === 'annule')
                    ->values(),
            ],
            'message' => 'Vos rendez-vous.',
        ]);
    }

    /**
     * Annulation par le patient propriétaire uniquement, jusqu'à 6 h avant le RDV.
     */
    public function annuler(Request $request, RendezVous $rendezVous): JsonResponse
    {
        $patient = $request->user();

        if ($rendezVous->patient_id !== $patient->id) {
            return response()->json([
                'data' => null,
                'message' => 'Vous ne pouvez annuler que vos propres rendez-vous.',
            ], 403);
        }

        if ($rendezVous->statut === 'annule') {
            throw ValidationException::withMessages([
                'date_heure' => ['Ce rendez-vous est déjà annulé.'],
            ]);
        }

        $limiteAnnulation = $rendezVous->date_heure->copy()->subHours(6);

        if (Carbon::now()->gte($limiteAnnulation)) {
            throw ValidationException::withMessages([
                'date_heure' => ["L'annulation n'est plus possible moins de 6 heures avant le rendez-vous."],
            ]);
        }

        $rendezVous->update([
            'statut' => 'annule',
            'annule_le' => Carbon::now(),
            'motif_annulation' => $request->input('motif_annulation'),
        ]);

        event(new RendezVousAnnule($rendezVous));

        return response()->json([
            'data' => $rendezVous->fresh(['medecin.user:id,prenom,nom', 'medecin.specialite']),
            'message' => 'Rendez-vous annulé.',
        ]);
    }

    /**
     * Le médecin marque un RDV honoré ou absent — uniquement sur ses propres RDV,
     * et uniquement depuis un statut non final (en_attente/confirme).
     */
    public function changerStatut(UpdateStatutRendezVousRequest $request, RendezVous $rendezVous): JsonResponse
    {
        $medecin = $request->user()->medecin;

        if (! $medecin || $rendezVous->medecin_id !== $medecin->id) {
            return response()->json([
                'data' => null,
                'message' => 'Vous ne pouvez modifier que vos propres rendez-vous.',
            ], 403);
        }

        if (! in_array($rendezVous->statut, ['en_attente', 'confirme'], true)) {
            throw ValidationException::withMessages([
                'statut' => ['Ce rendez-vous est déjà dans un état final ('.$rendezVous->statut.') et ne peut plus être modifié.'],
            ]);
        }

        $rendezVous->update(['statut' => $request->validated()['statut']]);

        return response()->json([
            'data' => $rendezVous->fresh(['patient:id,prenom,nom']),
            'message' => 'Statut du rendez-vous mis à jour.',
        ]);
    }

    /**
     * Le secrétariat confirme un RDV en_attente au comptoir/téléphone — réutilise
     * exactement le même e-mail et la même notification que lorsque le médecin
     * accepte via le lien signé (voir RdvValidationController).
     */
    public function confirmer(Request $request, RendezVous $rendezVous): JsonResponse
    {
        if ($rendezVous->statut !== 'en_attente') {
            return response()->json([
                'data' => null,
                'message' => "Ce rendez-vous n'est pas en attente de confirmation.",
            ], 403);
        }

        $rendezVous->update(['statut' => 'confirme']);
        $rendezVous->loadMissing(['patient', 'medecin.user', 'medecin.specialite']);

        $envoyerEmail = $rendezVous->patient->notif_email_rdv;

        if ($envoyerEmail) {
            try {
                Mail::to($rendezVous->patient->email)->send(new RdvDecisionPatient($rendezVous, true));
            } catch (Throwable $e) {
                Log::warning('Échec de l\'envoi de l\'e-mail de confirmation de RDV (secrétariat).', [
                    'rendez_vous_id' => $rendezVous->id,
                    'erreur' => $e->getMessage(),
                ]);
            }
        }

        RdvConfirmeNotification::creer($rendezVous, $envoyerEmail ? 'email' : 'in_app');

        return response()->json([
            'data' => $rendezVous->fresh(['patient:id,prenom,nom', 'medecin.user:id,prenom,nom', 'medecin.specialite']),
            'message' => 'Rendez-vous confirmé.',
        ]);
    }
}
