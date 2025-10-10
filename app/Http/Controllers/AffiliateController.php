<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Affiliate;
use Illuminate\Http\Request;
use App\Models\Affiliate_click;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\AffiliateResource;
use Symfony\Component\HttpFoundation\Response;
use App\Exceptions\MethodNotAllowedException; // Ensure this is included
use App\Traits\HandlesApiResponses; // Include the HandlesApiResponses trait
use App\Traits\ChecksRequestTimeout; // Include the ChecksRequestTimeout trait
use App\Traits\AuthenticatedUserCheck; // Include the AuthenticatedUserCheck trait

class AffiliateController extends Controller
{
    use HandlesApiResponses, AuthenticatedUserCheck, ChecksRequestTimeout; // Use all three traits
    // Generate affiliate link for authenticated user
    public function generateLink(Request $request): JsonResponse
    {
        $startTime = microtime(true); // Start time for tracking request duration

        try {
            // Check if the user is authenticated and has a valid token
            $this->checkIfAuthenticatedAndUser();

            // Ensure only GET requests are allowed
            if (!in_array($request->method(), ['GET'])) {
                throw new MethodNotAllowedException('HTTP method not allowed');
            }

            $user = Auth::user();

            Log::info($user);
            $affiliate = $user->generateAffiliateCode();
            Log::info($affiliate);

            if (!$affiliate) {
                throw new \Exception('Failed to generate affiliate code');
            }

            // Update the affiliate's total click count, referrals, and commission
            // $this->updateAffiliateTotals($affiliate);

            // Check request timeout
            $affiliate = \App\Models\Affiliate::create([
                'user_id' => $user->id,
                'affiliate_code' => $affiliateCode,
                'commission_rate' => 50.00,
                'status' => 'active',
                'total_clicks' => 0,
                'total_referrals' => 0,
                'total_commission' => 0.00,
            ]);
            $this->checkRequestTimeout($startTime); // Timeout check

            return $this->successResponse(
                new AffiliateResource($affiliate),
                'Affiliate link generated successfully'
            );
        } catch (MethodNotAllowedException $e) {
            // Handle MethodNotAllowedException explicitly and return 405 status code
            return $this->errorResponse(
                'HTTP method not allowed',
                Response::HTTP_METHOD_NOT_ALLOWED, // 405
                'ERROR',
                $e->getMessage()
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to generate affiliate link',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'ERROR',
                $e->getMessage()
            );
        }
    }



    public function trackClick($code)
    {
        \Log::info("trackClick called", [
            'code' => $code,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl()
        ]);

        $affiliate = \DB::table('affiliates')->where('affiliate_code', $code)->first();

        if (!$affiliate) {
            \Log::error("Affiliate not found", ['code' => $code]);
            abort(404, 'Invalid affiliate code');
        }

        $ip = request()->ip();
        $agent = request()->userAgent();

        try {
            // Check if click already exists
            $exists = \DB::table('affiliate_clicks')
                ->where('affiliate_id', $affiliate->id)
                ->where('ip_address', $ip)
                ->where('user_agent', $agent)
                ->exists();

            if (!$exists) {
                // Only insert if click doesn't exist
                \DB::table('affiliate_clicks')->insert([
                    'affiliate_id' => $affiliate->id,
                    'ip_address' => $ip,
                    'user_agent' => $agent,
                    'code' => $code,
                    'clicked_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Update total clicks in affiliates table
                $totalClicks = \DB::table('affiliate_clicks')
                    ->where('affiliate_id', $affiliate->id)
                    ->count();

                \DB::table('affiliates')
                    ->where('id', $affiliate->id)
                    ->update(['total_clicks' => $totalClicks]);

                \Log::info("New click recorded", [
                    'affiliate_id' => $affiliate->id,
                    'total_clicks' => $totalClicks
                ]);
            } else {
                \Log::info("Duplicate click detected", [
                    'affiliate_id' => $affiliate->id,
                    'ip' => $ip
                ]);
            }
        } catch (\Exception $e) {
            \Log::error("Error processing click", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        // Always redirect regardless of whether click was recorded
        $redirectUrl = "http://204.197.173.249:7532/auth/register/?code=$code";
        return redirect()->away($redirectUrl);
    }





    protected function updateAffiliateTotals(Affiliate $affiliate)
    {
        $totalClicks = DB::table('affiliate_clicks')
            ->where('affiliate_id', $affiliate->id)
            ->count();

        $affiliate->total_clicks = $affiliate->clicks()->count();

        $affiliate->total_clicks = $totalClicks;

        $user = User::find($affiliate->user_id);
        // Optional: update other totals like referrals, commissions here

        $affiliate->save();
    }
    // protected function updateAffiliateTotals(Affiliate $affiliate)
    // {
    //     $affiliate->total_clicks = $affiliate->clicks()->count();

    //     // $affiliate->total_referrals = $affiliate->clicks()->where('status', 'referred')->count();

    //     $user = User::find($affiliate->user_id);
    //     // $subscription_amount = $user->subscription_amount;

    //     // $affiliate->total_commission = $subscription_amount * $affiliate->commission_rate / 100;

    //     $affiliate->save();
    // }
    /**
     * Get affiliate info for a user (by id, email, or authenticated user)
     */
    public function getAffiliateInfo(Request $request)
    {
        // You can pass user_id or email as a query param, or get the authenticated user
        $user = null;
        if ($request->has('user_id')) {
            $user = \App\Models\User::find($request->query('user_id'));
        } elseif ($request->has('email')) {
            $user = \App\Models\User::where('email', $request->query('email'))->first();
        } else {
            $user = $request->user();
        }
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        }
        $affiliate = $user->affiliate;
        if (!$affiliate) {
            return response()->json([
                'success' => false,
                'message' => 'Affiliate record not found for this user.'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'user_id' => $user->id,
            'email' => $user->email,
            'affiliate_code' => $affiliate->affiliate_code,
            'total_commission' => $affiliate->total_commission,
            'total_clicks' => $affiliate->total_clicks,
            'total_referrals' => $affiliate->total_referrals,
            'commission_rate' => $affiliate->commission_rate,
            'status' => $affiliate->status,
        ]);
    }
}
