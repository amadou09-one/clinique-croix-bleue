<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Specialite;
use Illuminate\Http\JsonResponse;

class SpecialiteController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Specialite::orderBy('nom')->get(),
            'message' => 'Liste des spécialités.',
        ]);
    }
}
