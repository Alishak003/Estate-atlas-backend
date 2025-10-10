<?php

namespace App\Models;

use App\Models\AffiliateVisit;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Affiliate extends Model
{
    protected $fillable = [
        'user_id',
        'affiliate_code',
        'total_clicks',
        'total_referrals',
        'total_commission',
        'commission_rate',
        'status',
        'total_visits',
        'affiliate_link',
        'affiliate_code',
        'link_generated_count',
        'visits_count',
    ];

    protected $appends = ['affiliate_link'];

    protected $casts = [
        'total_visits' => 'integer',
        'total_clicks' => 'integer',
        'total_referrals' => 'integer',
        'total_commission' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'link_generated_count' => 'integer',
        'visits_count' => 'integer',
    ];

    public function getAffiliateLinkAttribute()
    {
        // Return the correct URL format that we want to track
        return "http://204.197.173.249:7532/auth/register/?code=" . $this->affiliate_code;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function clicks()
    {
        return $this->hasMany(Affiliate_click::class);
    }

    // Add this method to update visit count
    public function updateVisitCount()
    {
        $this->total_visits = $this->clicks()->count();
        $this->save();
    }



    public function visits(): HasMany
    {
        return $this->hasMany(AffiliateVisit::class);
    }
}
