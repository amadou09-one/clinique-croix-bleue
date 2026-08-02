<?php

use App\Http\Controllers\RdvValidationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Route publique (pas d'auth:sanctum) : le médecin y arrive en cliquant sur le lien
// signé reçu par e-mail, jamais via l'app authentifiée.
Route::get('/rdv/{rendezVous}/validation', [RdvValidationController::class, 'traiter'])
    ->middleware('signed')
    ->name('rdv.validation');
