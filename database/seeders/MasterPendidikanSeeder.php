<?php

namespace Database\Seeders;

use App\Models\MasterPendidikan;
use Illuminate\Database\Seeder;

class MasterPendidikanSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['SMA/SMK', 'D3', 'D4', 'S1', 'Profesi', 'S2', 'S3'] as $nama) {
            MasterPendidikan::firstOrCreate(['nama_pendidikan' => $nama]);
        }

        $this->command?->info('Master pendidikan berhasil diseed.');
    }
}
