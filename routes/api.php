<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BlocageController;
use App\Http\Controllers\Api\DisponibiliteController;
use App\Http\Controllers\Api\MedecinController;
use App\Http\Controllers\Api\MedecinDashboardController;
use App\Http\Controllers\Api\MedecinPatientController;
use App\Http\Controllers\Api\MedecinProfilController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfilController;
use App\Http\Controllers\Api\RendezVousController;
use App\Http\Controllers\Api\SecretaireController;
use App\Http\Controllers\Api\SpecialiteController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

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

    Route::middleware('role:admin')->get('/admin/ping', function () {
        return response()->json([
            'data' => null,
            'message' => 'pong admin',
        ]);
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

        Route::put('/medecin/profil', [MedecinProfilController::class, 'update']);
    });

    Route::middleware('role:secretaire')->group(function () {
        Route::get('/secretaire/stats', [SecretaireController::class, 'stats']);
        Route::get('/secretaire/planning', [SecretaireController::class, 'planning']);
        Route::patch('/rendez-vous/{rendezVous}/confirmer', [RendezVousController::class, 'confirmer']);
    });

    Route::get('/specialites', [SpecialiteController::class, 'index']);
    Route::get('/medecins', [MedecinController::class, 'index']);
    Route::get('/medecins/{medecin}/creneaux', [MedecinController::class, 'creneaux']);

    Route::middleware('role:patient')->group(function () {
        Route::post('/rendez-vous', [RendezVousController::class, 'store']);
        Route::get('/mes-rendez-vous', [RendezVousController::class, 'mesRendezVous']);
        Route::patch('/rendez-vous/{rendezVous}/annuler', [RendezVousController::class, 'annuler']);

        Route::get('/patient/notifications', [NotificationController::class, 'index']);
        Route::get('/patient/notifications/non-lues/count', [NotificationController::class, 'nonLuesCount']);
        Route::put('/patient/notifications/tout-lu', [NotificationController::class, 'toutMarquerLu']);
        Route::put('/patient/notifications/{notification}/lue', [NotificationController::class, 'marquerLue']);
    });
});
