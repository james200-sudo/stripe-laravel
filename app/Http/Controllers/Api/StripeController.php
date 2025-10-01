<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Webhook;

class StripeController extends Controller
{
    public function __construct()
    {
        // Initialiser Stripe avec la clé secrète
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Créer une session de checkout Stripe
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
                'success_url' => 'required|url',
                'cancel_url' => 'required|url',
            ]);

            // Récupérer l'utilisateur authentifié
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'error' => 'Non authentifié'
                ], 401);
            }

            Log::info('Creating Stripe checkout session', [
                'user_id' => $user->id,
                'plan' => $validated['plan_name'],
                'amount' => $validated['amount']
            ]);

            // Créer la session Stripe
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => $validated['currency'],
                        'product_data' => [
                            'name' => $validated['plan_name'],
                            'description' => 'Subscription to ' . $validated['plan_name'] . ' plan',
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
                'client_reference_id' => (string)$user->id,
                'customer_email' => $user->email,
                'metadata' => [
                    'user_id' => $user->id,
                    'plan_id' => $validated['plan_id'],
                    'plan_name' => $validated['plan_name'],
                    'billing_period' => $validated['is_yearly'] ? 'yearly' : 'monthly',
                ],
            ]);

            Log::info('Stripe session created successfully', [
                'session_id' => $session->id
            ]);

            return response()->json([
                'sessionId' => $session->id,
                'url' => $session->url,
            ]);

        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('Stripe API Error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur Stripe: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            Log::error('General Error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur serveur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Webhook pour recevoir les événements Stripe
     */
    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            // Vérifier la signature du webhook
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $webhookSecret
            );

            Log::info('Webhook received', ['type' => $event->type]);

            // Traiter l'événement selon son type
            switch ($event->type) {
                case 'checkout.session.completed':
                    $this->handleCheckoutSessionCompleted($event->data->object);
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

                default:
                    Log::info('Unhandled webhook event type: ' . $event->type);
            }

            return response()->json(['received' => true]);

        } catch (\UnexpectedValueException $e) {
            Log::error('Invalid payload: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('Invalid signature: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid signature'], 400);
        }
    }

    /**
     * Gérer le paiement complété
     */
    private function handleCheckoutSessionCompleted($session)
    {
        $userId = $session->client_reference_id;
        $planName = $session->metadata->plan_name ?? 'Unknown';

        Log::info('Checkout completed', [
            'user_id' => $userId,
            'plan' => $planName,
            'customer_id' => $session->customer,
            'subscription_id' => $session->subscription
        ]);

        try {
            $user = \App\Models\User::find($userId);
            
            if ($user) {
                $user->update([
                    'current_plan' => $planName,
                    'subscription_status' => 'active',
                    'stripe_customer_id' => $session->customer,
                    'stripe_subscription_id' => $session->subscription,
                ]);

                Log::info("User {$userId} upgraded to {$planName} plan");
            } else {
                Log::error("User {$userId} not found");
            }
        } catch (\Exception $e) {
            Log::error('Error updating user: ' . $e->getMessage());
        }
    }

    /**
     * Gérer l'annulation d'abonnement
     */
    private function handleSubscriptionDeleted($subscription)
    {
        Log::info('Subscription deleted', ['subscription_id' => $subscription->id]);

        try {
            $user = \App\Models\User::where('stripe_subscription_id', $subscription->id)->first();
            
            if ($user) {
                $user->update([
                    'current_plan' => 'Free',
                    'subscription_status' => 'canceled',
                ]);

                Log::info("User {$user->id} downgraded to Free plan");
            }
        } catch (\Exception $e) {
            Log::error('Error handling subscription deletion: ' . $e->getMessage());
        }
    }

    /**
     * Gérer le paiement réussi
     */
    private function handlePaymentSucceeded($invoice)
    {
        Log::info('Payment succeeded', ['invoice_id' => $invoice->id]);
        // Logique additionnelle si nécessaire
    }

    /**
     * Gérer l'échec de paiement
     */
    private function handlePaymentFailed($invoice)
    {
        Log::error('Payment failed', ['invoice_id' => $invoice->id]);
        
        // Notifier l'utilisateur par email par exemple
        // Vous pouvez ajouter cette logique ici
    }

    /**
     * Vérifier le statut d'une session
     */
    public function verifySession(Request $request, $sessionId)
    {
        try {
            $session = Session::retrieve($sessionId);
            
            return response()->json([
                'payment_status' => $session->payment_status,
                'status' => $session->status,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Session non trouvée'
            ], 404);
        }
    }
}