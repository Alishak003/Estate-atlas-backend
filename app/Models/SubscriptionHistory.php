<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionHistory extends Model
{
    protected $table = "subscription_histories";

    protected $fillable = [
        'user_id',
        'stripe_subscription_id',
        'price_id',
        'start_date',
        'end_date',
        'reason'
    ];
    
}
