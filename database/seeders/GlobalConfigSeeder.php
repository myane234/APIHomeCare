<?php

namespace Database\Seeders;

use App\Models\GlobalConfig;
use Illuminate\Database\Seeder;

class GlobalConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        GlobalConfig::firstOrCreate(
            ['id' => 1],
            [
                'app_name' => 'Smart Home Care',
                'app_logo' => null,
                'app_favicon' => null,
                'whatsapp_number' => '6281234567890',
                'phone_number' => '0211234567',
                'email' => 'info@smarthomecare.com',
                'address' => 'Jl. Kesehatan No. 123, Jakarta Selatan',
                'maintenance_mode' => false,
            ]
        );
    }
}
