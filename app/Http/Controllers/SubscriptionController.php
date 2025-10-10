<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\InvalidRequestException;

class SubscriptionController extends Controller
{
    private const PRICE_IDS = [
        'basic_monthly' => 'price_1RdSRcDgYV6zJ17vntUXtF7T',
        'premium_monthly' => 'price_1RdSSzDgYV6zJ17v3Vcyyrxa',
        'basic_yearly' => 'price_1SEFYYRzsDq04jEjcSQhnJW9',
        'premium_yearly' => 'price_1RdSWIDgYV6zJ17vncazxwVJ',
    ];

    public function createSubscription(Request $request): JsonResponse
    {
        $request->validate([
            'price_id' => 'required|string|in:basic_monthly,basic_yearly,premium_monthly,premium_yearly',
            'payment_method' => 'required|string',
        ]);

        $user = $request->user();
        $priceId = self::PRICE_IDS[$request->price_id];

        try {
            // Create or get customer
            if (!$user->hasStripeId()) {
                $user->createAsStripeCustomer();
            }

            // Add payment method
            $user->addPaymentMethod($request->payment_method);
            $user->updateDefaultPaymentMethod($request->payment_method);

            // Create subscription
            $subscription = $user->newSubscription('default', $priceId)->create($request->payment_method);

            return response()->json([
                'success' => true,
                'subscription' => [
                    'id' => $subscription->stripe_id,
                    'status' => $subscription->stripe_status,
                    'current_period_end' => $subscription->ends_at,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function updateSubscription(Request $request): JsonResponse
    {
        $request->validate([
            'price_id' => 'required|string|in:basic_monthly,basic_yearly,premium_monthly,premium_yearly',
        ]);

        $user = $request->user();
        $priceId = self::PRICE_IDS[$request->price_id];

        if (!$user->isSubscribed()) {
            return response()->json([
                'success' => false,
                'message' => 'User is not subscribed',
            ], 400);
        }

        try {
            $subscription = $user->subscription('default');
            $subscription->swap($priceId);

            return response()->json([
                'success' => true,
                'message' => 'Subscription updated successfully',
                'subscription' => [
                    'id' => $subscription->stripe_id,
                    'status' => $subscription->stripe_status,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function cancelSubscription(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isSubscribed()) {
            return response()->json([
                'success' => false,
                'message' => 'User is not subscribed',
            ], 400);
        }

        try {
            $subscription = $user->subscription('default');
            $subscription->cancel();

            return response()->json([
                'success' => true,
                'message' => 'Subscription cancelled successfully',
                'ends_at' => $subscription->ends_at,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }



    public function resumeSubscription(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscription('default');

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No subscription found',
            ], 400);
        }

        try {
            // Check different scenarios for resuming
            if ($subscription->ended()) {
                // Subscription has completely ended - can resume
                $subscription->resume();

                return response()->json([
                    'success' => true,
                    'message' => 'Ended subscription resumed successfully',
                    'subscription' => [
                        'id' => $subscription->stripe_id,
                        'status' => $subscription->stripe_status,
                        'ends_at' => $subscription->ends_at,
                    ],
                ]);
            }

            if ($subscription->cancel() && $subscription->onGracePeriod()) {
                // Subscription is cancelled but still in grace period - can resume
                $subscription->resume();

                return response()->json([
                    'success' => true,
                    'message' => 'Cancelled subscription resumed successfully',
                    'subscription' => [
                        'id' => $subscription->stripe_id,
                        'status' => $subscription->stripe_status,
                        'ends_at' => $subscription->ends_at,
                    ],
                ]);
            }

            if ($subscription->active() && !$subscription->cancel()) {
                // Subscription is already active
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription is already active',
                ], 400);
            }

            // Default case - try to resume anyway
            $subscription->resume();

            return response()->json([
                'success' => true,
                'message' => 'Subscription resumed successfully',
                'subscription' => [
                    'id' => $subscription->stripe_id,
                    'status' => $subscription->stripe_status,
                    'ends_at' => $subscription->ends_at,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Resume failed: ' . $e->getMessage(),
                'debug_info' => [
                    'cancelled' => $subscription->cancel(),
                    'ended' => $subscription->ended(),
                    'active' => $subscription->active(),
                    'on_grace_period' => $subscription->onGracePeriod(),
                    'stripe_status' => $subscription->stripe_status,
                ]
            ], 400);
        }
    }

    // public function getSubscriptionDetails(Request $request): JsonResponse
    // {
    //     $user = $request->user();

    //     if (!$user->isSubscribed()) {
    //         return response()->json([
    //             'subscribed' => false,
    //             'tier' => 'none',
    //         ]);
    //     }

    //     $subscription = $user->subscription('default');

    //     Log::info($subscription);

    //     return response()->json([
    //         'subscribed' => true,
    //         'tier' => $user->getSubscriptionTier(),
    //         'subscription' => [
    //             'id' => $subscription->stripe_id,
    //             'status' => $subscription->stripe_status,
    //             'current_period_start' => $subscription->created_at,
    //             'current_period_end' => $subscription->ends_at,
    //             'cancelled' => $subscription->ended(),
    //             'on_grace_period' => $subscription->onGracePeriod(),
    //         ],
    //     ]);
    // }
    public function getSubscriptionDetails(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $requestId = uniqid('sub_details_');

        Log::info("[$requestId] Starting getSubscriptionDetails", [
            'user_id' => $request->user()?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);

        try {
            $user = $request->user();

            Log::info("[$requestId] User retrieved", [
                'user_id' => $user?->id,
                'user_email' => $user?->email,
                'user_created_at' => $user?->created_at,
            ]);

            if (!$user->isSubscribed()) {
                Log::info("[$requestId] User not subscribed", [
                    'user_id' => $user->id,
                    'response' => 'not_subscribed',
                ]);

                return response()->json([
                    'subscribed' => false,
                    'tier' => 'none',
                ]);
            }

            Log::info("[$requestId] User is subscribed, fetching subscription details", [
                'user_id' => $user->id,
            ]);

            $subscription = $user->subscription('default');

            if (!$subscription) {
                Log::warning("[$requestId] Subscription object is null despite user being subscribed", [
                    'user_id' => $user->id,
                    'is_subscribed' => $user->isSubscribed(),
                ]);

                return response()->json([
                    'subscribed' => false,
                    'tier' => 'none',
                    'error' => 'Subscription not found',
                ]);
            }

            Log::info("[$requestId] Subscription object retrieved", [
                'subscription_id' => $subscription->id,
                'subscription_name' => $subscription->name,
                'stripe_id' => $subscription->stripe_id,
                'stripe_status' => $subscription->stripe_status,
                'stripe_price' => $subscription->stripe_price,
                'quantity' => $subscription->quantity,
                'trial_ends_at' => $subscription->trial_ends_at,
                'ends_at' => $subscription->ends_at,
                'created_at' => $subscription->created_at,
                'updated_at' => $subscription->updated_at,
                'cancelled' => $subscription->ended(),
                'on_grace_period' => $subscription->onGracePeriod(),
                'on_trial' => $subscription->onTrial(),
                'valid' => $subscription->valid(),
                'incomplete' => $subscription->incomplete(),
                'past_due' => $subscription->pastDue(),
            ]);

            $subscriptionTier = $user->getSubscriptionTier();
            $tierName = $this->getTierNameWithBilling($subscription->stripe_price);

            Log::info("[$requestId] Subscription tier retrieved", [
                'user_id' => $user->id,
                'tier' => $subscriptionTier,
                'tier_name' => $tierName,
                'stripe_price' => $subscription->stripe_price,
            ]);

            // Log subscription items if available
            if (method_exists($subscription, 'items')) {
                $items = $subscription->items;
                Log::info("[$requestId] Subscription items", [
                    'items_count' => $items->count(),
                    'items' => $items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'stripe_id' => $item->stripe_id,
                            'stripe_product' => $item->stripe_product,
                            'stripe_price' => $item->stripe_price,
                            'quantity' => $item->quantity,
                        ];
                    })->toArray(),
                ]);
            }

            $response = [
                'subscribed' => true,
                'tier' => $subscriptionTier,
                'tier_name' => $tierName,
                'subscription' => [
                    'id' => $subscription->stripe_id,
                    'status' => $subscription->stripe_status,
                    'current_period_start' => $subscription->created_at,
                    'current_period_end' => $subscription->ends_at,
                    'cancelled' => $subscription->ended(),
                    'on_grace_period' => $subscription->onGracePeriod(),
                ],
            ];

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info("[$requestId] Successful response prepared", [
                'user_id' => $user->id,
                'response_data' => $response,
                'execution_time_ms' => $executionTime,
            ]);

            return response()->json($response);

        } catch (\Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::error("[$requestId] Exception occurred in getSubscriptionDetails", [
                'user_id' => $request->user()?->id,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'exception_trace' => $e->getTraceAsString(),
                'execution_time_ms' => $executionTime,
            ]);

            return response()->json([
                'subscribed' => false,
                'tier' => 'none',
                'error' => 'An error occurred while fetching subscription details',
            ], 500);
        }

        /**
         * Get tier name from Stripe price ID
         */

    }

    private function getTierNameFromPrice(string $priceId): string
    {
        // Flip the PRICE_IDS array to map price IDs to tier names
        $priceToTierMap = array_flip(self::PRICE_IDS);

        // Clean up the tier name (remove _monthly/_yearly suffix and format nicely)
        $tierKey = $priceToTierMap[$priceId] ?? null;

        if (!$tierKey) {
            return 'Unknown';
        }

        // Remove billing period suffix and format the name
        $tierName = str_replace(['_monthly', '_yearly'], '', $tierKey);
        $tierName = str_replace('_', ' ', $tierName);
        $tierName = ucwords($tierName);

        return $tierName;
    }

    /**
     * Get tier name with billing period from Stripe price ID
     */
    private function getTierNameWithBilling(string $priceId): string
    {
        $priceToTierMap = array_flip(self::PRICE_IDS);
        $tierKey = $priceToTierMap[$priceId] ?? null;

        if (!$tierKey) {
            return 'Unknown';
        }

        // Format the full tier name with billing period
        $parts = explode('_', $tierKey);
        $billingPeriod = array_pop($parts); // monthly or yearly
        $tierName = implode(' ', $parts);
        $tierName = ucwords(str_replace('_', ' ', $tierName));

        return $tierName . ' (' . ucfirst($billingPeriod) . ')';
    }

    public function createSetupIntent(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            if (!$user->hasStripeId()) {
                $user->createAsStripeCustomer();
            }

            $intent = $user->createSetupIntent();

            return response()->json([
                'client_secret' => $intent->client_secret,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function getPaymentMethods(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasStripeId()) {
            return response()->json(['payment_methods' => []]);
        }

        $paymentMethods = $user->paymentMethods()->map(function ($paymentMethod) {
            return [
                'id' => $paymentMethod->id,
                'type' => $paymentMethod->type,
                'card' => $paymentMethod->card ? [
                    'brand' => $paymentMethod->card->brand,
                    'last4' => $paymentMethod->card->last4,
                    'exp_month' => $paymentMethod->card->exp_month,
                    'exp_year' => $paymentMethod->card->exp_year,
                ] : null,
            ];
        });

        return response()->json([
            'payment_methods' => $paymentMethods,
            'default_payment_method' => $user->defaultPaymentMethod()?->id,
        ]);
    }

    public function getPrices(): JsonResponse
    {
        return response()->json([
            'prices' => [
                'basic_monthly' => [
                    'id' => 'basic_monthly',
                    'stripe_id' => self::PRICE_IDS['basic_monthly'],
                    'name' => 'Basic Monthly',
                    'interval' => 'month',
                    'tier' => 'basic',
                ],
                'premium_monthly' => [
                    'id' => 'premium_monthly',
                    'stripe_id' => self::PRICE_IDS['premium_monthly'],
                    'name' => 'Premium Monthly',
                    'interval' => 'month',
                    'tier' => 'premium',
                ],
                
                'basic_yearly' => [
                    'id' => 'basic_yearly',
                    'stripe_id' => self::PRICE_IDS['basic_yearly'],
                    'name' => 'Basic Yearly',
                    'interval' => 'year',
                    'tier' => 'basic',
                ],
                'premium_yearly' => [
                    'id' => 'premium_yearly',
                    'stripe_id' => self::PRICE_IDS['premium_yearly'],
                    'name' => 'Premium Yearly',
                    'interval' => 'year',
                    'tier' => 'premium',
                ],
            ],
        ]);
    }

    public function pauseSubscription(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isSubscribed()) {
            return response()->json([
                'success' => false,
                'message' => 'User is not subscribed',
            ], 400);
        }

        try {
            $subscription = $user->subscription('default');

            // Check if subscription is already cancelled/paused
            if ($subscription->ended()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription is already cancelled',
                ], 400);
            }

            // Cancel the subscription (this will pause it at the end of the current period)
            $subscription->cancel();

            return response()->json([
                'success' => true,
                'message' => 'Subscription paused successfully. It will remain active until the end of the current billing period.',
                'ends_at' => $subscription->ends_at->toISOString(),
                'on_grace_period' => $subscription->onGracePeriod(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function getAllSubscriptionAmounts(Request $request): JsonResponse
    {
        Log::info('Attempting to get all subscription amounts.');
        $user = $request->user();

        if (!$user) {
            Log::warning('No authenticated user found.');
            return response()->json(['success' => false, 'message' => 'User not authenticated.'], 401);
        }

        Log::info('User found:', ['user_id' => $user->id, 'user_email' => $user->email]);

        if ($user->subscriptions()->count() == 0) {
            Log::info('User has no subscriptions.', ['user_id' => $user->id]);
            return response()->json([
                'success' => false,
                'message' => 'User has no subscriptions.',
            ], 404);
        }

        try {
            Log::info('Fetching subscriptions for user.', ['user_id' => $user->id]);
            $subscriptions = $user->subscriptions()->orderBy('created_at', 'asc')->get();

            if ($subscriptions->isEmpty()) {
                Log::warning('No subscriptions found for user after fetching subscriptions.', ['user_id' => $user->id]);
                return response()->json([
                    'success' => false,
                    'message' => 'No subscriptions found for this user',
                ], 404);
            }

            Log::info('Found subscriptions.', ['user_id' => $user->id, 'count' => $subscriptions->count()]);

            $subscriptionData = [];
            $totalCommission = 0; // Initialize total commission variable

            foreach ($subscriptions as $subscription) {
                Log::info('Processing subscription.', ['user_id' => $user->id, 'subscription_id' => $subscription->id]);

                $subscriptionItem = $subscription->items->first();
                if (!$subscriptionItem) {
                    Log::warning('No items found in subscription.', ['user_id' => $user->id, 'subscription_id' => $subscription->id]);
                    continue; // Skip if no items found
                }

                Log::info('Retrieving price from Stripe.', ['user_id' => $user->id, 'stripe_price_id' => $subscriptionItem->stripe_price]);
                $price = \Stripe\Price::retrieve($subscriptionItem->stripe_price);
                $amount = $price->unit_amount; // Amount in cents
                $currency = strtoupper($price->currency);
                $startDate = $subscription->created_at;

                Log::info('Successfully retrieved price.', ['user_id' => $user->id, 'amount' => $amount, 'currency' => $currency]);

                // Add the subscription's commission to the total commission
                $affiliate = \DB::table('affiliates')->where('user_id', $user->id)->first();
                if ($affiliate) {
                    $commission = ($amount / 100) * ($affiliate->commission_rate / 100);
                    $totalCommission += $commission; // Add to the total commission
                }

                // Store each subscription amount and commission in an array
                $subscriptionData[] = [
                    'subscription_amount' => $amount / 100, // Convert to dollars or main currency unit
                    'currency' => $currency,
                    'subscription_start_date' => $startDate->toDateTimeString(),
                    'commission' => number_format($commission, 2), // Include the commission for the subscription
                ];

                // Update the affiliate commission in the database
                // You can either update the affiliate commission or a new column for the total commission.
                if ($affiliate) {
                    \DB::table('affiliates')->where('user_id', $user->id)
                        ->update([
                            'total_commission' => $totalCommission // Update total commission
                        ]);
                }
            }

            Log::info('Returning all subscription amounts with total commission.', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'subscriptions' => $subscriptionData,
                'total_commission' => number_format($totalCommission, 2), // Return the total commission
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching all subscription amounts.', [
                'user_id' => $user->id,
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
