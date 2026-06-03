<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureAgenceAccess;
use App\Http\Middleware\LogApiActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // Middleware globaux API
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Alias de middleware custom
        $middleware->alias([
            'role'          => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'    => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'check.role'    => CheckRole::class,
            'agence.scope'  => EnsureAgenceAccess::class,
            'log.activity'  => LogApiActivity::class,
        ]);

        // Trusts tous les proxies en production (derrière Nginx)
        $middleware->trustProxies(at: '*');

    })
    ->withExceptions(function (Exceptions $exceptions) {

        // Toujours retourner du JSON pour les routes API
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // 404 en JSON pour l'API
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Ressource non trouvée.',
                ], 404);
            }
        });

        // 403 en JSON pour l'API
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Accès refusé. Permissions insuffisantes.',
                ], 403);
            }
        });

        // Erreurs de validation (422)
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Données invalides.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        // Token invalide ou expiré (401)
        $exceptions->render(function (\Laravel\Sanctum\Exceptions\MissingAbilityException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Token invalide ou expiré. Veuillez vous reconnecter.',
                ], 401);
            }
        });

    })
    ->create();
