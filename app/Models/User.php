<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Billable;

    const ROLE_USER = 'user';
    const ROLE_ADMIN = 'admin';

    protected $table = 'users';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'role',
        'phone',
        'referred_by',
        'referral_source',
        'subscription_id',
        'subscription_amount',
        'subscription_currency',
        'subscription_start_date',
        'stripe_connect_id',
    ];


    protected $hidden = [
        'password',
        'remember_token',
    ];


    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function isAdmin(): bool
    {
        // If you use a 'role' column
        return $this->role === self::ROLE_ADMIN;
        // If you use a boolean 'is_admin' column, use:
        // return (bool) $this->is_admin;
    }

    public function isSubscribed()
    {
        return $this->subscribed('default');
    }

    public function hasBasicSubscription()
    {
        return $this->subscribed('default', ['price_1Ra9wzDgYV6zJ17vI6UiuhLp', 'price_1Ra9xgDgYV6zJ17v4zCAQGLZ']);
    }

    public function hasPremiumSubscription()
    {
        return $this->subscribed('default', ['price_1Ra9ynDgYV6zJ17v2HMSLJpe', 'price_1Ra9zADgYV6zJ17v7B4zNE1r']);
    }

    public function getSubscriptionTier()
    {
        if (!$this->isSubscribed()) {
            return 'none';
        }

        if ($this->hasPremiumSubscription()) {
            return 'premium';
        }

        if ($this->hasBasicSubscription()) {
            return 'basic';
        }

        return 'unknown';
    }

    public function affiliate(): HasOne
    {
        return $this->hasOne(\App\Models\Affiliate::class);
    }

    public function generateAffiliateCode(): Affiliate
    {
        if(!$this->affiliate_code){
        $code = strtoupper(uniqid('AFF'));
        $affiliate = new Affiliate();
        $affiliate->user_id = $this->id;
        $affiliate->affiliate_code = $code;
        $affiliate->save();
        return $affiliate;

        }


    }
}
