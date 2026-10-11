<?php

namespace Database\Seeders;

use App\Models\BhpItem;
use App\Models\MasterLayanan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterBhpSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'nama_bhp'     => 'Kain Kassa Steril',
                'harga_modal'  => 5000,
                'tipe_margin'  => 'nominal',
                'nilai_margin' => 5000, // jual: 10000
                'is_active'    => true,
            ],
            [
                'nama_bhp'     => 'Kassa Gulung / Verband',
                'harga_modal'  => 4000,
                'tipe_margin'  => 'nominal',
                'nilai_margin' => 4000, // jual: 8000
                'is_active'    => true,
            ],
            [
                'nama_bhp'     => 'Plester Medis (Hypafix / Micropore)',
                'harga_modal'  => 8000,
                'tipe_margin'  => 'nominal',
                'nilai_margin' => 4000, // jual: 12000
                'is_active'    => true,
            ],
            [
                'nama_bhp'     => 'Sarung Tangan Steril (Handscoon)',
                'harga_modal'  => 6000,
                'tipe_margin'  => 'nominal',
                'nilai_margin' => 6000, // jual: 12000
                'is_active'    => true,
            ],
            [
                'nama_bhp'     => 'Sarung Tangan Medis Non-Steril',
                'harga_modal'  => 2500,
                'tipe_margin'  => 'nominal',
                'nilai_margin' => 2500, // jual: 5000
                'is_active'    => true,
            ],
            [
                'nama_bhp'     => 'Alkohol Swab',
                'harga_modal'  => 2000,
                'tipe_margin'  => 'nominal',
                'nilai_margin' => 2000, // jual: 4000
                'is_active'    => true,
            ],
            [
                'nama_bhp'     => 'Ciran Infus NaCl 0.9% 500ml',
                'harga_modal'  => 15000,
                'tipe_margin'  => 'nominal',
                'nilai_margin' => 10000, // jual: 25000
                'is_active'    => true,
            ],
            [
                'nama_bhp'     => 'Povidone Iodine 10% (Antiseptik)',
                'harga_modal'  => 12000,
                'tipe_margin'  => 'nominal',
                'nilai_margin' => 6000, // jual: 18000
                'is_active'    => true,
            ],
            [
                'nama_bhp'     => 'Underpad Medis Steril',
                'harga_modal'  => 6000,
                'tipe_margin'  => 'nominal',
                'nilai_margin' => 6000, // jual: 12000
                'is_active'    => true,
            ],
            [
                'nama_bhp'     => 'Spuit 3cc Steril',
                'harga_modal'  => 2500,
                'tipe_margin'  => 'nominal',
                'nilai_margin' => 2500, // jual: 5000
                'is_active'    => true,
            ],
            [
                'nama_bhp'     => 'Spuit 5cc Steril',
                'harga_modal'  => 3000,
                'tipe_margin'  => 'nominal',
                'nilai_margin' => 3000, // jual: 6000
                'is_active'    => true,
            ],
            [
                'nama_bhp'     => 'Spuit 10cc Steril',
                'harga_modal'  => 4000,
                'tipe_margin'  => 'nominal',
                'nilai_margin' => 4000, // jual: 8000
                'is_active'    => true,
            ],
            [
                'nama_bhp'     => 'Infus Set Dewasa',
                'harga_modal'  => 12000,
                'tipe_margin'  => 'nominal',
                'nilai_margin' => 12000, // jual: 24000
                'is_active'    => true,
            ],
            [
                'nama_bhp'     => 'Jarum Infus / Abocath (IV Catheter)',
                'harga_modal'  => 10000,
                'tipe_margin'  => 'nominal',
                'nilai_margin' => 10000, // jual: 20000
                'is_active'    => true,
            ],
            [
                'nama_bhp'     => 'Selang Kateter (Foley Catheter 2-Way)',
                'harga_modal'  => 25000,
                'tipe_margin'  => 'nominal',
                'nilai_margin' => 20000, // jual: 45000
                'is_active'    => true,
            ],
            [
                'nama_bhp'     => 'Kantong Urine (Urine Bag 2000ml)',
                'harga_modal'  => 10000,
                'tipe_margin'  => 'nominal',
                'nilai_margin' => 10000, // jual: 20000
                'is_active'    => true,
            ],
            [
                'nama_bhp'     => 'Jelly Pelumas Medis (K-Y Jelly)',
                'harga_modal'  => 5000,
                'tipe_margin'  => 'nominal',
                'nilai_margin' => 5000, // jual: 10000
                'is_active'    => true,
            ],
            [
                'nama_bhp'     => 'Selang NGT (Nasogastric Tube)',
                'harga_modal'  => 20000,
                'tipe_margin'  => 'nominal',
                'nilai_margin' => 15000, // jual: 35000
                'is_active'    => true,
            ],
            [
                'nama_bhp'     => 'Strip Tes Gula Darah & Lancet',
                'harga_modal'  => 8000,
                'tipe_margin'  => 'nominal',
                'nilai_margin' => 8000, // jual: 16000
                'is_active'    => true,
            ],
            [
                'nama_bhp'     => 'Strip Tes Kolesterol',
                'harga_modal'  => 20000,
                'tipe_margin'  => 'nominal',
                'nilai_margin' => 15000, // jual: 35000
                'is_active'    => true,
            ],
            [
                'nama_bhp'     => 'Strip Tes Asam Urat',
                'harga_modal'  => 12000,
                'tipe_margin'  => 'nominal',
                'nilai_margin' => 9000, // jual: 21000
                'is_active'    => true,
            ],
            [
                'nama_bhp'     => 'Salep Luka Modern / Hydrogel Dressing',
                'harga_modal'  => 35000,
                'tipe_margin'  => 'nominal',
                'nilai_margin' => 20000, // jual: 55000
                'is_active'    => true,
            ],
            [
                'nama_bhp'     => 'Minyak Pijat Laktasi Alami',
                'harga_modal'  => 20000,
                'tipe_margin'  => 'nominal',
                'nilai_margin' => 10000, // jual: 30000
                'is_active'    => true,
            ],
            [
                'nama_bhp'     => 'Minyak Pijat Bayi (Baby Oil)',
                'harga_modal'  => 15000,
                'tipe_margin'  => 'nominal',
                'nilai_margin' => 10000, // jual: 25000
                'is_active'    => true,
            ],
            [
                'nama_bhp'     => 'Kasa Alkohol Khusus Tali Pusat',
                'harga_modal'  => 5000,
                'tipe_margin'  => 'nominal',
                'nilai_margin' => 5000, // jual: 10000
                'is_active'    => true,
            ],
        ];

        $bhpMap = [];
        foreach ($items as $item) {
            $created = BhpItem::updateOrCreate(
                ['nama_bhp' => $item['nama_bhp']],
                $item
            );
            $bhpMap[$item['nama_bhp']] = $created->id_bhp;
        }

        $this->command?->info('Master BHP seeded: ' . count($bhpMap) . ' items.');

        // Mapping layanan ke BHP
        $mappingConfig = [
            'Pijat Laktasi' => [
                ['nama' => 'Minyak Pijat Laktasi Alami', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Underpad Medis Steril', 'qty' => 1, 'mandatory' => false],
            ],
            'Perawatan Bayi Baru Lahir (Newborn Care)' => [
                ['nama' => 'Minyak Pijat Bayi (Baby Oil)', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Kain Kassa Steril', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Underpad Medis Steril', 'qty' => 1, 'mandatory' => false],
            ],
            'Senam Hamil Privat' => [
                ['nama' => 'Underpad Medis Steril', 'qty' => 1, 'mandatory' => false],
            ],
            'Perawatan Tali Pusat' => [
                ['nama' => 'Kasa Alkohol Khusus Tali Pusat', 'qty' => 2, 'mandatory' => true],
                ['nama' => 'Kain Kassa Steril', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Sarung Tangan Steril (Handscoon)', 'qty' => 1, 'mandatory' => true],
            ],
            'Perawatan Luka Diabetes' => [
                ['nama' => 'Kain Kassa Steril', 'qty' => 2, 'mandatory' => true],
                ['nama' => 'Salep Luka Modern / Hydrogel Dressing', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Ciran Infus NaCl 0.9% 500ml', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Plester Medis (Hypafix / Micropore)', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Sarung Tangan Steril (Handscoon)', 'qty' => 2, 'mandatory' => true],
            ],
            'Perawatan Luka Pasca Operasi' => [
                ['nama' => 'Kain Kassa Steril', 'qty' => 2, 'mandatory' => true],
                ['nama' => 'Povidone Iodine 10% (Antiseptik)', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Ciran Infus NaCl 0.9% 500ml', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Plester Medis (Hypafix / Micropore)', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Sarung Tangan Steril (Handscoon)', 'qty' => 2, 'mandatory' => true],
            ],
            'Perawatan Luka Bakar' => [
                ['nama' => 'Salep Luka Modern / Hydrogel Dressing', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Kain Kassa Steril', 'qty' => 2, 'mandatory' => true],
                ['nama' => 'Ciran Infus NaCl 0.9% 500ml', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Plester Medis (Hypafix / Micropore)', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Sarung Tangan Steril (Handscoon)', 'qty' => 2, 'mandatory' => true],
            ],
            'Pemeriksaan Gula Darah & Kolesterol' => [
                ['nama' => 'Strip Tes Gula Darah & Lancet', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Strip Tes Kolesterol', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Strip Tes Asam Urat', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Alkohol Swab', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Sarung Tangan Medis Non-Steril', 'qty' => 1, 'mandatory' => true],
            ],
            'Home Care MCU Package' => [
                ['nama' => 'Strip Tes Gula Darah & Lancet', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Strip Tes Kolesterol', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Strip Tes Asam Urat', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Alkohol Swab', 'qty' => 2, 'mandatory' => true],
                ['nama' => 'Sarung Tangan Medis Non-Steril', 'qty' => 1, 'mandatory' => true],
            ],
            'Fisioterapi Stroke Homecare' => [
                ['nama' => 'Underpad Medis Steril', 'qty' => 1, 'mandatory' => false],
            ],
            'Fisioterapi Cedera Olahraga' => [
                ['nama' => 'Underpad Medis Steril', 'qty' => 1, 'mandatory' => false],
                ['nama' => 'Kassa Gulung / Verband', 'qty' => 1, 'mandatory' => false],
            ],
            'Pemasangan Kateter Urine' => [
                ['nama' => 'Selang Kateter (Foley Catheter 2-Way)', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Kantong Urine (Urine Bag 2000ml)', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Jelly Pelumas Medis (K-Y Jelly)', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Spuit 10cc Steril', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Ciran Infus NaCl 0.9% 500ml', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Sarung Tangan Steril (Handscoon)', 'qty' => 2, 'mandatory' => true],
                ['nama' => 'Underpad Medis Steril', 'qty' => 1, 'mandatory' => true],
            ],
            'Pemasangan NGT (Selang Makan)' => [
                ['nama' => 'Selang NGT (Nasogastric Tube)', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Spuit 10cc Steril', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Jelly Pelumas Medis (K-Y Jelly)', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Plester Medis (Hypafix / Micropore)', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Sarung Tangan Steril (Handscoon)', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Underpad Medis Steril', 'qty' => 1, 'mandatory' => true],
            ],
            'Pemasangan Infus & Terapi Cairan' => [
                ['nama' => 'Infus Set Dewasa', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Jarum Infus / Abocath (IV Catheter)', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Ciran Infus NaCl 0.9% 500ml', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Plester Medis (Hypafix / Micropore)', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Alkohol Swab', 'qty' => 1, 'mandatory' => true],
                ['nama' => 'Sarung Tangan Steril (Handscoon)', 'qty' => 2, 'mandatory' => true],
                ['nama' => 'Underpad Medis Steril', 'qty' => 1, 'mandatory' => true],
            ],
        ];

        foreach ($mappingConfig as $layananNama => $mappings) {
            $layanan = MasterLayanan::where('nama_layanan', $layananNama)->first();
            if (!$layanan) {
                continue;
            }

            foreach ($mappings as $m) {
                $idBhp = $bhpMap[$m['nama']] ?? null;
                if (!$idBhp) {
                    continue;
                }

                DB::table('mapping_layanan_bhp')->updateOrInsert(
                    [
                        'id_layanan' => $layanan->id_layanan,
                        'id_bhp'     => $idBhp,
                    ],
                    [
                        'qty_default'  => $m['qty'],
                        'is_mandatory' => $m['mandatory'],
                        'updated_at'   => now(),
                        'created_at'   => now(),
                    ]
                );
            }
        }

        $this->command?->info('Mapping layanan BHP successfully seeded.');
    }
}
