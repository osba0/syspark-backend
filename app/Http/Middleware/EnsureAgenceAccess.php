<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAgenceAccess
{
    /**
     * Injecte automatiquement le filtre d'agence sur toutes les requêtes.
     *
     * - super_admin et directeur voient TOUTES les agences
     * - resp_agence, chauffeur, attributaire, comptable sont scopés à leur agence
     * - resp_parc voit toutes les agences aussi
     *
     * Usage : ajouter 'agence.scope' aux routes API globales
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Rôles avec accès global (toutes agences)
        $rolesGlobaux = ['super_admin', 'directeur', 'resp_parc', 'comptable'];

        if (!$user->hasAnyRole($rolesGlobaux)) {
            // Scoper à l'agence de l'utilisateur
            // On injecte l'agence_id dans la requête pour les controllers
            if ($user->agence_id) {
                $request->merge(['_agence_scope' => $user->agence_id]);
            }
        }

        return $next($request);
    }
}
