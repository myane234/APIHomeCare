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
        // Ambil semua data wilayah layanan
        $wilayahList = WilayahLayanan::all();

        if ($wilayahList->isEmpty()) {
            $this->command?->warn('Tenaga medis dilewati karena data wilayah layanan belum tersedia.');
            return;
        }

        $counter = 1;

        // Loop untuk setiap wilayah
        foreach ($wilayahList as $wilayah) {
            $idWilayah = $wilayah->id_wilayah_layanan ?? $wilayah->id_provinsi ?? $wilayah->id;

            // Buat minimal 5 nakes per wilayah
            for ($i = 1; $i <= 5; $i++) {
                $email = "nakes{$counter}@example.com";
                $nik = '32010190' . str_pad($counter, 8, '0', STR_PAD_LEFT);
                $noTelp = '0812345' . str_pad($counter, 5, '0', STR_PAD_LEFT);

                $user = Users::updateOrCreate(
                    ['email' => $email],
                    ['password' => Hash::make('password'), 'is_active' => true]
                );

                $pasien = Pasien::updateOrCreate(
                    ['id_user' => $user->id_user],
                    [
                        'nama_lengkap' => "Tenaga Medis {$counter}",
                        'no_hp' => $noTelp,
                        'nik' => $nik,
                        'jenis_kelamin' => $counter % 2 === 0 ? 'P' : 'L',
                        'alamat_utama' => "Jl. Kesehatan No. {$counter}, Jakarta",
                    ]
                );

                TenagaMedis::updateOrCreate(
                    ['id_user' => $user->id_user],
                    [
                        'id_user' => $user->id_user,
                        'id_pasien' => $pasien->id_pasien,
                        'id_wilayah_layanan' => $idWilayah,
                        'nik' => $nik,
                        'nama_lengkap' => "Dr. Tenaga Medis {$counter}",
                        'nama_panggilan' => "Nakes {$counter}",
                        'jenis_kelamin' => $counter % 2 === 0 ? 'P' : 'L',
                        'tempat_lahir' => 'Jakarta',
                        'tanggal_lahir' => '1990-01-01',
                        'agama' => 'Islam',
                        'no_telp' => $noTelp,
                        'alamat_lengkap' => "Jl. Kesehatan No. {$counter}, Jakarta",
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
                        'dokumen_tambahan' => [
                            ['nama' => 'Pelatihan First Aid', 'tahun' => '2021'],
                        ],
                        'status' => 'approved',
                        'is_data_complete' => true,
                    ]
                );

                $counter++;
            }
        }
    }
}