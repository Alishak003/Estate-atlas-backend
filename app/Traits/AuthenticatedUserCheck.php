<?php

namespace App\Traits;

use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;
use App\Exceptions\ForbiddenException;
use App\Exceptions\InvalidTokenException;
use App\Exceptions\UnauthorizedException;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Exceptions\TokenExpiredException as CustomTokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException as TymonTokenExpiredException;

trait AuthenticatedUserCheck
{
    /**
     * Check if the user has a valid token, is logged in, and is an admin.
     *
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @throws InvalidTokenException
     * @throws CustomTokenExpiredException
     */
    public function checkIfAuthenticatedAndUser()
    {
        try {
            // Check if the token is valid and not expired
            try {
                $user = JWTAuth::parseToken()->authenticate();
                if (!$user) {
                    throw new InvalidTokenException('access token', 'invalid user');
                }
            } catch (TymonTokenExpiredException $e) {
                throw new CustomTokenExpiredException();
            } catch (JWTException $e) {
                throw new InvalidTokenException('access token', 'invalid');
            }

            // Check if the user is logged in and authenticated
            $user = Auth::user();
            if (!$user) {
                throw new UnauthorizedException('You must be logged in.');
            }


        } catch (\Exception $e) {
            // Handle any exceptions appropriately
            throw $e;
        }
    }


}
