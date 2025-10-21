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
        'link_generated_count',
    ];

    protected $appends = ['affiliate_link'];

    protected $casts = [
        'total_visits' => 'integer',
        'total_clicks' => 'integer',
        'total_referrals' => 'integer',
        'total_commission' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'link_generated_count' => 'integer',
    ];

    public function getAffiliateLinkAttribute()
    {
        // Return the correct URL format that we want to track
        return config('app.url') .'/auth/register/?code='. $this->affiliate_code ;
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

    public function generateAffiliateCode($userId): ?Affiliate
    {
        // Check if user already has an affiliate record
        $code = strtoupper(uniqid('AFF'));
        $affiliate = Affiliate::where('user_id',$userId)->first();
        if($affiliate && $affiliate->affiliate_code){
            return $affiliate;
        }
        if(!$affiliate){
        $affiliate = new Affiliate();
        $affiliate->user_id = $this->id;
        $affiliate->commission_rate = 50.00;
        $affiliate->status = 'active';
        $affiliate->total_clicks = 0;
        $affiliate->total_referrals = 0;
        $affiliate->total_commission = 0.00;
        $affiliate->stripe_connect_account_id = null;
        }
        $affiliate->affiliate_code = $code;
        $affiliate->save();

        return $affiliate;
    }

}
