<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
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
    public function run(): void
    {
        // Hilangkan limit waktu eksekusi & naikkan batas memori
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '2048M');

        $baseUrl = 'https://www.emsifa.com/api-wilayah-indonesia/api';

        // Lokasi penyiapan folder dan nama file JSON keluaran
        $directoryPath = database_path('seeders/data');
        $filePath = "{$directoryPath}/wilayah_indonesia.json";

        if (!File::exists($directoryPath)) {
            File::makeDirectory($directoryPath, 0755, true);
        }

        $this->command->info("1. Mengambil data Provinsi dari API...");
        $responseProvinsi = Http::get("{$baseUrl}/provinces.json");

        if ($responseProvinsi->failed()) {
            $this->command->error("Gagal mengambil data Provinsi.");
            return;
        }

        $provinces = $responseProvinsi->json();
        $exportData = [];

        foreach ($provinces as $prov) {
            // A. Simpan ke Database (Provinsi)
            $provinsiModel = WilayahLayanan::updateOrCreate(
                ['nama_provinsi' => $prov['name']],
                ['is_active' => true]
            );

            $this->command->info("=== Provinsi: {$prov['name']} ===");

            // Struktur array JSON untuk Provinsi
            $provData = [
                'id' => $prov['id'],
                'name' => $prov['name'],
                'regencies' => []
            ];

            // B. Mengambil Kota/Kabupaten
            $responseKota = Http::get("{$baseUrl}/regencies/{$prov['id']}.json");

            if ($responseKota->successful()) {
                $cities = $responseKota->json();

                foreach ($cities as $city) {
                    // Simpan ke Database (Kota)
                    $kotaModel = KotaKabupaten::updateOrCreate(
                        ['nama_kota' => $city['name']],
                        ['id_provinsi' => $provinsiModel->id_provinsi]
                    );

                    $cityData = [
                        'id' => $city['id'],
                        'province_id' => $city['province_id'] ?? $prov['id'],
                        'name' => $city['name'],
                        'districts' => []
                    ];

                    // C. Mengambil Kecamatan
                    $responseDistricts = Http::get("{$baseUrl}/districts/{$city['id']}.json");

                    if ($responseDistricts->successful()) {
                        $districts = $responseDistricts->json();

                        foreach ($districts as $district) {
                            // Simpan ke Database (Kecamatan)
                            Kecamatan::updateOrCreate(
                                ['id_kecamatan' => $district['id']],
                                [
                                    'regency_id' => $city['id'],
                                    'nama_kecamatan' => $district['name'],
                                ]
                            );

                            $districtData = [
                                'id' => $district['id'],
                                'regency_id' => $district['regency_id'] ?? $city['id'],
                                'name' => $district['name'],
                                'villages' => []
                            ];

                            // D. Mengambil Kelurahan/Desa
                            $responseVillages = Http::get("{$baseUrl}/villages/{$district['id']}.json");

                            if ($responseVillages->successful()) {
                                $villages = $responseVillages->json();
                                $kelurahanBatch = [];

                                foreach ($villages as $village) {
                                    // Untuk Database
                                    $kelurahanBatch[] = [
                                        'id_kelurahan' => $village['id'],
                                        'id_kecamatan' => $district['id'],
                                        'nama_kelurahan' => $village['name'],
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                    ];

                                    // Untuk JSON File
                                    $districtData['villages'][] = [
                                        'id' => $village['id'],
                                        'district_id' => $village['district_id'] ?? $district['id'],
                                        'name' => $village['name'],
                                    ];
                                }


                                if (!empty($kelurahanBatch)) {
                                    Kelurahan::upsert(
                                        $kelurahanBatch,
                                        ['id_kelurahan'],
                                        ['id_kecamatan', 'nama_kelurahan', 'updated_at']
                                    );
                                }
                            }

                            $cityData['districts'][] = $districtData;
                        }
                    }

                    $provData['regencies'][] = $cityData;
                    $this->command->info("  -> Berhasil memproses & menyusun JSON untuk: {$city['name']}");
                }
            }

            $exportData[] = $provData;
        }

        $jsonContents = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        File::put($filePath, $jsonContents);

        $this->command->info("==================================================");
        $this->command->info("File JSON berhasil dibuat di: {$filePath}");
        $this->command->info("Seeder dan ekspor JSON seluruh wilayah selesai!");
    }
}