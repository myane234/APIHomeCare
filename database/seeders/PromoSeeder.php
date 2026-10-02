<?php

namespace Database\Seeders;

use App\Models\Promo;
use App\Models\Layanan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class PromoSeeder extends Seeder
{
    public function run(): void
    {
        if (!Storage::disk('public')->exists('promo_images')) {
            Storage::disk('public')->makeDirectory('promo_images');
        }


        $defaultImagePath = base_path('../FEHomeCare/public/images/logo/logo.png'); 
        $defaultImageDest = 'promo_images/default.jpg';
        if (file_exists($defaultImagePath) && !Storage::disk('public')->exists($defaultImageDest)) {
            Storage::disk('public')->put($defaultImageDest, file_get_contents($defaultImagePath));
        }


        $layanans = Layanan::inRandomOrder()->take(4)->get();

        if ($layanans->isEmpty()) {
            return;
        }


        $templatePromos = [
            ['diskon' => 10.00, 'img' => 'mcu.png', 'desc' => 'Dapatkan potongan khusus untuk layanan kesehatan pilihan Anda.'],
            ['diskon' => 15.00, 'img' => 'newborn.png', 'desc' => 'Nikmati penawaran spesial demi kenyamanan dan kesehatan optimal.'],
            ['diskon' => 20.00, 'img' => 'fisio.png', 'desc' => 'Terapi intensif langsung di rumah Anda dengan harga lebih hemat.'],
            ['diskon' => 25.00, 'img' => 'luka.png', 'desc' => 'Perawatan profesional dan steril dengan potongan harga menarik.'],
        ];

        foreach ($layanans as $index => $layanan) {

            $template = $templatePromos[$index % count($templatePromos)];

            $sourcePath = base_path('../FEHomeCare/public/images/promo/' . $template['img']);
            $gambarPath = 'promo_images/default.jpg'; 

            if (file_exists($sourcePath)) {
                $filename = basename($sourcePath);
        
                $destPath = 'promo_images/' . $layanan->id_layanan . '_' . $filename;
                Storage::disk('public')->put($destPath, file_get_contents($sourcePath));
                $gambarPath = $destPath;
            }


            Promo::updateOrCreate(
                ['id_layanan' => $layanan->id_layanan],
                [
                    'deskripsi' => 'Promo Spesial ' . $layanan->nama_layanan . '! ' . $template['desc'],
                    'tipe_diskon' => 'persen',
                    'nilai_diskon' => $template['diskon'],
                    'tanggal_mulai' => now()->subDays(5)->format('Y-m-d'),
                    'tanggal_berakhir' => now()->addMonth()->format('Y-m-d'),
                    'status_promo' => 'Aktif',
                    'gambar_promo' => $gambarPath,
                ]
            );
        }
    }
}