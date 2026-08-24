<?php
namespace Database\Seeders;

use App\Models\WilayahLayanan;
use Illuminate\Database\Seeder;
use App\Services\WilayahImportService;

class ProvinsiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(WilayahImportService $importService): void
    {
        foreach ($importService->loadProvinces() as $item) {
            WilayahLayanan::updateOrCreate(
                ['id_provinsi' => $item['id']],
                [
                    'nama_provinsi' => $item['name'],
                    'is_active' => true
                ]
            );
        }

        $this->command?->info('Berhasil mengimpor data provinsi.');
    }
}