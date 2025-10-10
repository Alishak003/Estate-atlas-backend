<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class helpSupport extends Model
{
    protected $fillable = [
        'subject',
        'priority',
        'message',
    ];
}
