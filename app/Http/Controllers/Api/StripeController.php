<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Subscription;
use Stripe\Webhook;

class StripeController extends Controller
{
    private $pocketbaseUrl = 'https://hydro-ai-chat.ensolutions.ca';
    
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
                            'description' => 'TGM HydroAI - ' . $validated['plan_name'] . ' Plan',
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
                'client_reference_id' => $userId, // ID PocketBase
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

            // Récupérer les abonnements actifs
            $subscriptions = Subscription::all(['limit' => 10]);

            // Filtrer par metadata user_id
            $userSubscription = null;
            foreach ($subscriptions->data as $sub) {
                if (isset($sub->metadata['user_id']) && $sub->metadata['user_id'] === $userId) {
                    $userSubscription = $sub;
                    break;
                }
            }

            if (!$userSubscription) {
                return response()->json([
                    'success' => true,
                    'has_subscription' => false,
                    'message' => 'Aucun abonnement actif'
                ]);
            }

            return response()->json([
                'success' => true,
                'has_subscription' => true,
                'status' => $userSubscription->status,
                'plan_name' => $userSubscription->metadata['plan_name'] ?? 'Unknown',
                'current_period_end' => date('Y-m-d', $userSubscription->current_period_end),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error getting subscription status', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Erreur lors de la récupération du statut'], 500);
        }
    }

    /**
     * Annuler l'abonnement
     */
    public function cancelSubscription(Request $request)
    {
        try {
            $user = $request->input('pocketbase_user');
            $userId = $user['id'];

            // Trouver l'abonnement de l'utilisateur
            $subscriptions = Subscription::all(['limit' => 10]);
            
            $userSubscription = null;
            foreach ($subscriptions->data as $sub) {
                if (isset($sub->metadata['user_id']) && $sub->metadata['user_id'] === $userId) {
                    $userSubscription = $sub;
                    break;
                }
            }

            if (!$userSubscription) {
                return response()->json([
                    'error' => 'Aucun abonnement trouvé'
                ], 404);
            }

            // Annuler l'abonnement à la fin de la période
            $canceled = $userSubscription->cancel(['at_period_end' => true]);

            Log::info('Subscription cancelled', [
                'user_id' => $userId,
                'subscription_id' => $userSubscription->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Abonnement annulé avec succès',
                'cancel_at' => date('Y-m-d', $canceled->cancel_at),
            ]);

        } catch (\Exception $e) {
            Log::error('Error cancelling subscription', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Erreur lors de l\'annulation de l\'abonnement'
            ], 500);
        }
    }

    /**
     * Synchroniser le statut d'abonnement
     */
    public function syncSubscriptionStatus(Request $request)
    {
        try {
            $user = $request->input('pocketbase_user');
            $userId = $user['id'];
            
            Log::info('Syncing subscription status for user', ['user_id' => $userId]);
            
            // Récupérer tous les abonnements
            $subscriptions = Subscription::all(['limit' => 10]);
            
            $userSubscription = null;
            foreach ($subscriptions->data as $sub) {
                if (isset($sub->metadata['user_id']) && $sub->metadata['user_id'] === $userId) {
                    $userSubscription = $sub;
                    break;
                }
            }
            
            if (!$userSubscription) {
                // Aucun abonnement - rétrograder
                $this->downgradeToPocketBase($userId);
                
                return response()->json([
                    'success' => true,
                    'has_subscription' => false,
                    'message' => 'No active subscription found',
                ]);
            }
            
            // Vérifier si actif
            if ($userSubscription->status === 'active') {
                $this->syncPocketBaseWithStripe($userId, $userSubscription);
                
                return response()->json([
                    'success' => true,
                    'has_subscription' => true,
                    'status' => $userSubscription->status,
                    'plan_name' => $userSubscription->metadata['plan_name'] ?? 'Unknown',
                    'current_period_end' => date('Y-m-d', $userSubscription->current_period_end),
                ]);
            } else {
                // Abonnement inactif
                $this->downgradeToPocketBase($userId);
                
                return response()->json([
                    'success' => true,
                    'has_subscription' => false,
                    'status' => $userSubscription->status,
                    'message' => 'Subscription is not active',
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Error syncing subscription', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to sync subscription'], 500);
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
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);

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

    // =============================================
    // WEBHOOK HANDLERS
    // =============================================

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

            $this->updatePocketBaseUser($userId, $planId, $planName, $session);
            
        } catch (\Exception $e) {
            Log::error('Error handling checkout completion', ['error' => $e->getMessage()]);
        }
    }

    private function handleSubscriptionCreated($subscription)
    {
        Log::info('Subscription created', [
            'subscription_id' => $subscription->id,
            'status' => $subscription->status
        ]);
    }

    private function handleSubscriptionUpdated($subscription)
    {
        Log::info('Subscription updated', [
            'subscription_id' => $subscription->id,
            'status' => $subscription->status
        ]);
        
        // Synchroniser avec PocketBase
        if (isset($subscription->metadata['user_id'])) {
            $this->syncPocketBaseWithStripe($subscription->metadata['user_id'], $subscription);
        }
    }

    private function handleSubscriptionDeleted($subscription)
    {
        Log::info('Subscription deleted', [
            'subscription_id' => $subscription->id
        ]);
        
        if (isset($subscription->metadata['user_id'])) {
            $this->downgradeToPocketBase($subscription->metadata['user_id']);
        }
    }

    private function handlePaymentSucceeded($invoice)
    {
        Log::info('Payment succeeded', [
            'invoice_id' => $invoice->id,
            'amount' => $invoice->amount_paid / 100
        ]);
    }

    private function handlePaymentFailed($invoice)
    {
        Log::error('Payment failed', [
            'invoice_id' => $invoice->id,
            'customer' => $invoice->customer
        ]);
    }

    // =============================================
    // POCKETBASE SYNC METHODS
    // =============================================

    private function updatePocketBaseUser($userId, $planId, $planName, $session)
    {
        try {
            Log::info('Updating PocketBase user', [
                'user_id' => $userId,
                'plan_id' => $planId,
                'plan_name' => $planName
            ]);

            // Mapping des plans
            $planMapping = [
                'Free' => 'xkdv2sqngtpnqjp',
                'Individual' => 'iox7db52ee17vnf',
                'Company' => 'hnahry5t5ardea3',
                'Hydropower Utilities' => '7iw3959pf0rbo7m',
            ];

            $pocketbasePlanId = $planMapping[$planName] ?? null;
            
            if (!$pocketbasePlanId) {
                Log::warning('Plan name not found in mapping', ['plan_name' => $planName]);
                
                // Tentative de récupération depuis PocketBase
                $plansResponse = Http::get("{$this->pocketbaseUrl}/api/collections/plans/records", [
                    'filter' => "Name~'{$planName}'"
                ]);

                if ($plansResponse->successful()) {
                    $plans = $plansResponse->json();
                    if (!empty($plans['items'])) {
                        $pocketbasePlanId = $plans['items'][0]['id'];
                        Log::info('Plan found in PocketBase', ['plan_id' => $pocketbasePlanId]);
                    }
                }
            }

            if (!$pocketbasePlanId) {
                Log::error('Unable to determine plan ID', ['plan_name' => $planName]);
                return;
            }

            // Période d'abonnement
            $isYearly = isset($session->metadata['billing_period']) 
                && $session->metadata['billing_period'] === 'yearly';
            
            $subscriptionEndDate = now()->addMonths($isYearly ? 12 : 1);

            // Données à mettre à jour
            $updateData = [
                'plan' => $pocketbasePlanId,
                'subscriptionStatus' => 'active',
                'stripeCustomerId' => $session->customer ?? null,
                'stripeSubscriptionId' => $session->subscription ?? null,
                'subscriptionEndDate' => $subscriptionEndDate->toIso8601String(),
                'lastPaymentDate' => now()->toIso8601String(),
            ];

            Log::info('Sending update to PocketBase', $updateData);

            $response = Http::patch(
                "{$this->pocketbaseUrl}/api/collections/users/records/{$userId}",
                $updateData
            );

            if ($response->successful()) {
                Log::info('User successfully upgraded', [
                    'user_id' => $userId,
                    'plan' => $planName,
                    'expires' => $subscriptionEndDate->format('Y-m-d'),
                ]);
            } else {
                Log::error('Failed to update PocketBase user', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'user_id' => $userId,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error updating PocketBase user', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $userId
            ]);
        }
    }

    private function syncPocketBaseWithStripe($userId, $subscription)
    {
        try {
            $planName = $subscription->metadata['plan_name'] ?? 'Unknown';
            
            $planMapping = [
                'Free' => 'xkdv2sqngtpnqjp',
                'Individual' => 'iox7db52ee17vnf',
                'Company' => 'hnahry5t5ardea3',
                'Hydropower Utilities' => '7iw3959pf0rbo7m',
            ];
            
            $planId = $planMapping[$planName] ?? null;
            
            if (!$planId) {
                Log::warning('Unknown plan during sync', ['plan_name' => $planName]);
                return;
            }
            
            $updateData = [
                'plan' => $planId,
                'subscriptionStatus' => $subscription->status,
                'stripeSubscriptionId' => $subscription->id,
                'subscriptionEndDate' => date('c', $subscription->current_period_end),
            ];
            
            Http::patch(
                "{$this->pocketbaseUrl}/api/collections/users/records/{$userId}",
                $updateData
            );
            
            Log::info('PocketBase synced with Stripe', [
                'user_id' => $userId,
                'plan' => $planName,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error syncing PocketBase', ['error' => $e->getMessage()]);
        }
    }

    private function downgradeToPocketBase($userId)
    {
        try {
            Log::info('Downgrading user to free plan', ['user_id' => $userId]);
            
           $freePlanId = 'xkdv2sqngtpnqjp';
            
            $updateData = [
                'plan' => $freePlanId,
                'subscriptionStatus' => 'cancelled',
                'subscriptionEndDate' => now()->toIso8601String(),
            ];

            $response = Http::patch(
                "{$this->pocketbaseUrl}/api/collections/users/records/{$userId}",
                $updateData
            );
            
            if ($response->successful()) {
                Log::info('User downgraded to free plan', ['user_id' => $userId]);
            } else {
                Log::error('Failed to downgrade user', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error downgrading user', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
        }
    }
}