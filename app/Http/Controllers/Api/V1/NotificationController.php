<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends BaseApiController
{
    /**
     * GET /api/v1/notifications
     * Liste paginée des notifications de l'utilisateur connecté.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filtre = $request->input('filtre', 'toutes'); // toutes | non_lues

        $query = $user->notifications();

        if ($filtre === 'non_lues') {
            $query->whereNull('read_at');
        }

        $notifications = $query
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'data' => $notifications->items(),
            'meta' => [
                'total'       => $notifications->total(),
                'non_lues'    => $user->unreadNotifications()->count(),
                'current_page'=> $notifications->currentPage(),
                'last_page'   => $notifications->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/notifications/compteur
     * Nombre de notifications non lues — appelé fréquemment par le frontend.
     */
    public function compteur(Request $request): JsonResponse
    {
        return $this->success([
            'non_lues' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * POST /api/v1/notifications/{id}/lire
     * Marquer une notification comme lue.
     */
    public function marquerLue(Request $request, string $id): JsonResponse
    {
        $notif = $request->user()->notifications()->findOrFail($id);
        $notif->markAsRead();
        return $this->success(null, 'Notification marquée comme lue.');
    }

    /**
     * POST /api/v1/notifications/tout-lire
     * Marquer toutes les notifications non lues.
     */
    public function toutLire(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);
        return $this->success(null, 'Toutes les notifications ont été lues.');
    }

    /**
     * DELETE /api/v1/notifications/{id}
     * Supprimer une notification.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->findOrFail($id)->delete();
        return $this->success(null, 'Notification supprimée.');
    }

    /**
     * DELETE /api/v1/notifications
     * Supprimer toutes les notifications lues.
     */
    public function viderLues(Request $request): JsonResponse
    {
        $request->user()->readNotifications()->delete();
        return $this->success(null, 'Notifications lues supprimées.');
    }
}
