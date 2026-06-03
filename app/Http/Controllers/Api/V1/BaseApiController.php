<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Classe de base pour tous les controllers API V1.
 * Fournit les helpers communs : scope agence, pagination, réponses JSON.
 */
abstract class BaseApiController extends Controller
{
     use AuthorizesRequests;
    // ============================================================
    // Scope multi-agence
    // ============================================================

    /**
     * Retourne l'agence_id à utiliser pour filtrer les données.
     * - super_admin/directeur/resp_parc/comptable : null (toutes agences)
     * - resp_agence/chauffeur/attributaire : agence de l'utilisateur
     */
    protected function getAgenceScopeId(Request $request): ?int
    {
        return $request->input('_agence_scope'); // injecté par EnsureAgenceAccess
    }

    /**
     * Applique le scope agence sur une query Eloquent.
     */
    protected function applyAgenceScope($query, Request $request): mixed
    {
        $agenceId = $this->getAgenceScopeId($request);
        if ($agenceId) {
            $query->where('agence_id', $agenceId);
        }
        return $query;
    }

    // ============================================================
    // Réponses JSON standardisées
    // ============================================================

    protected function success(mixed $data, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    protected function created(mixed $data, string $message = 'Créé avec succès.'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function noContent(string $message = 'Supprimé avec succès.'): JsonResponse
    {
        return response()->json(['message' => $message], 200);
    }

    protected function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        $payload = ['message' => $message];
        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }
        return response()->json($payload, $status);
    }

    protected function forbidden(string $message = 'Accès refusé.'): JsonResponse
    {
        return $this->error($message, 403);
    }

    protected function notFound(string $message = 'Ressource non trouvée.'): JsonResponse
    {
        return $this->error($message, 404);
    }

    // ============================================================
    // Pagination helper
    // ============================================================

    protected function perPage(Request $request): int
    {
        $max = config('parc.pagination.per_page_max', 100);
        $default = config('parc.pagination.per_page_defaut', 25);
        return min((int)$request->input('per_page', $default), $max);
    }
}
