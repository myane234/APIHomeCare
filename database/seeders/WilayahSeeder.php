<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use App\Models\WilayahLayanan;
use App\Models\KotaKabupaten;
use App\Models\Kecamatan;
use App\Models\Kelurahan;

class WilayahSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(?callable $progress = null): void
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '2048M');

        $directoryPath = database_path('seeders/data');
        $filePath = "{$directoryPath}/wilayah_indonesia.json";

        // Cek apakah file JSON ada
        if (!File::exists($filePath)) {
            $this->command?->error("File JSON tidak ditemukan di: {$filePath}");
            return;
        }

        $this->command?->info('1. Membaca file JSON wilayah...');
        
        // Membaca dan decode JSON ke array
        $jsonString = File::get($filePath);
        $provinces = json_decode($jsonString, true) ?? [];

        if (empty($provinces)) {
            $this->command?->warn('File JSON kosong atau format tidak valid.');
            return;
        }

        if ($progress) {
            $progress('source_loaded', ['total_provinces' => count($provinces)]);
        }

        // Iterasi Provinsi
        foreach ($provinces as $prov) {
            $this->command?->info("=== Provinsi: {$prov['name']} ===");
            
            if ($progress) {
                $progress('province_started', ['province' => $prov]);
            }

            // 1. Save / Update Provinsi
            WilayahLayanan::updateOrCreate(
                ['id_provinsi' => $prov['id']],
                [
                    'nama_provinsi' => $prov['name'],
                    'is_active'     => true
                ]
            );

            $regencies = $prov['regencies'] ?? [];

            // 2. Iterasi Kabupaten/Kota
            foreach ($regencies as $city) {
                KotaKabupaten::updateOrCreate(
                    ['id_kota' => $city['id']],
                    [
                        'nama_kota'   => $city['name'],
                        'id_provinsi' => $prov['id']
                    ]
                );

                $districts = $city['districts'] ?? [];

                // 3. Iterasi Kecamatan
                foreach ($districts as $district) {
                    Kecamatan::updateOrCreate(
                        ['id_kecamatan' => $district['id']],
                        [
                            'regency_id'     => $city['id'],
                            'nama_kecamatan' => $district['name'],
                        ]
                    );

                    $villages = $district['villages'] ?? [];
                    $kelurahanBatch = [];

                    // Prepare batch data untuk Kelurahan per kecamatan
                    foreach ($villages as $village) {
                        $kelurahanBatch[] = [
                            'id_kelurahan'   => $village['id'],
                            'id_kecamatan'   => $district['id'],
                            'nama_kelurahan' => $village['name'],
                            'created_at'     => now(),
                            'updated_at'     => now(),
                        ];
                    }

                    // 4. Upsert Kelurahan sekaligus per Kecamatan (lebih cepat dari updateOrCreate satu per satu)
                    if (!empty($kelurahanBatch)) {
                        Kelurahan::upsert(
                            $kelurahanBatch,
                            ['id_kelurahan'],
                            ['id_kecamatan', 'nama_kelurahan', 'updated_at']
                        );
                    }
                }

                $this->command?->info("  -> Selesai: {$city['name']}");

                if ($progress) {
                    $progress('city_processed', [
                        'city' => $city, 
                        'districts' => count($districts)
                    ]);
                }
            }
        }

        $this->command?->info("==================================================");
        $this->command?->info("Proses seeding wilayah dari JSON selesai!");
    }
}