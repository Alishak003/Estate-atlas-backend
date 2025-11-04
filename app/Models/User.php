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
use Laravel\Cashier\Subscription;

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
        'status'
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
        return $this->subscribedToPrice('price_1SDMAnRzsDq04jEjj80UfhHp','default') || $this->subscribedToPrice('price_1SEFYYRzsDq04jEjcSQhnJW9','default') ;
    }

    public function hasPremiumSubscription()
    {
        return $this->subscribedToPrice('price_1SDMGVRzsDq04jEjF198a1uJ','default') || $this->subscribedToPrice('price_1SDMFKRzsDq04jEjkQZpu3kG','default');
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

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function currentPlan()
    {
        // 1. Get the active Cashier subscription
        return $this->subscriptions()
                    ->join('plans', 'plans.stripe_price_id', '=', 'subscriptions.stripe_price')
                    ->select('subscriptions.stripe_status as status','subscriptions.ends_at as renewalDate', 'plans.slug as slug', 'plans.name as name', 'plans.price as price', 'plans.duration as duration','subscriptions.is_paused as is_paused', 'subscriptions.paused_at')
                    ->latest('subscriptions.created_at') // Get the most recent one if multiple exist
                    ->first();
    }

}
