<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plans extends Model
{
    protected $table = 'plans';

    protected $fillable = [
        'slug',
        'name',
        'status',
        'stripe_price_id',
        'price'
    ];
}
