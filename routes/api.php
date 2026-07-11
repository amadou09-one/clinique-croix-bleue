<?php

use App\Http\Controllers\Api\AuthController;
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
});
