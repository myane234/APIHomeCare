<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\KategoriLayanan;
use Illuminate\Support\Str;

class KategoriLayananSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $kategoris = [
            'Ibu dan Anak',
            'Perawatan Luka',
            'Medical Checkup',
            'Fisioterapi',
            'Pemasangan dan Penggantian Alat Medis',
        ];

        foreach ($kategoris as $kategori) {
            $slug = Str::slug($kategori);
            
            $imageUrl = "https://picsum.photos/seed/{$slug}/600/400";

            KategoriLayanan::updateOrCreate(
                ['nama_kategori' => $kategori],
                ['photo_kategori' => $imageUrl]
            );
        }
    }
}