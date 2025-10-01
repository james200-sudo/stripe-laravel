<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    /**
     * Récupérer tous les plans actifs
     */
    public function index()
    {
        try {
            $plans = Plan::where('is_active', true)
                ->orderBy('monthly_price')
                ->get();

            return response()->json([
                'success' => true,
                'plans' => $plans,
                'count' => $plans->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch plans',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer un plan spécifique par ID
     */
    public function show($id)
    {
        try {
            $plan = Plan::find($id);

            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'error' => 'Plan not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'plan' => $plan
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch plan',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer un plan par nom
     */
    public function getByName($name)
    {
        try {
            $plan = Plan::where('name', 'LIKE', $name)->first();

            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'error' => 'Plan not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'plan' => $plan
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch plan',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer le plan actuel de l'utilisateur authentifié
     */
    public function currentPlan(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Not authenticated'
                ], 401);
            }

            $planDetails = Plan::where('name', $user->current_plan)->first();

            return response()->json([
                'success' => true,
                'current_plan' => $user->current_plan,
                'subscription_status' => $user->subscription_status,
                'plan_details' => $planDetails
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch current plan',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculer le prix avec réduction annuelle
     */
    public function calculatePrice(Request $request)
    {
        try {
            $validated = $request->validate([
                'plan_id' => 'required|exists:plans,id',
                'is_yearly' => 'required|boolean'
            ]);

            $plan = Plan::find($validated['plan_id']);

            $price = $validated['is_yearly'] ? $plan->yearly_price : $plan->monthly_price;
            $discount = $validated['is_yearly'] ? $plan->yearly_discount : 0;
            $discountPercentage = $validated['is_yearly'] ? $plan->yearly_discount_percentage : 0;

            return response()->json([
                'success' => true,
                'plan_name' => $plan->name,
                'price' => $price,
                'formatted_price' => $plan->getFormattedPrice($validated['is_yearly']),
                'is_yearly' => $validated['is_yearly'],
                'discount' => round($discount, 2),
                'discount_percentage' => round($discountPercentage, 2),
                'period' => $validated['is_yearly'] ? '/year' : '/month'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to calculate price',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Comparer plusieurs plans
     */
    public function compare(Request $request)
    {
        try {
            $validated = $request->validate([
                'plan_ids' => 'required|array|min:2|max:4',
                'plan_ids.*' => 'exists:plans,id',
                'is_yearly' => 'boolean'
            ]);

            $isYearly = $validated['is_yearly'] ?? false;
            $plans = Plan::whereIn('id', $validated['plan_ids'])->get();

            $comparison = $plans->map(function ($plan) use ($isYearly) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'price' => $plan->getPriceValue($isYearly),
                    'formatted_price' => $plan->getFormattedPrice($isYearly),
                    'perks' => $plan->perks,
                    'perks_count' => count($plan->perks ?? []),
                    'is_free' => $plan->isFree(),
                    'is_enterprise' => $plan->isEnterprise(),
                ];
            });

            return response()->json([
                'success' => true,
                'comparison' => $comparison,
                'billing_period' => $isYearly ? 'yearly' : 'monthly'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to compare plans',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}