<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogApiActivity
{
    // Méthodes qui modifient des données (à logger)
    private const LOGGED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    // Routes à ne pas logger (trop verbeux)
    private const EXCLUDED_ROUTES = [
        'api/v1/auth/me',
        'api/v1/dashboard',
        'api/v1/alertes',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Logger uniquement les mutations importantes
        if (!in_array($request->method(), self::LOGGED_METHODS)) {
            return $response;
        }

        foreach (self::EXCLUDED_ROUTES as $route) {
            if (str_contains($request->path(), $route)) {
                return $response;
            }
        }

        // Ne logger que les succès (2xx)
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            Log::channel('daily')->info('API Activity', [
                'user_id'  => $request->user()?->id,
                'email'    => $request->user()?->email,
                'method'   => $request->method(),
                'path'     => $request->path(),
                'ip'       => $request->ip(),
                'status'   => $response->getStatusCode(),
            ]);
        }

        return $response;
    }
}
