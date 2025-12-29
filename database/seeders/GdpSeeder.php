<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GdpSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['year' => 2021, 'country' => 'Mexico', 'gdp' => 0.00],
            ['year' => 2022, 'country' => 'Mexico', 'gdp' => 3.71],
            ['year' => 2023, 'country' => 'Mexico', 'gdp' => 3.35],
            ['year' => 2024, 'country' => 'Mexico', 'gdp' => 1.43],
            ['year' => 2021, 'country' => 'Mexico', 'gdp' => 0.00],
            ['year' => 2022, 'country' => 'Mexico', 'gdp' => 3.71],
            ['year' => 2023, 'country' => 'Mexico', 'gdp' => 3.35],
            ['year' => 2024, 'country' => 'Mexico', 'gdp' => 1.43],
        ];

        foreach ($data as $row) {
            DB::table('gdp_data')->insert(array_merge($row, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
