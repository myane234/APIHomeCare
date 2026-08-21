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
        foreach ($importService->load() as $item) {
            WilayahLayanan::updateOrCreate(
                ['nama_provinsi' => $item['name']],
                ['is_active' => true]
            );
        }
    }
}