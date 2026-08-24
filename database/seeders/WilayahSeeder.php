<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use App\Services\WilayahImportService;
use App\Models\WilayahLayanan;
use App\Models\KotaKabupaten;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use Illuminate\Support\Facades\Http;

class WilayahSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(WilayahImportService $importService, ?callable $progress = null): void
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '2048M');

        $directoryPath = database_path('seeders/data');
        $filePath = "{$directoryPath}/wilayah_indonesia.json";

        if (!File::exists($directoryPath)) {
            File::makeDirectory($directoryPath, 0755, true);
        }

        $this->command?->info('1. Membaca data Provinsi...');
        $provinces = $importService->loadProvinces();
        $exportData = [];

        if ($progress) {
            $progress('source_loaded', ['total_provinces' => count($provinces)]);
        }

        foreach ($provinces as $prov) {
            // Simpan Provinsi ke DB menggunakan id_provinsi sesuai API
            $provinsiModel = WilayahLayanan::updateOrCreate(
                ['id_provinsi' => $prov['id']],
                [
                    'nama_provinsi' => $prov['name'],
                    'is_active' => true
                ]
            );

            $this->command?->info("=== Provinsi: {$prov['name']} ===");
            if ($progress) {
                $progress('province_started', ['province' => $prov]);
            }

            $provData = [
                'id' => $prov['id'],
                'name' => $prov['name'],
                'regencies' => []
            ];

            // Request berkala Kabupaten/Kota
            $regencies = [];
            $source = $importService->getSource();
            if ($source && $source->source_type === 'api') {
               $url = str_replace('{id_provinsi}', $prov['id'], $source->regencies_url);
               $regencies = Http::acceptJson()->retry(3,1000)->get($url)->json() ?? [];
            } else {
               // Fallback untuk file csv/json
               $allRegencies = $importService->loadRegencies();
               $matchedProv = array_filter($allRegencies, fn($p) => $p['id'] == $prov['id']);
               $matchedProv = reset($matchedProv);
               $regencies = $matchedProv['regencies'] ?? [];
            }

            foreach ($regencies as $city) {
                $kotaModel = KotaKabupaten::updateOrCreate(
                    ['id_kota' => $city['id']],
                    [
                        'nama_kota' => $city['name'],
                        'id_provinsi' => $prov['id']
                    ]
                );

                $cityData = [
                    'id' => $city['id'],
                    'province_id' => $prov['id'],
                    'name' => $city['name'],
                    'districts' => []
                ];

                // Request berkala Kecamatan
                $districts = [];
                if ($source && $source->source_type === 'api') {
                    $url = str_replace('{id_kota}', $city['id'], $source->districts_url);
                    $districts = Http::acceptJson()->retry(3,1000)->get($url)->json() ?? [];
                } else {
                    $districts = $city['districts'] ?? [];
                }

                foreach ($districts as $district) {
                    Kecamatan::updateOrCreate(
                        ['id_kecamatan' => $district['id']],
                        [
                            'regency_id' => $city['id'],
                            'nama_kecamatan' => $district['name'],
                        ]
                    );

                    $districtData = [
                        'id' => $district['id'],
                        'regency_id' => $city['id'],
                        'name' => $district['name'],
                        'villages' => []
                    ];

                    // Request berkala Kelurahan
                    $villages = [];
                    if ($source && $source->source_type === 'api') {
                        $url = str_replace('{id_kecamatan}', $district['id'], $source->villages_url);
                        $villages = Http::acceptJson()->retry(3,1000)->get($url)->json() ?? [];
                    } else {
                        $villages = $district['villages'] ?? [];
                    }

                    $kelurahanBatch = [];
                    foreach ($villages as $village) {
                        $kelurahanBatch[] = [
                            'id_kelurahan' => $village['id'],
                            'id_kecamatan' => $district['id'],
                            'nama_kelurahan' => $village['name'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        
                        $districtData['villages'][] = [
                            'id' => $village['id'],
                            'district_id' => $district['id'],
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
                $this->command?->info("  -> Selesai: {$city['name']}");
                
                if ($progress) {
                    $progress('city_processed', ['city' => $cityData, 'districts' => count($cityData['districts'] ?? [])]);
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