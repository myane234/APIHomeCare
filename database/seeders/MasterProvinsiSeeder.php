<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WilayahLayanan;
use App\Services\WilayahImportService;

class MasterProvinsiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(WilayahImportService $importService): void
    {
        foreach ($importService->load() as $province) {
            WilayahLayanan::updateOrCreate(
                ['nama_provinsi' => $province['name']],
                ['is_active' => true]
            );
        }
        $this->command->info('Berhasil sinkronisasi data provinsi.');
    }
}
