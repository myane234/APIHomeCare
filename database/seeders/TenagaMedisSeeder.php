<?php

namespace Database\Seeders;

use App\Models\TenagaMedis;
use App\Models\Users;
use App\Models\Pasien;
use App\Models\WilayahLayanan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenagaMedisSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $wilayah = WilayahLayanan::query()->first();
        if (!$wilayah) {
            $this->command?->warn('Tenaga medis dilewati karena data provinsi belum tersedia.');
            return;
        }

        for ($i = 1; $i <= 5; $i++) {
            $user = Users::updateOrCreate(
                ['email' => "nakes{$i}@example.com"],
                ['password' => Hash::make('password'), 'is_active' => true]
            );
            $pasien = Pasien::updateOrCreate(
                ['id_user' => $user->id_user],
                [
                    'nama_lengkap' => "Tenaga Medis {$i}",
                    'no_hp' => '0812345678' . $i,
                    'nik' => '320101900000000' . $i,
                    'jenis_kelamin' => $i % 2 === 0 ? 'P' : 'L',
                    'alamat_utama' => "Jl. Kesehatan No. {$i}, Jakarta",
                ]
            );

            TenagaMedis::updateOrCreate(
                ['id_user' => $user->id_user],
                [
                'id_user' => $user->id_user,
                'id_pasien' => $pasien->id_pasien,
                'id_wilayah_layanan' => $wilayah->id_provinsi,
                'nama_lengkap' => "Dr. Tenaga Medis {$i}",
                'nama_panggilan' => "Nakes {$i}",
                'nik' => '320101900000000' . $i,
                'jenis_kelamin' => $i % 2 === 0 ? 'P' : 'L',
                'tempat_lahir' => 'Jakarta',
                'tanggal_lahir' => '1990-01-01',
                'agama' => 'Islam',
                'alamat_lengkap' => "Jl. Kesehatan No. {$i}, Jakarta",
                'no_telp' => '0812345678' . $i,
                'jenis_tenaga_medis' => 'Dokter Umum',
                'universitas' => 'Universitas Indonesia',
                'program_studi' => 'Kedokteran',
                'tahun_lulus' => 2015,
                'no_str' => 'STR-' . rand(10000, 99999),
                'no_sip' => 'SIP-' . rand(10000, 99999),
                'file_ktp' => 'seeders/placeholder/ktp.jpg',
                'ijazah' => 'seeders/placeholder/ijazah.jpg',
                'file_skck' => 'seeders/placeholder/skck.jpg',
                'file_cv' => 'seeders/placeholder/cv.pdf',
                'file_str' => 'seeders/placeholder/str.jpg',
                'file_sip' => 'seeders/placeholder/sip.jpg',
                'tempat_kerja' => 'Smart Home Care',
                'lama_bekerja' => '5 tahun',
                'status' => 'approved',
                'pengalaman_kerja' => [
                    ['instansi' => 'RS Sehat Selalu', 'tahun' => '2015-2020'],
                ],
                'dokumen_tambahan' => [
                    ['nama' => 'Pelatihan First Aid', 'tahun' => '2021'],
                ],
                'is_data_complete' => true,
                ]
            );
        }
    }
}