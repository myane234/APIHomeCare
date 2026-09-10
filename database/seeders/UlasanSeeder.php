<?php

namespace Database\Seeders;

use App\Models\Ulasan;
use App\Models\Layanan;
use Illuminate\Database\Seeder;

class UlasanSeeder extends Seeder
{
    public function run(): void
    {

        $layananList = Layanan::all();
        $layananLansia = $layananList->firstWhere('nama_layanan', 'like', '%Lansia%')?->id_master_layanan ?? $layananList->first()?->id_master_layanan;
        $layananFisio = $layananList->firstWhere('nama_layanan', 'like', '%Fisio%')?->id_master_layanan ?? $layananList->first()?->id_master_layanan;
        $layananBayi = $layananList->firstWhere('nama_layanan', 'like', '%Bayi%')?->id_master_layanan ?? $layananList->first()?->id_master_layanan;

        $ulasanData = [
            [
                'nama_pengulas' => 'Siti Rahmawati',
                'profesi_peran' => 'Keluarga Pasien',
                'rating' => 5,
                'komentar' => 'Pelayanan perawat sangat telaten dan ramah. Ibu saya merasa sangat nyaman dirawat di rumah.',
                'layanan_id' => $layananLansia,
                'is_published' => true,
                'urutan' => 1,
            ],
            [
                'nama_pengulas' => 'Budi Santoso',
                'profesi_peran' => 'Pasien Fisioterapi',
                'rating' => 5,
                'komentar' => 'Sangat membantu pemulihan pasca operasi. Terapis sabar dan memberikan panduan latihan harian yang terstruktur.',
                'layanan_id' => $layananFisio,
                'is_published' => true,
                'urutan' => 2,
            ],
            [
                'nama_pengulas' => 'Dewi Lestari',
                'profesi_peran' => 'Ibu Menyusui',
                'rating' => 5,
                'komentar' => 'Pijat laktasi dan perawatan bayi dari perawat Home Care sangat memuaskan dan menenangkan.',
                'layanan_id' => $layananBayi,
                'is_published' => true,
                'urutan' => 3,
            ],
            [
                'nama_pengulas' => 'Hendra Wijaya',
                'profesi_peran' => 'Pasien Rawat Medis',
                'rating' => 4,
                'komentar' => 'Respon cepat dan perawat sangat profesional. Penggantian perban luka berjalan lancar tanpa rasa sakit berlebih.',
                'layanan_id' => $layananList->first()?->id_master_layanan,
                'is_published' => true,
                'urutan' => 4,
            ],
            [
                'nama_pengulas' => 'Ratna Sari',
                'profesi_peran' => 'Keluarga Pasien',
                'rating' => 5,
                'komentar' => 'Layanan pemantauan kesehatan lansia 24 jam sangat membantu keluarga kami yang bekerja.',
                'layanan_id' => $layananLansia,
                'is_published' => true,
                'urutan' => 5,
            ],
            [
                'nama_pengulas' => 'Ahmad Fauzi',
                'profesi_peran' => 'Pasien Pasca Operasi',
                'rating' => 5,
                'komentar' => 'Pelayanan tepat waktu dan ramah. Sangat direkomendasikan untuk keluarga yang membutuhkan perawatan medis profesional di rumah.',
                'layanan_id' => $layananFisio,
                'is_published' => true,
                'urutan' => 6,
            ],
            [
                'nama_pengulas' => 'Maya Putri',
                'profesi_peran' => 'Ibu Bayi Kembar',
                'rating' => 5,
                'komentar' => 'Sangat terbantu dengan perawat bayi newborn. Tidur saya jadi lebih teratur dan bayi terlatih tumbuh kembangnya dengan baik.',
                'layanan_id' => $layananBayi,
                'is_published' => true,
                'urutan' => 7,
            ],
            [
                'nama_pengulas' => 'Eko Prasetyo',
                'profesi_peran' => 'Pasien Stroke',
                'rating' => 4,
                'komentar' => 'Pendampingan fisioterapi rutin membuat progres gerakan tangan dan kaki saya terasa signifikan dalam sebulan.',
                'layanan_id' => $layananFisio,
                'is_published' => true,
                'urutan' => 8,
            ],
        ];

        foreach ($ulasanData as $data) {
            Ulasan::create($data);
        }
    }
}
