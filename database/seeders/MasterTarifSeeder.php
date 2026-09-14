<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterTarifSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Update Layanan Prices
        $layanan_prices = [
            1 => 120000.00, 2 => 150000.00, 3 => 100000.00, 4 => 100000.00,
            5 => 200000.00, 6 => 180000.00, 7 => 220000.00, 8 => 85000.00,
            9 => 250000.00, 10 => 300000.00, 11 => 250000.00, 12 => 175000.00,
            13 => 190000.00, 14 => 210000.00
        ];
        foreach ($layanan_prices as $id => $harga) {
            DB::table('master_layanan')->where('id_layanan', $id)->update([
                'harga' => $harga,
                'updated_at' => now(),
            ]);
        }

        // 2. Kategori Tarif
        $kategori_tarif = [
            ['id_kategori_tarif' => 1, 'nama_kategori' => 'REGULER', 'biaya_tambahan' => 0.00, 'is_default' => 1, 'hari_berlaku' => null, 'jam_mulai' => null, 'jam_selesai' => null],
            ['id_kategori_tarif' => 2, 'nama_kategori' => 'CITO (EMERGENCY)', 'biaya_tambahan' => 10000.00, 'is_default' => 0, 'hari_berlaku' => null, 'jam_mulai' => null, 'jam_selesai' => null],
            ['id_kategori_tarif' => 3, 'nama_kategori' => 'MALAM HARI', 'biaya_tambahan' => 20000.00, 'is_default' => 0, 'hari_berlaku' => json_encode(['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu']), 'jam_mulai' => '22:00:00', 'jam_selesai' => '06:00:00'],
            ['id_kategori_tarif' => 4, 'nama_kategori' => 'AKHIR PEKAN (WEEKEND)', 'biaya_tambahan' => 15000.00, 'is_default' => 0, 'hari_berlaku' => json_encode(['sabtu', 'minggu']), 'jam_mulai' => '00:00:00', 'jam_selesai' => '23:59:00'],
            ['id_kategori_tarif' => 5, 'nama_kategori' => 'HARI LIBUR NASIONAL', 'biaya_tambahan' => 25000.00, 'is_default' => 0, 'hari_berlaku' => null, 'jam_mulai' => null, 'jam_selesai' => null],
            ['id_kategori_tarif' => 6, 'nama_kategori' => 'VIP / PRIORITAS', 'biaya_tambahan' => 50000.00, 'is_default' => 0, 'hari_berlaku' => null, 'jam_mulai' => null, 'jam_selesai' => null],
            ['id_kategori_tarif' => 7, 'nama_kategori' => 'LUAR KOTA', 'biaya_tambahan' => 35000.00, 'is_default' => 0, 'hari_berlaku' => null, 'jam_mulai' => null, 'jam_selesai' => null],
        ];
        foreach ($kategori_tarif as $kt) {
            DB::table('master_kategori_tarif')->updateOrInsert(
                ['id_kategori_tarif' => $kt['id_kategori_tarif']],
                array_merge($kt, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        // 3. Komponen Biaya
        $komponen_biaya = [
            ['id_komponen' => 1, 'nama_komponen' => 'PPN', 'tipe_komponen' => 'pajak', 'jenis_nilai' => 'persen', 'nilai' => 11.00, 'is_active' => 1],
            ['id_komponen' => 2, 'nama_komponen' => 'Admin Aplikasi', 'tipe_komponen' => 'admin_aplikasi', 'jenis_nilai' => 'nominal', 'nilai' => 4000.00, 'is_active' => 1],
            ['id_komponen' => 3, 'nama_komponen' => 'Penanganan APD & Steril', 'tipe_komponen' => 'lainnya', 'jenis_nilai' => 'nominal', 'nilai' => 15000.00, 'is_active' => 1],
            ['id_komponen' => 4, 'nama_komponen' => 'Platform Service Fee', 'tipe_komponen' => 'admin_aplikasi', 'jenis_nilai' => 'persen', 'nilai' => 10.00, 'is_active' => 1],
            ['id_komponen' => 5, 'nama_komponen' => 'Asuransi Pasien', 'tipe_komponen' => 'lainnya', 'jenis_nilai' => 'nominal', 'nilai' => 5000.00, 'is_active' => 1],
            ['id_komponen' => 6, 'nama_komponen' => 'Surcharge Weekend/Malam', 'tipe_komponen' => 'lainnya', 'jenis_nilai' => 'persen', 'nilai' => 15.00, 'is_active' => 1],
            ['id_komponen' => 7, 'nama_komponen' => 'Diskon Promo Member', 'tipe_komponen' => 'lainnya', 'jenis_nilai' => 'persen', 'nilai' => 5.00, 'is_active' => 1],
        ];
        foreach ($komponen_biaya as $kb) {
            DB::table('master_komponen_biaya')->updateOrInsert(
                ['id_komponen' => $kb['id_komponen']],
                array_merge($kb, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        // 4. Master Tarif Templates
        $tarif_templates = [
            [
                'id_master_tarif' => 1, 'nama_template' => 'Tarif Ibu & Anak Reguler', 'id_kategori_tarif' => 1,
                'id_layanan' => 1, 'fee_nakes_tipe' => 'persen', 'fee_nakes_nilai' => 80.00,
                'fee_nakes_nominal' => 96000.00, 'fee_platform_nominal' => 24000.00, 'is_transport' => 1, 'is_active' => 1,
                'layanan_ids' => [1, 2, 3, 4], 'komponen_ids' => [1, 2]
            ],
            [
                'id_master_tarif' => 2, 'nama_template' => 'Tarif Perawatan Luka Reguler', 'id_kategori_tarif' => 1,
                'id_layanan' => 5, 'fee_nakes_tipe' => 'persen', 'fee_nakes_nilai' => 80.00,
                'fee_nakes_nominal' => 160000.00, 'fee_platform_nominal' => 40000.00, 'is_transport' => 1, 'is_active' => 1,
                'layanan_ids' => [5, 6, 7], 'komponen_ids' => [1, 2, 3]
            ],
            [
                'id_master_tarif' => 3, 'nama_template' => 'Tarif Medical Checkup Reguler', 'id_kategori_tarif' => 1,
                'id_layanan' => 8, 'fee_nakes_tipe' => 'persen', 'fee_nakes_nilai' => 75.00,
                'fee_nakes_nominal' => 63750.00, 'fee_platform_nominal' => 21250.00, 'is_transport' => 1, 'is_active' => 1,
                'layanan_ids' => [8, 9], 'komponen_ids' => [1, 2]
            ],
            [
                'id_master_tarif' => 4, 'nama_template' => 'Tarif Fisioterapi Reguler', 'id_kategori_tarif' => 1,
                'id_layanan' => 10, 'fee_nakes_tipe' => 'persen', 'fee_nakes_nilai' => 80.00,
                'fee_nakes_nominal' => 240000.00, 'fee_platform_nominal' => 60000.00, 'is_transport' => 1, 'is_active' => 1,
                'layanan_ids' => [10, 11], 'komponen_ids' => [1, 2]
            ],
            [
                'id_master_tarif' => 5, 'nama_template' => 'Tarif Alat Medis Reguler', 'id_kategori_tarif' => 1,
                'id_layanan' => 12, 'fee_nakes_tipe' => 'persen', 'fee_nakes_nilai' => 80.00,
                'fee_nakes_nominal' => 140000.00, 'fee_platform_nominal' => 35000.00, 'is_transport' => 1, 'is_active' => 1,
                'layanan_ids' => [12, 13, 14], 'komponen_ids' => [1, 2, 3]
            ],
            [
                'id_master_tarif' => 6, 'nama_template' => 'Tarif CITO (Emergency) All Services', 'id_kategori_tarif' => 2,
                'id_layanan' => 5, 'fee_nakes_tipe' => 'persen', 'fee_nakes_nilai' => 85.00,
                'fee_nakes_nominal' => 170000.00, 'fee_platform_nominal' => 30000.00, 'is_transport' => 1, 'is_active' => 1,
                'layanan_ids' => [5, 6, 7, 12, 13, 14], 'komponen_ids' => [1, 2, 3]
            ],
            [
                'id_master_tarif' => 7, 'nama_template' => 'Tarif Weekend & Malam Hari', 'id_kategori_tarif' => 3,
                'id_layanan' => 1, 'fee_nakes_tipe' => 'persen', 'fee_nakes_nilai' => 80.00,
                'fee_nakes_nominal' => 16000.00, 'fee_platform_nominal' => 4000.00, 'is_transport' => 1, 'is_active' => 1,
                'layanan_ids' => [1, 2, 3, 4, 5, 8, 10], 'komponen_ids' => [1, 2, 6]
            ],
            [
                'id_master_tarif' => 8, 'nama_template' => 'Tarif VIP Prioritas Surabaya', 'id_kategori_tarif' => 6,
                'id_layanan' => 9, 'fee_nakes_tipe' => 'persen', 'fee_nakes_nilai' => 85.00,
                'fee_nakes_nominal' => 212500.00, 'fee_platform_nominal' => 37500.00, 'is_transport' => 1, 'is_active' => 1,
                'layanan_ids' => [8, 9, 10, 11], 'komponen_ids' => [1, 2, 4, 5]
            ],
            [
                'id_master_tarif' => 9, 'nama_template' => 'Tarif Reguler Bandung', 'id_kategori_tarif' => 1,
                'id_layanan' => 2, 'fee_nakes_tipe' => 'persen', 'fee_nakes_nilai' => 80.00,
                'fee_nakes_nominal' => 120000.00, 'fee_platform_nominal' => 30000.00, 'is_transport' => 1, 'is_active' => 1,
                'layanan_ids' => [1, 2, 3, 5, 6, 12], 'komponen_ids' => [1, 2]
            ],
            [
                'id_master_tarif' => 10, 'nama_template' => 'Tarif Luar Kota Jabodetabek', 'id_kategori_tarif' => 7,
                'id_layanan' => 10, 'fee_nakes_tipe' => 'persen', 'fee_nakes_nilai' => 80.00,
                'fee_nakes_nominal' => 240000.00, 'fee_platform_nominal' => 60000.00, 'is_transport' => 1, 'is_active' => 1,
                'layanan_ids' => [5, 6, 10, 11, 14], 'komponen_ids' => [1, 2, 3, 5]
            ],
        ];

        foreach ($tarif_templates as $t) {
            $layanan_ids = $t['layanan_ids'];
            $komponen_ids = $t['komponen_ids'];
            unset($t['layanan_ids'], $t['komponen_ids']);

            DB::table('master_tarif')->updateOrInsert(
                ['id_master_tarif' => $t['id_master_tarif']],
                array_merge($t, ['created_at' => now(), 'updated_at' => now()])
            );

            DB::table('master_tarif_layanan')->where('id_master_tarif', $t['id_master_tarif'])->delete();
            DB::table('master_tarif_komponen')->where('id_master_tarif', $t['id_master_tarif'])->delete();

            foreach ($layanan_ids as $lid) {
                DB::table('master_tarif_layanan')->insert([
                    'id_master_tarif' => $t['id_master_tarif'],
                    'id_layanan' => $lid,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            foreach ($komponen_ids as $kid) {
                DB::table('master_tarif_komponen')->insert([
                    'id_master_tarif' => $t['id_master_tarif'],
                    'id_komponen' => $kid,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
