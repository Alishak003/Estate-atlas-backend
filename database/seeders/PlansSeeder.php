<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlansSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'basic_monthly',
                'name' => 'Basic Monthly Subscription',
                'price' => 29,
                'duration' => 'monthly',
                'stripe_price_id' => 'price_1SDMAnRzsDq04jEjj80UfhHp',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'basic_yearly',
                'name' => 'Basic Yearly Subscription',
                'price' => 288,
                'duration' => 'yearly',
                'stripe_price_id' => 'price_1SEFYYRzsDq04jEjcSQhnJW9',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'premium_monthly',
                'name' => 'Premium Monthly Subscription',
                'price' => 49,
                'duration' => 'monthly',
                'stripe_price_id' => 'price_1SDMFKRzsDq04jEjkQZpu3kG',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'premium_yearly',
                'name' => 'Premium Yearly Subscription',
                'price' => 528,
                'duration' => 'yearly',
                'stripe_price_id' => 'price_1SDMGVRzsDq04jEjF198a1uJ',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('plans')->insert($plans);
    }
}
