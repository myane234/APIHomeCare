<?php

namespace Database\Seeders;

use App\Models\PointSetting;
use Illuminate\Database\Seeder;

class PointSettingSeeder extends Seeder
{
    /**
     * Seed konfigurasi awal fitur poin pasien.
     *
     * point_rate        = 10000  → setiap kelipatan Rp10.000 dapat 1 poin
     * point_expiry_days = 365    → poin berlaku 1 tahun sejak diperoleh
     * is_active         = true   → fitur poin aktif
     */
    public function run(): void
    {
        PointSetting::firstOrCreate(
            ['id' => 1],
            [
                'point_rate'        => 10000,
                'point_expiry_days' => 365,
                'is_active'         => true,
            ]
        );
    }
}
