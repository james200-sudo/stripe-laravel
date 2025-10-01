<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyPocketBaseToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json([
                'error' => 'Token manquant'
            ], 401);
        }

        // Décoder le JWT sans vérification (pour récupérer les infos utilisateur)
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return response()->json([
                    'error' => 'Format de token invalide'
                ], 401);
            }

            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            
            if (!$payload) {
                return response()->json([
                    'error' => 'Token invalide'
                ], 401);
            }

            // Vérifier l'expiration
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return response()->json([
                    'error' => 'Token expiré'
                ], 401);
            }

            // Extraire les informations utilisateur du token
            $userId = $payload['id'] ?? null;
            $userEmail = $payload['email'] ?? null;

            if (!$userId) {
                return response()->json([
                    'error' => 'Token invalide - ID utilisateur manquant'
                ], 401);
            }

            // Attacher les données utilisateur à la requête
            $request->merge([
                'pocketbase_user' => [
                    'id' => $userId,
                    'email' => $userEmail,
                ],
                'pocketbase_token' => $token,
            ]);

            return $next($request);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la vérification du token'
            ], 401);
        }
    }
}