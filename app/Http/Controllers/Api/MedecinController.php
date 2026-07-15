<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Medecin;
use App\Services\CreneauxCalculator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedecinController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Medecin::with(['user:id,prenom,nom', 'specialite']);

        if ($request->filled('specialite_id')) {
            $query->where('specialite_id', $request->integer('specialite_id'));
        }

        return response()->json([
            'data' => $query->get(),
            'message' => 'Liste des médecins.',
        ]);
    }

    public function creneaux(Request $request, Medecin $medecin, CreneauxCalculator $calculator): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ], [
            'date.required' => 'La date est obligatoire (format AAAA-MM-JJ).',
            'date.date_format' => 'La date doit être au format AAAA-MM-JJ.',
        ]);

        // Dates manipulées en UTC — voir la note de fuseau horaire dans CreneauxCalculator.
        $date = Carbon::createFromFormat('Y-m-d', $request->query('date'), 'UTC')->startOfDay();

        return response()->json([
            'data' => $calculator->pourJour($medecin, $date),
            'message' => 'Créneaux du '.$date->toDateString().'.',
        ]);
    }
}
