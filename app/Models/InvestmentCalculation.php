<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvestmentCalculation extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_value',
        'down_payment',
        'interest_rate',
        'loan_term',
        'monthly_rental_income',
        'monthly_expenses',
        'monthly_mortgage',
        'annual_cash_flow',
        'roi_percent'
    ];
}
