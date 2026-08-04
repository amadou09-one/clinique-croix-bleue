<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUtilisateurAdminRequest;
use App\Http\Requests\UpdateUtilisateurAdminRequest;
use App\Mail\CompteProCreeMail;
use App\Mail\PatientCompteCreeMail;
use App\Models\Medecin;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AdminUtilisateurController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with('medecin.specialite');

        if ($request->filled('role')) {
            $query->where('role', $request->query('role'));
        }

        if ($request->filled('statut')) {
            $query->where('est_actif', $request->query('statut') === 'actif');
        }

        if ($request->filled('recherche')) {
            $q = $request->query('recherche');
            $query->where(function ($sub) use ($q) {
                $sub->where('prenom', 'like', "%{$q}%")
                    ->orWhere('nom', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $utilisateurs = $query->orderBy('nom')->paginate(15)->withQueryString();

        return response()->json([
            'data' => $utilisateurs,
            'message' => 'Utilisateurs récupérés.',
        ]);
    }

    /**
     * Seul point d'entrée permettant de créer un compte medecin/secretaire/admin
     * (règle métier n°6) — un mot de passe aléatoire est généré et jamais transmis
     * en clair, un lien de définition de mot de passe est envoyé par e-mail (même
     * mécanisme que SecretairePatientController).
     */
    public function store(StoreUtilisateurAdminRequest $request): JsonResponse
    {
        $data = $request->validated();

        $utilisateur = User::create([
            'prenom' => $data['prenom'],
            'nom' => $data['nom'],
            'email' => $data['email'],
            'telephone' => $data['telephone'],
            'role' => $data['role'],
            'date_naissance' => $data['date_naissance'] ?? null,
            'sexe' => $data['sexe'] ?? null,
            'password' => Hash::make(Str::random(40)),
        ]);

        if ($data['role'] === 'medecin') {
            Medecin::create([
                'user_id' => $utilisateur->id,
                'specialite_id' => $data['specialite_id'],
                'titre' => $data['titre'] ?? null,
                'annees_experience' => $data['annees_experience'],
            ]);
        }

        $token = Password::broker()->createToken($utilisateur);
        $urlDefinirMotDePasse = rtrim(config('app.frontend_url'), '/').'/definir-mot-de-passe'
            .'?email='.urlencode($utilisateur->email).'&token='.$token;

        $mail = $data['role'] === 'patient'
            ? new PatientCompteCreeMail($utilisateur, $urlDefinirMotDePasse)
            : new CompteProCreeMail($utilisateur, $urlDefinirMotDePasse);

        Mail::to($utilisateur->email)->send($mail);

        return response()->json([
            'data' => $utilisateur->fresh(['medecin.specialite']),
            'message' => 'Compte créé avec succès. Un e-mail a été envoyé pour définir le mot de passe.',
        ], 201);
    }

    public function update(UpdateUtilisateurAdminRequest $request, User $utilisateur): JsonResponse
    {
        $data = $request->validated();

        $utilisateur->update([
            'prenom' => $data['prenom'],
            'nom' => $data['nom'],
            'email' => $data['email'],
            'telephone' => $data['telephone'],
            'role' => $data['role'],
            'est_actif' => $data['est_actif'],
            'date_naissance' => $data['date_naissance'] ?? null,
            'sexe' => $data['sexe'] ?? null,
        ]);

        if ($data['role'] === 'medecin') {
            Medecin::updateOrCreate(
                ['user_id' => $utilisateur->id],
                [
                    'specialite_id' => $data['specialite_id'],
                    'titre' => $data['titre'] ?? null,
                    'annees_experience' => $data['annees_experience'],
                ]
            );
        }

        return response()->json([
            'data' => $utilisateur->fresh(['medecin.specialite']),
            'message' => 'Utilisateur mis à jour.',
        ]);
    }

    /**
     * Désactivation uniquement (soft delete) : est_actif passe à false, jamais de
     * suppression physique — un utilisateur peut avoir des rendez_vous, dossiers
     * médicaux ou traitements liés qui ne doivent jamais disparaître.
     */
    public function destroy(Request $request, User $utilisateur): JsonResponse
    {
        if ($utilisateur->id === $request->user()->id) {
            return response()->json([
                'data' => null,
                'message' => 'Vous ne pouvez pas désactiver votre propre compte.',
            ], 403);
        }

        $utilisateur->update(['est_actif' => false]);

        return response()->json([
            'data' => $utilisateur->fresh(),
            'message' => 'Compte désactivé.',
        ]);
    }
}
