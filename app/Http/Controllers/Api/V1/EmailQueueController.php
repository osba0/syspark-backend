<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\NotificationEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Consultation et administration de la file d'emails (audit).
 * Accessible aux Super Admin / Directeur.
 */
class EmailQueueController extends BaseApiController
{
    /**
     * GET /api/v1/admin/email-queue
     * Liste paginée avec filtre par statut.
     */
    public function index(Request $request): JsonResponse
    {
        $query = NotificationEmail::query()->with('user:id,name,prenom,email');

        if ($statut = $request->input('statut')) {
            $query->where('statut', $statut);
        }

        $emails = $query->orderByDesc('created_at')->paginate(25);

        return response()->json([
            'data' => $emails->items(),
            'meta' => [
                'total'        => $emails->total(),
                'current_page' => $emails->currentPage(),
                'last_page'    => $emails->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/email-queue/stats
     * Statistiques pour le dashboard admin.
     */
    public function stats(): JsonResponse
    {
        $base = NotificationEmail::query();

        return $this->success([
            'total'              => $base->count(),
            'pending'            => (clone $base)->where('statut', 'pending')->count(),
            'sent'               => (clone $base)->where('statut', 'sent')->count(),
            'failed'             => (clone $base)->where('statut', 'failed')->count(),
            'failed_definitif'   => (clone $base)
                ->where('statut', 'failed')
                ->whereColumn('tentatives', '>=', 'max_tentatives')
                ->count(),
            'envoyes_24h'        => (clone $base)
                ->where('statut', 'sent')
                ->where('sent_at', '>=', now()->subDay())
                ->count(),
            'taux_succes' => $this->tauxSucces($base),
        ]);
    }

    /**
     * POST /api/v1/admin/email-queue/{id}/relancer
     * Force la relance d'un email en échec définitif (réinitialise tentatives).
     */
    public function relancer(NotificationEmail $email): JsonResponse
    {
        $email->update([
            'statut'          => 'pending',
            'tentatives'      => 0,
            'next_attempt_at' => null,
            'last_error'      => null,
        ]);

        return $this->success(null, 'Email remis en file pour envoi.');
    }

    /**
     * DELETE /api/v1/admin/email-queue/purger-envoyes
     * Supprime les emails envoyés depuis plus de 90 jours (purge historique).
     */
    public function purgerEnvoyes(): JsonResponse
    {
        $nb = NotificationEmail::where('statut', 'sent')
            ->where('sent_at', '<', now()->subDays(90))
            ->delete();

        return $this->success(['supprimes' => $nb], "{$nb} email(s) archivé(s) supprimé(s).");
    }

    private function tauxSucces($base): float
    {
        $total   = (clone $base)->whereIn('statut', ['sent', 'failed'])->count();
        $envoyes = (clone $base)->where('statut', 'sent')->count();

        return $total > 0 ? round(($envoyes / $total) * 100, 1) : 100.0;
    }
}
