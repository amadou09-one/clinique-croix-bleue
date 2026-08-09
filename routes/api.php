<?php

use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminUtilisateurController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BlocageController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DefinirMotDePasseController;
use App\Http\Controllers\Api\DisponibiliteController;
use App\Http\Controllers\Api\DocumentMedicalController;
use App\Http\Controllers\Api\MedecinConsultationController;
use App\Http\Controllers\Api\MedecinController;
use App\Http\Controllers\Api\MedecinDashboardController;
use App\Http\Controllers\Api\MedecinPatientController;
use App\Http\Controllers\Api\MedecinProfilController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PatientDossierController;
use App\Http\Controllers\Api\ProfilController;
use App\Http\Controllers\Api\RendezVousController;
use App\Http\Controllers\Api\SecretaireController;
use App\Http\Controllers\Api\SecretairePatientController;
use App\Http\Controllers\Api\SpecialiteController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/definir-mot-de-passe', [DefinirMotDePasseController::class, 'store']);

// Formulaire de contact du site vitrine — public, limité en fréquence pour éviter le spam.
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Profil et préférences : communs à tous les rôles authentifiés (chacun n'agit
    // que sur $request->user(), jamais sur un autre compte — pas de middleware role:
    // nécessaire ici).
    Route::get('/profil', [ProfilController::class, 'show']);
    Route::put('/profil', [ProfilController::class, 'update']);
    Route::put('/profil/mot-de-passe', [ProfilController::class, 'updatePassword']);
    Route::get('/preferences', [ProfilController::class, 'showPreferences']);
    Route::put('/preferences', [ProfilController::class, 'updatePreferences']);

    // Téléchargement d'un document médical : accessible au patient propriétaire
    // OU au médecin qui a déjà reçu ce patient — l'autorisation fine est faite
    // dans le contrôleur, pas via le middleware role (les deux rôles y accèdent).
    Route::get('/documents/{document}/telecharger', [DocumentMedicalController::class, 'telecharger']);

    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/ping', function () {
            return response()->json([
                'data' => null,
                'message' => 'pong admin',
            ]);
        });

        Route::get('/admin/stats', [AdminDashboardController::class, 'stats']);

        Route::get('/admin/utilisateurs', [AdminUtilisateurController::class, 'index']);
        Route::post('/admin/utilisateurs', [AdminUtilisateurController::class, 'store']);
        Route::put('/admin/utilisateurs/{utilisateur}', [AdminUtilisateurController::class, 'update']);
        Route::delete('/admin/utilisateurs/{utilisateur}', [AdminUtilisateurController::class, 'destroy']);
    });

    Route::middleware('role:medecin')->group(function () {
        Route::get('/medecin/ping', function () {
            return response()->json([
                'data' => null,
                'message' => 'pong medecin',
            ]);
        });

        Route::get('/medecin/agenda', [MedecinDashboardController::class, 'agenda']);
        Route::get('/medecin/stats', [MedecinDashboardController::class, 'stats']);
        Route::patch('/rendez-vous/{rendezVous}/statut', [RendezVousController::class, 'changerStatut']);

        Route::get('/medecin/disponibilites', [DisponibiliteController::class, 'index']);
        Route::put('/medecin/disponibilites', [DisponibiliteController::class, 'update']);

        Route::get('/medecin/blocages', [BlocageController::class, 'index']);
        Route::post('/medecin/blocages', [BlocageController::class, 'store']);
        Route::delete('/medecin/blocages/{blocage}', [BlocageController::class, 'destroy']);

        Route::get('/medecin/patients', [MedecinPatientController::class, 'index']);
        Route::get('/medecin/patients/{patient}', [MedecinPatientController::class, 'show']);
        Route::post('/medecin/patients/{patient}/traitements', [MedecinPatientController::class, 'storeTraitement']);
        Route::post('/medecin/patients/{patient}/documents', [MedecinPatientController::class, 'storeDocument']);

        Route::post('/medecin/rendez-vous/{rendezVous}/consultation', [MedecinConsultationController::class, 'store']);

        Route::put('/medecin/profil', [MedecinProfilController::class, 'update']);
    });

    Route::middleware('role:secretaire')->group(function () {
        Route::get('/secretaire/stats', [SecretaireController::class, 'stats']);
        Route::get('/secretaire/planning', [SecretaireController::class, 'planning']);
        Route::patch('/rendez-vous/{rendezVous}/confirmer', [RendezVousController::class, 'confirmer']);

        Route::get('/secretaire/patients/recherche', [SecretairePatientController::class, 'recherche']);
        Route::get('/secretaire/patients', [SecretairePatientController::class, 'index']);
        Route::post('/secretaire/patients', [SecretairePatientController::class, 'store']);
    });

    Route::get('/specialites', [SpecialiteController::class, 'index']);
    Route::get('/medecins', [MedecinController::class, 'index']);
    Route::get('/medecins/{medecin}/creneaux', [MedecinController::class, 'creneaux']);

    // La création de RDV est partagée patient/secrétaire (voir RendezVousController::store
    // pour la logique de résolution du patient cible et la sécurité anti-usurpation).
    Route::middleware('role:patient,secretaire')->post('/rendez-vous', [RendezVousController::class, 'store']);

    Route::middleware('role:patient')->group(function () {
        Route::get('/mes-rendez-vous', [RendezVousController::class, 'mesRendezVous']);
        Route::patch('/rendez-vous/{rendezVous}/annuler', [RendezVousController::class, 'annuler']);

        Route::get('/patient/dossier', [PatientDossierController::class, 'show']);

        Route::get('/patient/notifications', [NotificationController::class, 'index']);
        Route::get('/patient/notifications/non-lues/count', [NotificationController::class, 'nonLuesCount']);
        Route::put('/patient/notifications/tout-lu', [NotificationController::class, 'toutMarquerLu']);
        Route::put('/patient/notifications/{notification}/lue', [NotificationController::class, 'marquerLue']);
    });
});
