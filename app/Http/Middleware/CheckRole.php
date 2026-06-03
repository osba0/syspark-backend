<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CheckRole — Middleware de vérification de rôle Spatie.
 *
 * À utiliser OBLIGATOIREMENT à la place du middleware Spatie 'role:'.
 *
 * POURQUOI :
 *   Le middleware Spatie RoleMiddleware 'role:A,B' interprète le 2ème paramètre
 *   séparé par virgule comme un guard dans certaines versions (v6+).
 *   Cela produit l'erreur : "Auth guard [directeur] is not defined."
 *
 *   Ce middleware appelle directement hasAnyRole() qui est guard-agnostic
 *   et utilise config('permission.guard_name') = 'web' pour résoudre les rôles.
 *
 * USAGE dans les routes :
 *   ->middleware('check.role:super_admin,directeur,resp_parc')
 *
 * CONVENTION du projet :
 *   ✅ 'check.role:role1,role2'          → utiliser dans toutes les routes
 *   ❌ 'role:role1,role2'                → NE PAS UTILISER (middleware Spatie)
 *   ❌ 'role:role1|role2'                → NE PAS UTILISER
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        if (!$request->user()->hasAnyRole($roles)) {
            return response()->json([
                'message'        => 'Accès refusé. Rôle insuffisant.',
                'required_roles' => $roles,
            ], 403);
        }

        return $next($request);
    }
}