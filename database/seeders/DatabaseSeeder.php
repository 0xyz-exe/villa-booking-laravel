<?php

namespace Database\Seeders;

use App\Models\Amenity;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Wi-Fi', 'Air conditioning', 'Kitchen', 'Pool', 'Parking', 'Washer', 'Pet friendly'] as $name) {
            Amenity::firstOrCreate(['name' => $name]);
        }
    }
}
