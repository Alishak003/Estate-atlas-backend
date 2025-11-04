<?php

namespace App\Http\Controllers\Auth;

use App\Traits\ApiResponse;
use App\Jobs\Auth\LoginUserJob;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Auth\LoginRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\Plans;
use App\Models\SubscriptionDiscount;


class LoginController extends Controller
{
    use ApiResponse;
    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();
        if (!$token = Auth::attempt($credentials)) {
            return $this->unauthorizedResponse('Invalid credentials.');
        }

        $user = Auth::user();
        $subscription = $user->subscription();
        \Log::info('logged in with sub : ',[$subscription]);
        $tier = $user->getSubscriptionTier();
        $subscriptionData = null;


        if ($subscription) {
            $plan = Plans::where('stripe_price_id', $subscription->stripe_price)
                                 ->first(['duration', 'price']);
            
            $duration = $plan->duration ?? '';
            $price = $plan->price ?? '';

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
            \Log::info('duration:',[$subscription->strip_price]);

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


        $payload = [
            'user' => $user,
            'iss'  => URL::secure('/'),
            'exp'  => Carbon::now()->addDays(7)->timestamp, // Token expires in 7 days
        ];

        $token = JWTAuth::claims($payload)->fromUser($user);
        $cookie = cookie('auth_token', $token, 60, '/', null, true, true, false, 'Strict');

        LoginUserJob::dispatch($user->id, now()->toDateTimeString());
        return $this->successResponse('Logged in successfully.', [
            'token' => $token,
            'user' => $user,
            'subscription' => $subscriptionData,
        ])->withCookie($cookie);
    }
}
