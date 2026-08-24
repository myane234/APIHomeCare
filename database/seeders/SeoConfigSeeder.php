<?php

namespace Database\Seeders;

use App\Models\SeoConfig;
use Illuminate\Database\Seeder;

class SeoConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SeoConfig::firstOrCreate(
            ['id' => 1],
            [
                'meta_title' => 'Smart Home Care - Layanan Kesehatan Home Care Terpercaya',
                'meta_description' => 'Kami menyediakan layanan kesehatan home care profesional langsung ke rumah Anda.',
                'meta_keywords' => 'homecare, kesehatan, perawat, dokter, fisioterapi',
            ]
        );
    }
}
