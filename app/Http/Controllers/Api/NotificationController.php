<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return response()->json([
            'data' => $notifications,
            'message' => 'Notifications récupérées.',
        ]);
    }

    public function nonLuesCount(Request $request): JsonResponse
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->whereNull('lu_le')
            ->count();

        return response()->json([
            'data' => ['count' => $count],
            'message' => 'Nombre de notifications non lues récupéré.',
        ]);
    }

    public function marquerLue(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json([
                'data' => null,
                'message' => "Vous n'avez pas les droits nécessaires pour accéder à cette ressource.",
            ], 403);
        }

        if ($notification->lu_le === null) {
            $notification->update(['lu_le' => now()]);
        }

        return response()->json([
            'data' => $notification->fresh(),
            'message' => 'Notification marquée comme lue.',
        ]);
    }

    public function toutMarquerLu(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('lu_le')
            ->update(['lu_le' => now()]);

        return response()->json([
            'data' => null,
            'message' => 'Toutes les notifications ont été marquées comme lues.',
        ]);
    }
}
