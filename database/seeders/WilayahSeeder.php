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

        $now = now();
        $provBatch = [];
        $cityBatch = [];
        $districtBatch = [];
        $villageBatch = [];

        foreach ($provinces as $prov) {
            $provBatch[] = [
                'id_provinsi'   => $prov['id'],
                'nama_provinsi' => $prov['name'],
                'is_active'     => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];

            foreach ($prov['regencies'] ?? [] as $city) {
                $cityBatch[] = [
                    'id_kota'     => $city['id'],
                    'id_provinsi' => $prov['id'],
                    'nama_kota'   => $city['name'],
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];

                foreach ($city['districts'] ?? [] as $district) {
                    $districtBatch[] = [
                        'id_kecamatan'   => $district['id'],
                        'regency_id'     => $city['id'],
                        'nama_kecamatan' => $district['name'],
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ];

                    foreach ($district['villages'] ?? [] as $village) {
                        $villageBatch[] = [
                            'id_kelurahan'   => $village['id'],
                            'id_kecamatan'   => $district['id'],
                            'nama_kelurahan' => $village['name'],
                            'created_at'     => $now,
                            'updated_at'     => $now,
                        ];
                    }
                }
            }
        }

        $this->command?->info("Upserting " . count($provBatch) . " provinsi...");
        WilayahLayanan::upsert($provBatch, ['id_provinsi'], ['nama_provinsi', 'is_active', 'updated_at']);

        $this->command?->info("Upserting " . count($cityBatch) . " kota/kabupaten...");
        foreach (array_chunk($cityBatch, 250) as $chunk) {
            KotaKabupaten::upsert($chunk, ['id_kota'], ['id_provinsi', 'nama_kota', 'updated_at']);
        }

        $this->command?->info("Upserting " . count($districtBatch) . " kecamatan...");
        foreach (array_chunk($districtBatch, 500) as $chunk) {
            Kecamatan::upsert($chunk, ['id_kecamatan'], ['regency_id', 'nama_kecamatan', 'updated_at']);
        }

        $this->command?->info("Upserting " . count($villageBatch) . " kelurahan...");
        foreach (array_chunk($villageBatch, 1000) as $chunk) {
            Kelurahan::upsert($chunk, ['id_kelurahan'], ['id_kecamatan', 'nama_kelurahan', 'updated_at']);
        }

        $this->command?->info("==================================================");
        $this->command?->info("Proses seeding wilayah dari JSON selesai!");
    }
}