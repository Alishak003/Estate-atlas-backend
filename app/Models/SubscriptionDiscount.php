<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionDiscount extends Model
{
    protected $table = "subscription_discounts";

    protected $fillable = [
        'subscription_id',
        'discount_type',
        'discount_id',
        'discount_percent',
        'discount_applied_at',
        'discount_ends_at',
    ];
}
