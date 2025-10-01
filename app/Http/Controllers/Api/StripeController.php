<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class StripeController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Créer une session Stripe
     */
    public function createCheckoutSession(Request $request)
    {
        try {
            $validated = $request->validate([
                'amount' => 'required|numeric|min:0',
                'currency' => 'required|string|max:3',
                'plan_id' => 'required|string',
                'plan_name' => 'required|string',
                'is_yearly' => 'required|boolean',
                'success_url' => 'required|url',
                'cancel_url' => 'required|url',
            ]);

            // Récupérer les données utilisateur depuis PocketBase
            $user = $request->input('pocketbase_user');
            
            if (!$user) {
                return response()->json(['error' => 'Utilisateur non trouvé'], 401);
            }

            $userId = $user['id'];
            $userEmail = $user['email'];

            // Créer la session Stripe
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => $validated['currency'],
                        'product_data' => [
                            'name' => $validated['plan_name'],
                        ],
                        'unit_amount' => (int)($validated['amount'] * 100),
                        'recurring' => [
                            'interval' => $validated['is_yearly'] ? 'year' : 'month',
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'success_url' => $validated['success_url'] . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $validated['cancel_url'],
                'client_reference_id' => $userId, // ID PocketBase
                'customer_email' => $userEmail,
                'metadata' => [
                    'user_id' => $userId,
                    'plan_id' => $validated['plan_id'],
                    'plan_name' => $validated['plan_name'],
                    'pocketbase_user' => $userId,
                ],
            ]);

            return response()->json([
                'sessionId' => $session->id,
                'url' => $session->url,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Webhook Stripe
     */
    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);

            if ($event->type === 'checkout.session.completed') {
                $session = $event->data->object;
                $userId = $session->client_reference_id;
                $planName = $session->metadata->plan_name;

                // Mettre à jour l'utilisateur dans PocketBase
                $this->updatePocketBaseUser($userId, $planName, $session);
            }

            return response()->json(['received' => true]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Mettre à jour l'utilisateur dans PocketBase
     */
    private function updatePocketBaseUser($userId, $planName, $session)
    {
        try {
            // Récupérer le plan ID depuis PocketBase
            $plansResponse = Http::get('https://hydro-ai-chat.ensolutions.ca/api/collections/plans/records', [
                'filter' => "Name='{$planName}'"
            ]);

            $plans = $plansResponse->json();
            $planId = $plans['items'][0]['id'] ?? null;

            if (!$planId) {
                \Log::error("Plan non trouvé: {$planName}");
                return;
            }

            // Mettre à jour l'utilisateur avec le nouveau plan
            Http::patch(
                "https://hydro-ai-chat.ensolutions.ca/api/collections/users/records/{$userId}",
                [
                    'plan' => $planId, // Relation vers la table plans
                ]
            );

            \Log::info("User {$userId} upgraded to {$planName}");

        } catch (\Exception $e) {
            \Log::error("Error updating PocketBase user: " . $e->getMessage());
        }
    }
}