<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\InvalidRequestException;
use Illuminate\Support\Facades\Auth;
use App\Traits\HandlesApiResponses;
use Illuminate\Support\Facades\Validator;
use Stripe\Stripe;
use App\Models\Coupon;
use App\Models\Plans;
use Laravel\Cashier\Subscription;
use Stripe\PromotionCode;
use Carbon\Carbon;
use Stripe\Customer;
use App\Models\SubscriptionDiscount; // make sure you have this mode



class SubscriptionController extends Controller
{
    use HandlesApiResponses;
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
        // $priceId = self::PRICE_IDS[$request->price_id];
        $priceId = "price_1RdSRcDgYV6zJ17vntUXtF7T";

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

//    public function updateSubscription(Request $request): JsonResponse
//     {

//         // $request->validate([
//         //     'price_slug' => 'required|string|in:basic_monthly,basic_yearly,premium_monthly,premium_yearly,monthly,yearly',
//         // ]);

//         // $user = Auth::user();

//         // if (!$user->isSubscribed()) {
//         //     return response()->json([
//         //         'success' => false,
//         //         'message' => 'User is not subscribed',
//         //     ], 400);
//         // }

//         // // Determine new price ID
//         // $tier = $user->getSubscriptionTier(); // "basic" or "premium"
//         // $priceSlug = $request->price_slug;

//         // if ($priceSlug === 'monthly') {
//         //     $priceSlug = $tier . '_monthly';
//         // } elseif ($priceSlug === 'yearly') {
//         //     $priceSlug = $tier . '_yearly';
//         // }

//         // $priceId = Plans::where('slug', $priceSlug)->value('stripe_price_id');

//         // if (!$priceId) {
//         //     return response()->json([
//         //         'success' => false,
//         //         'message' => 'Price ID not found for the selected plan.',
//         //     ], 400);
//         // }

//         // try {
//         //     $subscription = $user->subscription('default');

//         //     $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

//         //     // Create a subscription schedule with the existing subscription
//         //     $schedule = $stripe->subscriptionSchedules->create([
//         //     'from_subscription' => $subscription->stripe_id,
//         //     ]);

//         //     // Update the schedule with the new phase
//         //     $stripe->subscriptionSchedules->update(
//         //     $schedule->id,
//         //     [
//         //         'phases' => [   
//         //             [
//         //                 'items' => [
//         //                 [
//         //                     'price' => $schedule->phases[0]->items[0]->price,
//         //                     'quantity' => $schedule->phases[0]->items[0]->quantity,
//         //                 ],
//         //                 ],
//         //                 'start_date' => $schedule->phases[0]->start_date,
//         //                 'end_date' => $schedule->phases[0]->end_date,
//         //             ],
//         //             [
//         //                 'items' => [
//         //                 [
//         //                     'price' => $priceId,
//         //                     'quantity' => 1,
//         //                 ],
//         //                 ],
//         //             ],
//         //         ],
//         //     ]
//         //     );





//             return response()->json([
//                 'success' => true,
//                 'message' => 'Subscription update scheduled successfully for the next billing cycle',
//                 // 'subscription' => [
//                 //     'id' => $subscription->stripe_id,
//                 //     'status' => $subscription->stripe_status,
//                 // ],
//             ]);
//         // } catch (\Exception $e) {
//         //     \Log::info([$e]);
//         //     return response()->json([
//         //         'success' => false,
//         //         'message' => $e->getMessage(),
//         //     ], 400);
//         // }
//     }

    public function updateSubscription(Request $request): JsonResponse
    {
        // Set your secret key. Remember to switch to your live secret key in production.
        // See your keys here: https://dashboard.stripe.com/apikeys
        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

        // Create a subscription schedule with the existing subscription
        $schedule = $stripe->subscriptionSchedules->create([
        'from_subscription' => 'sub_ERf72J8Sc7qx7D',
        ]);

        // Update the schedule with the new phase
        $stripe->subscriptionSchedules->update(
        $schedule->id,
        [
            'phases' => [
            [
                'items' => [
                [
                    'price' => $schedule->phases[0]->items[0]->price,
                    'quantity' => $schedule->phases[0]->items[0]->quantity,
                ],
                ],
                'start_date' => $schedule->phases[0]->start_date,
                'end_date' => $schedule->phases[0]->end_date,
            ],
            [
                'items' => [
                [
                    'price' => '{{PRICE_PRINT_BASIC}}',
                    'quantity' => 1,
                ],
                ],
                'duration' => [
                'interval' => 'month',
                'interval_count' => 1,
                ],
            ],
            ],
        ]
        );


    }


    public function cancelSubscription(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isSubscribed()) {
            return response()->json([
                'success' => false,
                'message' => 'User is not subscribed',
            ], 400);
        }

        try {
            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

            $subscription = $user->subscription('default');
            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active subscription found.',
                ], 400);
            }
            $subscriptionId = $subscription->stripe_id;
            $subscription = $stripe->subscriptions->update
                            (
                            $subscriptionId
                            ,
                            ['cancel_at_period_end' => true]
                            );

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
        $user = Auth::user();
        $subscription = $user->subscription('default');

        // 1. Initial Checks
        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found.',
            ], 400);
        }

        // Check if the subscription is locally marked as paused
        // (You should check for is_paused and/or paused_at)
        if (!$subscription->paused_at) { 
            return response()->json([
                'success' => false,
                'message' => 'Subscription is not currently paused.',
            ], 400);
        }
        
        // NOTE: This check prevents trying to resume a subscription that has ended.
        // if ($subscription->ended()) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Subscription has ended. Please start a new one.',
        //     ], 400);
        // }

        try {
            Stripe::setApiKey(config('services.stripe.secret'));

            // 2. Resume on Stripe (Update the subscription to remove pause_collection)
            // Passing an empty array or null for pause_collection removes the pause setting.
            // $nowTimestamp = Carbon::now()->getTimestamp();
            $nowTimestamp = Carbon::now()->addMinutes(1)->getTimestamp();

            $subscription->updateStripeSubscription([
                'pause_collection' => null, // Or simply pass null
                'proration_behavior' => 'create_prorations',
            ]);

            // 3. Update Local Database (Clear the pause flags)
            $subscription->forceFill([
                'paused_at' => null, // Clear the pause timestamp
                'is_paused' => false, // Set the local flag to false
            ])->save();

            // 4. Update User Status
            $user->status = "active"; // Set user status back to active
            $user->save();

            // 5. Return Success
            return response()->json([
                'success' => true,
                'message' => 'Subscription has been successfully resumed!',
                'stripe_status' => $subscription->fresh()->stripe_status,
                'status' => $user->fresh()->status,
            ]);

        } catch (\Exception $e) {
            // Handle the error, especially the one about the grace period
            if (str_contains($e->getMessage(), 'Unable to resume subscription that is not within grace period')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to resume: The pause period has expired. Please re-subscribe to a plan.',
                ], 400);
            }
            
            \Log::error("Stripe Resume Error for User #{$user->id}: " . $e->getMessage()); 
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to resume subscription: ' . $e->getMessage(),
            ], 400);
        }
    }



    public function getSubscriptionContextDetails(Request $request){
        $user = Auth::user();
        $subscription = $user->subscription();
        \Log::info('logged in with sub : ',[$subscription]);
        $tier = $user->getSubscriptionTier();
        $subscriptionData = null;


        if ($subscription) {
            $plan = Plans::where('stripe_price_id', $subscription->stripe_price)
             ->first(['duration', 'price']);
            \Log::info('logged in with sub Plan : ',[$plan]);

            $endsAt = "";
            if(empty($subscription->ends_at)){
                $startsAt = $subscription->starts_at ?? "";

                if ($startsAt) {
                    \Log::info('logged in with sub Starts at : ',[$startsAt]);

                    $startsAt = Carbon::parse($startsAt); // Make sure it's a Carbon instance
                    \Log::info('logged in with sub Carbon Starts at : ',[$startsAt]);

                    $duration = $plan->duration ?? '';
                    \Log::info('logged in with sub duration at : ',[$duration]);

                    switch (strtolower($duration)) {
                        case 'monthly':
                            $endsAt = $startsAt->copy()->addMonth();
                            break;
                        case 'yearly':
                            $endsAt = $startsAt->copy()->addYear();
                            break;
                        default:
                            $endsAt = ""; // fallback if duration not set
                        }
                    \Log::info('logged in with sub  Ends at : ',[$endsAt]);
                    
                }
            }
            else{
                $endsAt = $subscription->ends_at;
            }
            $subscriptionData = [
                'id' => $subscription->stripe_id ?? "",
                'stripe_status' => $subscription->stripe_status ?? "",
                'price_id' => $subscription->stripe_price ?? "",
                'current_period_end' => $endsAt,
                'tier' => $tier
            ];


            

            $duration = $plan->duration ?? '';
            $price = $plan->price ?? '';

            $subscriptionData['duration'] = $duration;
            $subscriptionData['price'] = $price;

            if($subscription->is_paused){
                $subscriptionData['is_paused'] =[
                        'paused_at'=>$subscription->paused_at ?? "",
                ];
            }
            $discount = SubscriptionDiscount::where("subscription_id",$subscription->id)->get()->first();
            \Log::info('duration:',[$duration]);
            \Log::info('subscription object:',[$subscription]);
            if(!empty($discount) && $discount->status === 'active'){
                $subscriptionData['discount'] = [
                        'discount_ends_at'=>$discount->discount_ends_at ?? "",
                        'discount_value'=>$discount->discount_value ?? "",
                        'discount_type'=>$discount->discount_type ?? "",
                    ];
            }
        }
        return $this->successResponse( [
            'subscription' => $subscriptionData,
        ],'Logged in successfully.');
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
        $user = Auth::user();
        Log::info("[$requestId] Starting getSubscriptionDetails", [
            'user_id' => $user?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);

        try {
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
        $user = Auth::user();
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
            Stripe::setApiKey(config('services.stripe.secret'));
            $resumeDate = Carbon::now()->addMonths(3);                                                                                 

            $subscription->updateStripeSubscription(['pause_collection' => [
                    'behavior' => 'void',
                    'resumes_at' => $resumeDate->getTimestamp()
                    ]
                ]
            );

            $subscription->forceFill(['paused_at' => Carbon::now(),'is_paused'=>true])->save();

            $user->status = "active";
            $user->save();

            $endsAt = "";

            if(empty($subscription->ends_at)){
                $startsAt = $subscription->starts_at ?? "";
                if ($startsAt) {
                    $startsAt = Carbon::parse($startsAt); // Make sure it's a Carbon instance
                    $duration = $plan->duration ?? '';

                    switch (strtolower($duration)) {
                        case 'monthly':
                            $endsAt = $startsAt->copy()->addMonth();
                            break;
                        case 'yearly':
                            $endsAt = $startsAt->copy()->addYear();
                            break;
                        default:
                            $endsAt = ""; // fallback if duration not set
                        }
                }
            }
            else{
                $endsAt = $subscription->ends_at;
            }
            return response()->json([
                'success' => true,
                'message' => 'Subscription paused successfully. It will remain active until the end of the current billing period.',
                'ends_at' => $endsAt,
                'stripe_status' => $subscription->fresh()->stripe_status,
                'status'=>$user->fresh()->status,
                'resume_at'=>$resumeDate
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
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

    public function getSubscriptionTier(){
        $user = Auth::user();
        $subscription = Auth::user()->subscription('default');
        \Log::info('price stipeee : ',[$subscription['stripe_price']]);
        \Log::info(" user :",[$user]);
        \Log::info("tier  :",[$user->subscriptions()]);
        if($user){
            \Log::info([$user]);
            $tier = $user->getSubscriptionTier();
            \Log::info($tier);
            return response()->json([
                'success' => true,
                'tier' => $tier,
            ]);
        }
    }

    public function applyDiscount(Request $request){
        $validator = Validator::make($request->all(),[
            'promo_code'=>'required|string',
        ]);

        if($validator->fails()){
            \Log::warning('Validation failed:', $validator->errors()->toArray());

            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        $validated = $validator->validated();
        $promo_code = $validated['promo_code'];
        $user = Auth::user();

        if(!$user){
            return $this->successresponse('user Not Found');
        }

        $subscription = $user->subscription('default');
        if (!$subscription || !$subscription->valid()) {
            return response()->json(['success' => false, 'message' => 'No active subscription found.'], 400);
        }

        try {
            \Log::info("hatt");
            $coupon = Coupon::getCouponDetails($promo_code)->first();
                \Log::info("coupon : ", $coupon->toArray());
            $stripeSubscriptionId = $subscription->stripe_id;
            Stripe::setApiKey(config('services.stripe.secret'));
            $user->subscription('default')->updateStripeSubscription(['coupon' => $promo_code]);

            // $user->syncSubscription($stripeSubscriptionId);

            $updatedStripeSubscription = \Stripe\Subscription::retrieve($stripeSubscriptionId, ['expand' => ['discount']]);
            \Log::info("updated subscription : ", $updatedStripeSubscription->toArray());
            if(isset($updatedStripeSubscription->discount)){
                $discount = $updatedStripeSubscription->discount->coupon;
                \Log::info("discount : ", $discount->toArray());

                $discount_applied_at = \Carbon\Carbon::now()->toDateString();
                $duration_in_months = $coupon->duration_in_months ?? 0;
                $duration_in_days = $coupon->duration_in_days ?? 0;
                $discount_ends_at =\Carbon\Carbon::createFromFormat('Y-m-d', $discount_applied_at)
                                        ->addMonths($duration_in_months)
                                        ->addDays($duration_in_days)
                                        ->toDateString();

                SubscriptionDiscount::updateOrCreate([
                    'subscription_id'       => $subscription->id],[
                    'discount_type'         => $discount->percent_off ? 'percent' : ($discount->amount_off ? 'amount' : 'unknown'),
                    'discount_id'           => $discount->id,
                    'discount_value'        => $discount->percent_off ?? ($discount->amount_off ? $discount->amount_off / 100 : null) ?? null,
                    'discount_applied_at'   => $discount_applied_at,
                    'discount_ends_at'      => $discount_ends_at ?? null,
                ]);

            }
            return $this->successResponse('30% discount successfully applied!');

        } catch (\Throwable $th) {
            //throw $th;
            // Log the error and return a failure response
            \Log::error('Stripe Discount Error: ' . $th->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to apply discount. Please try again.'], 500);
        }
    }

}
