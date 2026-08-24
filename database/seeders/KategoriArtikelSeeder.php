<?php

namespace Database\Seeders;

use App\Models\KategoriArtikel;
use Illuminate\Database\Seeder;

class KategoriArtikelSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Tips Kesehatan', 'Kegiatan'] as $nama) {
            KategoriArtikel::firstOrCreate(['nama_kategori' => $nama]);
        }

        $this->command?->info('Kategori artikel berhasil diseed.');
    }
}
