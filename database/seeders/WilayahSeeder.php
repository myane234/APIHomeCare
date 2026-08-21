<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use App\Services\WilayahImportService;
use App\Models\WilayahLayanan;
use App\Models\KotaKabupaten;
use App\Models\Kecamatan;
use App\Models\Kelurahan;

class WilayahSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(WilayahImportService $importService, ?callable $progress = null): void
    {
        // Hilangkan limit waktu eksekusi & naikkan batas memori
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '2048M');

        // Lokasi penyiapan folder dan nama file JSON keluaran
        $directoryPath = database_path('seeders/data');
        $filePath = "{$directoryPath}/wilayah_indonesia.json";

        if (!File::exists($directoryPath)) {
            File::makeDirectory($directoryPath, 0755, true);
        }

        $this->command?->info('1. Membaca sumber data wilayah yang tersimpan...');
        $provinces = $importService->load($progress);
        $exportData = [];

        foreach ($provinces as $prov) {
            // A. Simpan ke Database (Provinsi)
            $provinsiModel = WilayahLayanan::updateOrCreate(
                ['nama_provinsi' => $prov['name']],
                ['is_active' => true]
            );

            $this->command?->info("=== Provinsi: {$prov['name']} ===");
            if ($progress) {
                $progress('province_started', ['province' => $prov]);
            }

            // Struktur array JSON untuk Provinsi
            $provData = [
                'id' => $prov['id'],
                'name' => $prov['name'],
                'regencies' => []
            ];

            // B. Memproses Kota/Kabupaten dari sumber yang dipilih
            foreach ($prov['regencies'] ?? [] as $city) {
                    // Simpan ke Database (Kota)
                    $kotaModel = KotaKabupaten::updateOrCreate(
                        ['id_kota' => $city['id']],
                        [
                            'nama_kota' => $city['name'],
                            'id_provinsi' => $provinsiModel->id_provinsi
                        ]
                    );

                    $cityData = [
                        'id' => $city['id'],
                        'province_id' => $city['province_id'] ?? $prov['id'],
                        'name' => $city['name'],
                        'districts' => []
                    ];

                    // C. Memproses Kecamatan dari sumber yang dipilih
                    foreach ($city['districts'] ?? [] as $district) {
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

                            // D. Memproses Kelurahan/Desa dari sumber yang dipilih
                            $kelurahanBatch = [];
                            foreach ($district['villages'] ?? [] as $village) {
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

                            $cityData['districts'][] = $districtData;
                    }

                    $provData['regencies'][] = $cityData;
                    $this->command?->info("  -> Berhasil memproses & menyusun JSON untuk: {$city['name']}");
                    if ($progress) {
                        $progress('city_processed', ['city' => $city, 'districts' => count($city['districts'] ?? [])]);
                    }
            }

            $exportData[] = $provData;
        }

        $jsonContents = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        File::put($filePath, $jsonContents);

        $this->command?->info("==================================================");
        $this->command?->info("File JSON berhasil dibuat di: {$filePath}");
        $this->command?->info("Seeder dan ekspor JSON seluruh wilayah selesai!");
    }
}