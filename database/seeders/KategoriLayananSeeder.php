<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\KategoriLayanan;
use Illuminate\Support\Facades\Storage;

class KategoriLayananSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Pastikan direktori storage kategori ada
        if (!Storage::disk('public')->exists('kategori')) {
            Storage::disk('public')->makeDirectory('kategori');
        }

        // Copy default image sebagai fallback
        $defaultImagePath = base_path('../FEHomeCare/public/images/logo/logo.png');
        if (file_exists($defaultImagePath)) {
            Storage::disk('public')->put('kategori/default.jpg', file_get_contents($defaultImagePath));
        }

        // Mapping kategori dengan file gambar promo yang sesuai
        $kategoris = [
            [
                'nama_kategori' => 'Ibu dan Anak',
                'source_image' => 'newborn.png',
            ],
            [
                'nama_kategori' => 'Perawatan Luka',
                'source_image' => 'luka.png',
            ],
            [
                'nama_kategori' => 'Medical Checkup',
                'source_image' => 'mcu.png',
            ],
            [
                'nama_kategori' => 'Fisioterapi',
                'source_image' => 'fisio.png',
            ],
            [
                'nama_kategori' => 'Pemasangan dan Penggantian Alat Medis',
                'source_image' => 'default.jpg', // Menggunakan fallback jika tidak ada gambar khusus
            ],
        ];

        foreach ($kategoris as $data) {
            $sourcePath = base_path('../FEHomeCare/public/images/promo/' . $data['source_image']);
            $gambarPath = 'kategori/default.jpg'; // fallback default

            if (file_exists($sourcePath)) {
                $filename = basename($sourcePath);
                $destPath = 'kategori/' . $filename;
                
                // Copy file dari FEHomeCare ke storage/app/public/kategori
                Storage::disk('public')->put($destPath, file_get_contents($sourcePath));
                $gambarPath = $destPath;
            }

            KategoriLayanan::updateOrCreate(
                ['nama_kategori' => $data['nama_kategori']],
                ['photo_kategori' => $gambarPath]
            );
        }
    }
}