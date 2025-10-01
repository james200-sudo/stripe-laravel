<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class StripeController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Créer une session Stripe Checkout
     */
    public function createCheckoutSession(Request $request)
    {
        try {
            // Validation des données
            $validated = $request->validate([
                'amount' => 'required|numeric|min:0',
                'currency' => 'required|string|max:3',
                'plan_id' => 'required|string',
                'plan_name' => 'required|string',
                'is_yearly' => 'required|boolean',
                'success_url' => 'required|string',
                'cancel_url' => 'required|string',
                'metadata' => 'sometimes|array',
            ]);

            // Récupérer les données utilisateur depuis le middleware
            $user = $request->input('pocketbase_user');
            
            if (!$user || !isset($user['id'])) {
                Log::error('User data missing in request', ['user' => $user]);
                return response()->json(['error' => 'Données utilisateur manquantes'], 400);
            }

            $userId = $user['id'];
            $userEmail = $user['email'] ?? 'no-email@provided.com';

            Log::info('Creating checkout session', [
                'user_id' => $userId,
                'plan' => $validated['plan_name'],
                'amount' => $validated['amount'],
            ]);

            // Créer la session Stripe
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($validated['currency']),
                        'product_data' => [
                            'name' => $validated['plan_name'],
                            'description' => 'Hydro AI - ' . $validated['plan_name'] . ' Plan',
                        ],
                        'unit_amount' => (int)($validated['amount'] * 100), // Convertir en centimes
                        'recurring' => [
                            'interval' => $validated['is_yearly'] ? 'year' : 'month',
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'success_url' => $validated['success_url'] . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $validated['cancel_url'],
                'client_reference_id' => $userId, // ID PocketBase pour identifier l'utilisateur
                'customer_email' => $userEmail,
                'metadata' => array_merge([
                    'user_id' => $userId,
                    'plan_id' => $validated['plan_id'],
                    'plan_name' => $validated['plan_name'],
                    'billing_period' => $validated['is_yearly'] ? 'yearly' : 'monthly',
                ], $validated['metadata'] ?? []),
            ]);

            Log::info('Checkout session created successfully', [
                'session_id' => $session->id,
                'user_id' => $userId,
            ]);

            return response()->json([
                'success' => true,
                'sessionId' => $session->id,
                'url' => $session->url,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error', ['errors' => $e->errors()]);
            return response()->json([
                'error' => 'Données invalides',
                'details' => $e->errors()
            ], 422);
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('Stripe API error', [
                'message' => $e->getMessage(),
                'type' => get_class($e)
            ]);
            return response()->json([
                'error' => 'Erreur Stripe: ' . $e->getMessage()
            ], 500);
            
        } catch (\Exception $e) {
            Log::error('Unexpected error creating checkout session', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Erreur lors de la création de la session de paiement'
            ], 500);
        }
    }

    /**
     * Vérifier une session Stripe
     */
    public function verifySession(Request $request, $sessionId)
    {
        try {
            $session = Session::retrieve($sessionId);
            
            return response()->json([
                'success' => true,
                'status' => $session->payment_status,
                'customer_email' => $session->customer_details->email ?? null,
                'amount_total' => $session->amount_total / 100,
                'currency' => $session->currency,
                'metadata' => $session->metadata,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error verifying session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Session introuvable'], 404);
        }
    }

    /**
     * Statut d'abonnement
     */
    public function subscriptionStatus(Request $request)
    {
        try {
            $user = $request->input('pocketbase_user');
            $userId = $user['id'];

            // Récupérer les abonnements actifs pour cet utilisateur
            $subscriptions = \Stripe\Subscription::all([
                'limit' => 10,
            ]);

            // Filtrer par metadata user_id
            $userSubscriptions = array_filter($subscriptions->data, function($sub) use ($userId) {
                return isset($sub->metadata['user_id']) && $sub->metadata['user_id'] === $userId;
            });

            if (empty($userSubscriptions)) {
                return response()->json([
                    'success' => true,
                    'has_subscription' => false,
                    'message' => 'Aucun abonnement actif'
                ]);
            }

            $subscription = reset($userSubscriptions);

            return response()->json([
                'success' => true,
                'has_subscription' => true,
                'status' => $subscription->status,
                'plan_name' => $subscription->metadata['plan_name'] ?? 'Unknown',
                'current_period_end' => date('Y-m-d', $subscription->current_period_end),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error getting subscription status', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Erreur lors de la récupération du statut'], 500);
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

            Log::info('Webhook received', ['type' => $event->type]);

            switch ($event->type) {
                case 'checkout.session.completed':
                    $this->handleCheckoutCompleted($event->data->object);
                    break;
                    
                case 'customer.subscription.created':
                    $this->handleSubscriptionCreated($event->data->object);
                    break;
                    
                case 'customer.subscription.updated':
                    $this->handleSubscriptionUpdated($event->data->object);
                    break;
                    
                case 'customer.subscription.deleted':
                    $this->handleSubscriptionDeleted($event->data->object);
                    break;
                    
                case 'invoice.payment_succeeded':
                    $this->handlePaymentSucceeded($event->data->object);
                    break;
                    
                case 'invoice.payment_failed':
                    $this->handlePaymentFailed($event->data->object);
                    break;
            }

            return response()->json(['received' => true]);

        } catch (\UnexpectedValueException $e) {
            Log::error('Invalid webhook signature', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid signature'], 400);
            
        } catch (\Exception $e) {
            Log::error('Webhook error', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Gérer la complétion du checkout
     */
    private function handleCheckoutCompleted($session)
    {
        try {
            $userId = $session->client_reference_id;
            $planName = $session->metadata->plan_name ?? 'Unknown';
            $planId = $session->metadata->plan_id ?? null;

            Log::info('Checkout completed', [
                'user_id' => $userId,
                'plan' => $planName,
                'session_id' => $session->id
            ]);

            // Mettre à jour l'utilisateur dans PocketBase
            $this->updatePocketBaseUser($userId, $planId, $planName, $session);
            
        } catch (\Exception $e) {
            Log::error('Error handling checkout completion', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Gérer la création d'abonnement
     */
    private function handleSubscriptionCreated($subscription)
    {
        Log::info('Subscription created', [
            'subscription_id' => $subscription->id,
            'status' => $subscription->status
        ]);
    }

    /**
     * Gérer la mise à jour d'abonnement
     */
    private function handleSubscriptionUpdated($subscription)
    {
        Log::info('Subscription updated', [
            'subscription_id' => $subscription->id,
            'status' => $subscription->status
        ]);
    }

    /**
     * Gérer la suppression d'abonnement
     */
    private function handleSubscriptionDeleted($subscription)
    {
        Log::info('Subscription deleted', [
            'subscription_id' => $subscription->id
        ]);
        
        // Rétrograder l'utilisateur au plan gratuit
        if (isset($subscription->metadata['user_id'])) {
            $this->downgradeToPocketBase($subscription->metadata['user_id']);
        }
    }

    /**
     * Gérer le paiement réussi
     */
    private function handlePaymentSucceeded($invoice)
    {
        Log::info('Payment succeeded', [
            'invoice_id' => $invoice->id,
            'amount' => $invoice->amount_paid / 100
        ]);
    }

    /**
     * Gérer le paiement échoué
     */
    private function handlePaymentFailed($invoice)
    {
        Log::error('Payment failed', [
            'invoice_id' => $invoice->id,
            'customer' => $invoice->customer
        ]);
    }

    /**
     * Mettre à jour l'utilisateur dans PocketBase
     */
    private function updatePocketBaseUser($userId, $planId, $planName, $session)
    {
        try {
            $pocketbaseUrl = 'https://hydro-ai-chat.ensolutions.ca';
            
            Log::info('Updating PocketBase user', [
                'user_id' => $userId,
                'plan_id' => $planId,
                'plan_name' => $planName
            ]);

            // Si on n'a pas de plan_id, on le récupère depuis PocketBase
            if (!$planId) {
                $plansResponse = Http::get("{$pocketbaseUrl}/api/collections/plans/records", [
                    'filter' => "name='{$planName}'"
                ]);

                if ($plansResponse->successful()) {
                    $plans = $plansResponse->json();
                    $planId = $plans['items'][0]['id'] ?? null;
                }
            }

            if (!$planId) {
                Log::error("Plan not found in PocketBase", ['plan_name' => $planName]);
                return;
            }

            // Mettre à jour l'utilisateur
            $response = Http::patch(
                "{$pocketbaseUrl}/api/collections/users/records/{$userId}",
                [
                    'plan' => $planId,
                    'subscription_status' => 'active',
                    'stripe_customer_id' => $session->customer ?? null,
                    'subscription_end_date' => now()->addMonth()->toIso8601String(),
                ]
            );

            if ($response->successful()) {
                Log::info("User successfully upgraded", [
                    'user_id' => $userId,
                    'plan' => $planName
                ]);
            } else {
                Log::error("Failed to update PocketBase user", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Error updating PocketBase user", [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
        }
    }

    /**
     * Rétrograder un utilisateur au plan gratuit
     */
    private function downgradeToPocketBase($userId)
    {
        try {
            $pocketbaseUrl = 'https://hydro-ai-chat.ensolutions.ca';
            
            // Récupérer le plan gratuit
            $plansResponse = Http::get("{$pocketbaseUrl}/api/collections/plans/records", [
                'filter' => "name='Free'"
            ]);

            if ($plansResponse->successful()) {
                $plans = $plansResponse->json();
                $freePlanId = $plans['items'][0]['id'] ?? null;

                if ($freePlanId) {
                    Http::patch(
                        "{$pocketbaseUrl}/api/collections/users/records/{$userId}",
                        [
                            'plan' => $freePlanId,
                            'subscription_status' => 'cancelled',
                        ]
                    );
                    
                    Log::info("User downgraded to free plan", ['user_id' => $userId]);
                }
            }
        } catch (\Exception $e) {
            Log::error("Error downgrading user", ['error' => $e->getMessage()]);
        }
    }
}