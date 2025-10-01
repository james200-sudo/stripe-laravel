<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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

        // Vérifier le token auprès de PocketBase
        $response = Http::withHeaders([
            'Authorization' => $token,
        ])->get('https://hydro-ai-chat.ensolutions.ca/api/collections/users/auth-refresh');

        if ($response->failed()) {
            return response()->json([
                'error' => 'Token invalide ou expiré'
            ], 401);
        }

        $userData = $response->json();
        
        // Attacher les données utilisateur à la requête
        $request->merge([
            'pocketbase_user' => $userData['record'] ?? null,
            'pocketbase_token' => $token,
        ]);

        return $next($request);
    }
}