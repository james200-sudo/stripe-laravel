<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StripeController;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Routes Stripe
Route::prefix('stripe')->group(function () {
    // Route protégée - nécessite authentification
    Route::post('/create-checkout', [StripeController::class, 'createCheckoutSession'])
        ->middleware('auth:sanctum');
    
    // Route pour vérifier une session
    Route::get('/verify-session/{sessionId}', [StripeController::class, 'verifySession'])
        ->middleware('auth:sanctum');
    
    // Webhook - pas d'authentification (Stripe envoie directement)
    Route::post('/webhook', [StripeController::class, 'webhook']);
});


// Routes d'authentification
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
});

Route::get('/ping', function() {
    return response()->json(['status' => 'ok', 'message' => 'API Laravel fonctionnelle']);
});