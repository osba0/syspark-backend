<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/login
     * Connexion et génération du token Sanctum
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Protection brute-force : 5 tentatives / 1 minute par IP+email
        $key = 'login:' . $request->ip() . '|' . $request->input('email');
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => "Trop de tentatives. Réessayez dans {$seconds} secondes.",
            ], 429);
        }

        $user = User::where('email', $request->input('email'))
            ->with('agence')
            ->first();

        if (!$user || !Hash::check($request->input('password'), $user->password)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages([
                'email' => ['Identifiants invalides.'],
            ]);
        }

        if (!$user->est_actif) {
            return response()->json([
                'message' => 'Votre compte est désactivé. Contactez l\'administrateur.',
            ], 403);
        }

        // Réinitialiser le rate limiter en cas de succès
        RateLimiter::clear($key);

        // Révoquer les anciens tokens si single-session
        // $user->tokens()->delete();

        // Générer le token Sanctum avec expiration 24h
        $token = $user->createToken(
            'parc-auto-api',
            ['*'],                     // Abilities (toutes pour l'instant)
            now()->addHours(24)        // Expiration
        )->plainTextToken;

        // Mettre à jour la dernière connexion
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Connexion réussie.',
            'data'    => [
                'token'       => $token,
                'token_type'  => 'Bearer',
                'expires_in'  => 86400, // 24h en secondes
                'user'        => new UserResource($user),
            ],
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     * Déconnexion et révocation du token courant
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie.',
        ]);
    }

    /**
     * DELETE /api/v1/auth/logout-all
     * Révoquer tous les tokens (déconnexion de tous les appareils)
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Tous les appareils ont été déconnectés.',
        ]);
    }

    /**
     * GET /api/v1/auth/me
     * Profil de l'utilisateur connecté avec ses rôles et permissions
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'agence',
            // HasMany → on charge la collection avec ses sous-relations
            'chauffeur' => function ($q) {
                $q->with(['affectationActive.vehicule']);
            },
        ]);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    /**
     * POST /api/v1/auth/password/change
     * Changement de mot de passe
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'name'      => ['sometimes', 'string', 'max:100'],
            'prenom'    => ['nullable', 'string', 'max:100'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'fonction'  => ['nullable', 'string', 'max:150'],
        ]);

        $user->update($request->only(['name', 'prenom', 'telephone', 'fonction']));

        return response()->json([
            'message' => 'Profil mis à jour.',
            'data'    => new UserResource($user->fresh()->load('agence')),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/'],
        ], [
            'password.regex' => 'Le mot de passe doit contenir au moins une majuscule, une minuscule et un chiffre.',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Le mot de passe actuel est incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($request->password)]);

        // Révoquer tous les autres tokens pour sécurité
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return response()->json([
            'message' => 'Mot de passe modifié avec succès.',
        ]);
    }

    /**
     * POST /api/v1/auth/password/forgot
     * Demande réinitialisation mot de passe
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Laravel built-in password reset
        $status = \Illuminate\Support\Facades\Password::sendResetLink(
            $request->only('email')
        );

        return response()->json([
            'message' => 'Si cet email existe, un lien de réinitialisation a été envoyé.',
        ]);
    }
}