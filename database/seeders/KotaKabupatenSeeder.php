<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Services\WilayahImportService;
use App\Models\WilayahLayanan;
use App\Models\KotaKabupaten;

class KotaKabupatenSeeder extends Seeder
{
    public function run(WilayahImportService $importService): void
    {
        foreach ($importService->loadRegencies() as $prov) {
            $provinsiModel = WilayahLayanan::updateOrCreate(
                ['id_provinsi' => $prov['id']],
                [
                    'nama_provinsi' => $prov['name'],
                    'is_active' => true
                ]
            );

            $localProvinsiId = $provinsiModel->id_provinsi;

            foreach ($prov['regencies'] ?? [] as $city) {
                    KotaKabupaten::updateOrCreate(
                        ['id_kota' => $city['id']],
                        [
                            'nama_kota' => $city['name'],
                            'id_provinsi' => $localProvinsiId
                        ]
                    );
            }
            $this->command->info("Berhasil mengimpor kota untuk: {$prov['name']} (ID Lokal: {$localProvinsiId})");
        }
    }
}