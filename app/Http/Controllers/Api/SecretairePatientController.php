<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientSecretaireRequest;
use App\Mail\PatientCompteCreeMail;
use App\Models\DossierMedical;
use App\Models\RendezVous;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class SecretairePatientController extends Controller
{
    /**
     * Résultats courts pour la recherche "au fil de la frappe" du formulaire
     * de création de RDV au comptoir.
     */
    public function recherche(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2'],
        ], [
            'q.required' => 'Le terme de recherche est obligatoire.',
            'q.min' => 'Le terme de recherche doit contenir au moins 2 caractères.',
        ]);

        $q = $request->query('q');

        $patients = User::where('role', 'patient')
            ->where(function ($query) use ($q) {
                $query->where('prenom', 'like', "%{$q}%")
                    ->orWhere('nom', 'like', "%{$q}%")
                    ->orWhere('telephone', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            })
            ->orderBy('prenom')
            ->limit(10)
            ->get(['id', 'prenom', 'nom', 'telephone', 'email']);

        $dossiers = DossierMedical::whereIn('patient_id', $patients->pluck('id'))
            ->get()
            ->keyBy('patient_id');

        $patients = $patients->map(fn (User $p) => [
            'id' => $p->id,
            'prenom' => $p->prenom,
            'nom' => $p->nom,
            'telephone' => $p->telephone,
            'email' => $p->email,
            'groupe_sanguin' => $dossiers->get($p->id)?->groupe_sanguin,
        ]);

        return response()->json([
            'data' => $patients,
            'message' => 'Résultats de recherche.',
        ]);
    }

    /**
     * Liste paginée de tous les patients enregistrés à la clinique.
     */
    public function index(Request $request): JsonResponse
    {
        $maintenant = Carbon::now();

        $query = User::where('role', 'patient');

        if ($request->filled('q')) {
            $q = $request->query('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('prenom', 'like', "%{$q}%")
                    ->orWhere('nom', 'like', "%{$q}%")
                    ->orWhere('telephone', 'like', "%{$q}%");
            });
        }

        $tri = $request->query('tri', 'nom');
        if (in_array($tri, ['nom', 'prenom', 'created_at'], true)) {
            $query->orderBy($tri);
        }

        $patients = $query->paginate(15)->withQueryString();

        $rendezVousParPatient = RendezVous::whereIn('patient_id', $patients->pluck('id'))
            ->where('statut', '!=', 'annule')
            ->with('medecin.user:id,prenom,nom')
            ->get()
            ->groupBy('patient_id');

        $patients->getCollection()->transform(function (User $p) use ($rendezVousParPatient, $maintenant) {
            $rdvs = $rendezVousParPatient->get($p->id, collect());
            $derniereVisite = $rdvs->where('statut', 'honore')->sortByDesc('date_heure')->first();
            $prochainRdv = $rdvs
                ->filter(fn (RendezVous $r) => $r->date_heure->gt($maintenant) && in_array($r->statut, ['confirme', 'en_attente'], true))
                ->sortBy('date_heure')
                ->first();

            return [
                'id' => $p->id,
                'prenom' => $p->prenom,
                'nom' => $p->nom,
                'age' => $p->date_naissance?->age,
                'telephone' => $p->telephone,
                'derniere_visite' => $derniereVisite?->date_heure,
                'prochain_rdv' => $prochainRdv?->date_heure,
            ];
        });

        return response()->json([
            'data' => $patients,
            'message' => 'Patients récupérés.',
        ]);
    }

    /**
     * Enregistrement d'un nouveau patient au comptoir. Aucun mot de passe n'est
     * jamais transmis en clair : un mot de passe aléatoire (inutilisable tant
     * que le patient n'en a pas défini un lui-même) est généré et hashé, et un
     * lien de définition de mot de passe (token du broker Laravel, 7 jours) est
     * envoyé par e-mail.
     */
    public function store(StorePatientSecretaireRequest $request): JsonResponse
    {
        $data = $request->validated();

        $patient = User::create([
            'prenom' => $data['prenom'],
            'nom' => $data['nom'],
            'email' => $data['email'],
            'telephone' => $data['telephone'],
            'date_naissance' => $data['date_naissance'],
            'sexe' => $data['sexe'] ?? null,
            'password' => Hash::make(Str::random(40)),
            'role' => 'patient',
        ]);

        $token = Password::broker()->createToken($patient);
        $urlDefinirMotDePasse = rtrim(config('app.frontend_url'), '/').'/definir-mot-de-passe'
            .'?email='.urlencode($patient->email).'&token='.$token;

        Mail::to($patient->email)->send(new PatientCompteCreeMail($patient, $urlDefinirMotDePasse));

        return response()->json([
            'data' => $patient,
            'message' => 'Patient enregistré avec succès. Un e-mail lui a été envoyé pour définir son mot de passe.',
        ], 201);
    }
}
