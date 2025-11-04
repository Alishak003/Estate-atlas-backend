<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Traits\HandlesApiResponses;
use App\Traits\ChecksRequestTimeout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Traits\AuthenticatedUserCheck;
use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Requests\ChangePasswordRequest;
use Laravel\Cashier\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;


class UserController extends Controller
{
    use HandlesApiResponses, ChecksRequestTimeout, AuthenticatedUserCheck;

    public function getUser(Request $request)
    {
        $this->checkIfAuthenticatedAndUser();
        $startTime = microtime(true);
        $this->checkRequestTimeout($startTime, 30);
        if (!in_array($request->method(), ['GET'])) {
            throw new \App\Exceptions\MethodNotAllowedException();
        }
        $user = Auth::user();
        if (!$user) {
            return $this->errorResponse('User not authenticated.', 401);
        }
        return $this->successResponse($user, 'User retrieved successfully.');
    }

    public function updateProfile(ProfileUpdateRequest $request)
    {

        $this->checkIfAuthenticatedAndUser();
        $startTime = microtime(true);
        $this->checkRequestTimeout($startTime, 30); // 1 second timeout limit (you can adjust this)

        if (!in_array($request->method(), ['POST'])) {
            throw new \App\Exceptions\MethodNotAllowedException();
        }

        $user = Auth::user();
        $user->update($request->validated());

        return $this->successResponse(
            $user,
            'Profile updated successfully.'
        );
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $this->checkIfAuthenticatedAndUser();
        $startTime = microtime(true);

        $this->checkRequestTimeout($startTime, 30); // 1 second timeout limit (you can adjust this)
        if (!in_array($request->method(), ['POST'])) {
            throw new \App\Exceptions\MethodNotAllowedException();
        }

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->errorResponse('Current password is incorrect.', 400);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return $this->successResponse(
            null,
            'Password updated successfully.'
        );
    }

    public function getUserPlan() 
    {
        \Log::info("api hit", [Auth::user()->getAttributes()]);
        $user = Auth::user();
        if(!$user){
            return response()->json(['message'=>'Unautheticated User'],401);
        }
        \Log::info("authenticated");


        $currentSubscription = $user->currentPlan();
        \Log::info("cription",[$currentSubscription]);

        if (!$currentSubscription) {
        return response()->json(['message' => 'No active subscription found.'], 404);
        }

        if (!empty($currentSubscription->renewalDate)) {
            $currentSubscription->renewalDate = Carbon::parse($currentSubscription->renewalDate)
                ->format('M d, Y');
        }


        // 3. Return the data
        return $this->successResponse(
            $currentSubscription
        );
    }
}
