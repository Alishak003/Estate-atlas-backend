<?php

namespace App\Http\Controllers\Auth;

use App\Traits\ApiResponse;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Affiliate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log
;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RegisterController extends Controller
{
    use ApiResponse;
    public function register(Request $request)
    {
        // Log the incoming request data
        Log::info('Register request data:', $request->all());

        return $this->safeCall(function () use ($request) {
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'affiliate_code' => 'nullable|string|exists:affiliates,affiliate_code'
            ]);

            // Log validation errors if validation fails
            if ($validator->fails()) {
                Log::error('Validation error during registration:', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $referredBy = null;
            if (!empty($request->affiliate_code)) {
                $affiliate = Affiliate::where('affiliate_code', $request->affiliate_code)->first();
                if ($affiliate) {
                    Log::info('Affiliate found:', ['affiliate_code' => $request->affiliate_code, 'affiliate_id' => $affiliate->id]);
                    $referredBy = $affiliate->id;
                } else {
                    Log::warning('Affiliate not found for code:', ['affiliate_code' => $request->affiliate_code]);
                }
            }

            // Log the referredBy value
            Log::info('Referred By ID:', ['referred_by' => $referredBy]);

            // Create the user
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'referred_by' => $referredBy,  // Set the referred_by field
                'role' => 'user'
            ]);

            // Log user creation success
            Log::info('User created successfully:', ['user_id' => $user->id]);

            // Prepare the payload and generate JWT token
            $payload = [
                'user' => $user,
                'iss' => URL::secure('/'),
                'exp' => Carbon::now()->addDays(7)->timestamp, // Token expires in 7 days
            ];
            $token = JWTAuth::claims($payload)->fromUser($user);

            // Log token generation
            Log::info('JWT token generated for user:', ['user_id' => $user->id, 'token' => $token]);

            // Set the cookie
            $cookie = cookie('auth_token', $token, 60, '/', null, true, true, false, 'Strict');

            // Log the response sent to the client
            Log::info('Sending registration response for user:', ['user_id' => $user->id]);

            return $this->successResponse('User registered successfully', [
                'data' => $user,
                'token' => $token,
                'app_url' => URL::secure('/'),
            ])->withCookie($cookie);
        });
    }

    public function registerAndSubscribe(Request $request)
    {
        // 1. Validate all fields
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'price_id' => 'required|string', // subscription plan
            // 'payment_method' => 'required|string',
            'affiliate_code' => 'nullable|string|exists:affiliates,affiliate_code'
        ]);
        if ($validator->fails()) {
            \Log::warning('Validation failed:', $validator->errors()->toArray());

            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Map label to Stripe Price ID if needed
        $price_id = $request->price_id;
        if (array_key_exists($price_id, $priceIdMap)) {
            $price_id = $priceIdMap[$price_id];
        }

        // 2. If referral code is present, find the referrer affiliate
        $referrerAffiliate = null;
        if (!empty($request->affiliate_code)) {
            $referrerAffiliate = \App\Models\Affiliate::with('user')->where('affiliate_code', $request->affiliate_code)->first();
        }

        DB::beginTransaction();
        try {
            $tempUser = new \App\Models\User([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
            ]);

            $tempUser->createAsStripeCustomer();


            $user = $tempUser;
           
            if (!empty($referrerAffiliate) && $referrerAffiliate->user_id !== $user->id) {
                
                $firstItem = $subscription->items->first();
                $amount = null;
                if ($firstItem && $firstItem->price) {
                    $amount = $firstItem->price->unit_amount / 100;
                } elseif ($firstItem && $firstItem->stripe_price) {
                    $stripePrice = \Stripe\Price::retrieve($firstItem->stripe_price);
                    $amount = $stripePrice->unit_amount / 100;
                }
                if ($amount !== null) {
                    $commission = $amount * 0.5;
                    $referrerAffiliate->total_commission += $commission;
                    $referrerAffiliate->total_referrals += 1;
                    $referrerAffiliate->save();

                    // Automatically transfer the commission
                    $referrerUser = $referrerAffiliate->user;
                    if ($referrerUser && $referrerUser->stripe_connect_id) {
                        \Illuminate\Support\Facades\Log::info('Referrer has a Stripe Connect account. Attempting payout.', [
                            'referrer_user_id' => $referrerUser->id,
                            'stripe_connect_id' => $referrerUser->stripe_connect_id
                        ]);
                        try {
                            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                            $transferPayload = [
                                'amount' => $commission * 100, // Amount in cents
                                'currency' => 'usd',
                                'destination' => $referrerUser->stripe_connect_id,
                                'transfer_group' => 'commission_payout_' . $subscription->id,
                            ];
                            \Illuminate\Support\Facades\Log::info('Stripe transfer payload:', $transferPayload);
                            \Stripe\Transfer::create($transferPayload);
                            \Illuminate\Support\Facades\Log::info('Stripe transfer successful.');
                        } catch (\Exception $e) {
                            // Log the failed transfer but don't stop the registration
                            \Illuminate\Support\Facades\Log::error('Stripe transfer failed: ' . $e->getMessage(), [
                                'trace' => $e->getTraceAsString()
                            ]);
                        }
                    } else {
                        \Illuminate\Support\Facades\Log::warning('Referrer does not have a Stripe Connect account set up. Skipping payout.', [
                            'referrer_user_id' => $referrerUser ? $referrerUser->id : null
                        ]);
                    }
                }
            }

            DB::commit();
            // Generate JWT token for the user
            $payload = [
                'user' => $user,
                'iss' => url('/'),
                'exp' => Carbon::now()->addDays(7)->timestamp, // Token expires in 7 days
            ];
           try {
            $token = JWTAuth::claims($payload)->fromUser($user);
                } catch (\Exception $e) {
                    \Log::error('JWT token creation failed: ' . $e->getMessage(), [
                        'trace' => $e->getTraceAsString(),
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'JWT token creation failed.',
                        'error' => $e->getMessage()
                    ], 500);
                }
            $cookie = cookie('auth_token', $token, 60, '/', null, true, true, false, 'Strict');
            return response()->json([
                'success' => true,
                'user' => $user,
                'token' => $token,
                'affiliate_code' => $affiliateCode,
                // 'subscription' => [
                //     'id' => $subscription->stripe_id,
                //     'status' => $subscription->stripe_status,
                // ]
            ])->withCookie($cookie);
        } catch (\Exception $e) {
            \Log::error('RegisterAndSubscribe error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
