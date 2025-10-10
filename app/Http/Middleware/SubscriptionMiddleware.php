<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SubscriptionMiddleware
{
    public function handle(Request $request, Closure $next, ...$tiers)
    {
        $user = $request->user();

        if (!$user || !$user->isSubscribed()) {
            return response()->json([
                'error' => 'Subscription required',
                'message' => 'You need an active subscription to access this resource',
            ], 403);
        }

        if (!empty($tiers)) {
            $userTier = $user->getSubscriptionTier();

            if (!in_array($userTier, $tiers)) {
                return response()->json([
                    'error' => 'Insufficient subscription tier',
                    'message' => 'Your subscription tier does not allow access to this resource',
                    'required_tiers' => $tiers,
                    'current_tier' => $userTier,
                ], 403);
            }
        }

        return $next($request);
    }
}
