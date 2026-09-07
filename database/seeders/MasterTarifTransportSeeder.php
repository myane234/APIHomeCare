<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterTarifTransportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $transport_data = [
            ['id_kota' => 3171, 'tarif_awal' => 10000.00, 'tarif_per_kilometer' => 2500.00], // Kota Jakarta Selatan
            ['id_kota' => 3172, 'tarif_awal' => 10000.00, 'tarif_per_kilometer' => 2500.00], // Kota Jakarta Timur
            ['id_kota' => 3173, 'tarif_awal' => 12000.00, 'tarif_per_kilometer' => 3000.00], // Kota Jakarta Pusat
            ['id_kota' => 3174, 'tarif_awal' => 10000.00, 'tarif_per_kilometer' => 2500.00], // Kota Jakarta Barat
            ['id_kota' => 3175, 'tarif_awal' => 12000.00, 'tarif_per_kilometer' => 3000.00], // Kota Jakarta Utara
            ['id_kota' => 3271, 'tarif_awal' => 8000.00,  'tarif_per_kilometer' => 2000.00], // Kota Bogor
            ['id_kota' => 3273, 'tarif_awal' => 10000.00, 'tarif_per_kilometer' => 2500.00], // Kota Bandung
            ['id_kota' => 3275, 'tarif_awal' => 10000.00, 'tarif_per_kilometer' => 2500.00], // Kota Bekasi
            ['id_kota' => 3276, 'tarif_awal' => 10000.00, 'tarif_per_kilometer' => 2500.00], // Kota Depok
            ['id_kota' => 3578, 'tarif_awal' => 10000.00, 'tarif_per_kilometer' => 2500.00], // Kota Surabaya
        ];

        foreach ($transport_data as $tr) {
            DB::table('master_tarif_transport')->updateOrInsert(
                ['id_kota' => $tr['id_kota']],
                array_merge($tr, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
