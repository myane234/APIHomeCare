<?php

namespace Database\Seeders;

use App\Models\MasterUniversitas;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MasterUniversitasSeeder extends Seeder
{
    private const SOURCE_URL = 'http://universities.hipolabs.com/search?country=Indonesia';

    public function run(): void
    {
        $response = Http::acceptJson()->retry(3, 1000, throw: false)->timeout(60)->get(self::SOURCE_URL);
        if ($response->failed() || !is_array($response->json())) {
            throw new RuntimeException('Gagal mengambil data universitas dari Hipolabs.');
        }

        $count = 0;
        foreach ($response->json() as $university) {
            $name = trim((string) ($university['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            MasterUniversitas::updateOrCreate(
                ['nama_universitas' => $name],
                ['is_active' => true]
            );
            $count++;
        }

        $this->command?->info("{$count} universitas Indonesia berhasil disinkronkan.");
    }
}
