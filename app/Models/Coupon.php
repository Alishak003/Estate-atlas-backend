<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    public static function getCouponDetails($name){
        if($name){
            return Coupon::where('code',$name)->get();
        }
    }
}
