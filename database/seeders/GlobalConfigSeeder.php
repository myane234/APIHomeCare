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
                'meta_title' => 'Smart Home Care - Layanan Kesehatan Home Care Terpercaya',
                'meta_description' => 'Kami menyediakan layanan kesehatan home care profesional langsung ke rumah Anda.',
                'meta_keywords' => 'homecare, kesehatan, perawat, dokter, fisioterapi',
                'whatsapp_number' => '6281234567890',
                'phone_number' => '0211234567',
                'email' => 'info@smarthomecare.com',
                'address' => 'Jl. Kesehatan No. 123, Jakarta Selatan',
                'running_text' => 'Selamat datang di Smart Home Care! Dapatkan potongan harga 10% untuk pemesanan pertama Anda.',
                'maintenance_mode' => false,
            ]
        );
    }
}
