<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StripeController;

/*
|--------------------------------------------------------------------------
| Routes API - Hydro AI Backend
|--------------------------------------------------------------------------
*/

// ========================================
// ROUTE DE TEST
// ========================================
Route::get('/ping', function() {
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
Route::middleware('pocketbase.auth')->group(function () {
    
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
    
    // Synchroniser le statut d'abonnement avec PocketBase
    Route::get('/stripe/sync-subscription', [StripeController::class, 'syncSubscriptionStatus']);
});

// ========================================
// WEBHOOK STRIPE (Public - Stripe l'appelle directement)
// ========================================
Route::post('/stripe/webhook', [StripeController::class, 'webhook']);
Route::get('/csrf-token', function () {
    // La fonction 'csrf_token()' de Laravel lit le jeton de la session
    // et le renvoie.
    return response()->json([
        'csrf_token' => csrf_token()
    ]);
});

    
