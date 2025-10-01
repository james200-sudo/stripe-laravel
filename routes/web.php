<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StripeController;

// ========================================
// PAGE D'ACCUEIL
// ========================================
Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Routes API - Hydro AI Backend
|--------------------------------------------------------------------------
*/

// ========================================
// ROUTE DE TEST
// ========================================
Route::get('/api/ping', function() {
    return response()->json([
        'status' => 'success',
        'message' => 'Hydro AI API is operational',
        'timestamp' => now()->toIso8601String(),
        'version' => '1.0.0',
    ]);
});

// ========================================
// ROUTES STRIPE (Protégées par token PocketBase)
// ========================================
Route::prefix('api')->middleware('pocketbase.auth')->group(function () {
    
    // Créer une session de paiement Stripe Checkout
    Route::post('/stripe/create-checkout', [StripeController::class, 'createCheckoutSession']);
    
    // Vérifier le statut d'abonnement de l'utilisateur
    Route::get('/stripe/subscription-status', [StripeController::class, 'subscriptionStatus']);
    
    // Vérifier une session Stripe après paiement
    Route::get('/stripe/verify-session/{sessionId}', [StripeController::class, 'verifySession']);
    
    // Annuler l'abonnement actuel
    Route::post('/stripe/cancel-subscription', [StripeController::class, 'cancelSubscription']);
    
    // Changer de plan (upgrade/downgrade)
    Route::post('/stripe/change-plan', [StripeController::class, 'changePlan']);
});

// ========================================
// WEBHOOK STRIPE (Public - Stripe l'appelle directement)
// ========================================
Route::post('/api/stripe/webhook', [StripeController::class, 'webhook']);