<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Affiliate_click extends Model
{
    protected $table = 'affiliate_clicks'; // Make sure this matches your table name

    protected $fillable = [
        'affiliate_id',
        'ip_address',
        'user_agent',
        'referer',
        'clicked_at',
        'code'
    ];

    protected $dates = [
        'clicked_at'
    ];

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }
}
