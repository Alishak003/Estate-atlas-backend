<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnemploymentStatsSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['year' => 2014, 'country' => 'Mexico', 'unemployment_rate' => 4.83],
            ['year' => 2015, 'country' => 'Mexico', 'unemployment_rate' => 4.34],
            ['year' => 2016, 'country' => 'Mexico', 'unemployment_rate' => 3.89],
            ['year' => 2017, 'country' => 'Mexico', 'unemployment_rate' => 3.44],
            ['year' => 2018, 'country' => 'Mexico', 'unemployment_rate' => 3.29],
            ['year' => 2019, 'country' => 'Mexico', 'unemployment_rate' => 3.50],
            ['year' => 2020, 'country' => 'Mexico', 'unemployment_rate' => 4.49],
            ['year' => 2021, 'country' => 'Mexico', 'unemployment_rate' => 4.12],
            ['year' => 2022, 'country' => 'Mexico', 'unemployment_rate' => 3.28],
            ['year' => 2023, 'country' => 'Mexico', 'unemployment_rate' => 2.79],
            ['year' => 2024, 'country' => 'Mexico', 'unemployment_rate' => 2.70],
        ];

        DB::table('unemployment_stats')->insert($data);
    }
}
