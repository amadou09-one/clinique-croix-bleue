<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MedecinController;
use App\Http\Controllers\Api\RendezVousController;
use App\Http\Controllers\Api\SpecialiteController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::middleware('role:admin')->get('/admin/ping', function () {
        return response()->json([
            'data' => null,
            'message' => 'pong admin',
        ]);
    });

    Route::middleware('role:medecin')->get('/medecin/ping', function () {
        return response()->json([
            'data' => null,
            'message' => 'pong medecin',
        ]);
    });

    Route::get('/specialites', [SpecialiteController::class, 'index']);
    Route::get('/medecins', [MedecinController::class, 'index']);
    Route::get('/medecins/{medecin}/creneaux', [MedecinController::class, 'creneaux']);

    Route::middleware('role:patient')->group(function () {
        Route::post('/rendez-vous', [RendezVousController::class, 'store']);
        Route::get('/mes-rendez-vous', [RendezVousController::class, 'mesRendezVous']);
        Route::patch('/rendez-vous/{rendezVous}/annuler', [RendezVousController::class, 'annuler']);
    });
});
