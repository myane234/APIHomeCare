<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterBankSeeder extends Seeder
{
    public function run(): void
    {
        $banks = [
            ['nama_bank' => 'Bank Central Asia (BCA)',          'kode_bank' => 'BCA',    'is_active' => true],
            ['nama_bank' => 'Bank Negara Indonesia (BNI)',       'kode_bank' => 'BNI',    'is_active' => true],
            ['nama_bank' => 'Bank Rakyat Indonesia (BRI)',       'kode_bank' => 'BRI',    'is_active' => true],
            ['nama_bank' => 'Bank Mandiri',                      'kode_bank' => 'MANDIRI','is_active' => true],
            ['nama_bank' => 'Bank Syariah Indonesia (BSI)',      'kode_bank' => 'BSI',    'is_active' => true],
            ['nama_bank' => 'CIMB Niaga',                        'kode_bank' => 'CIMB',   'is_active' => true],
            ['nama_bank' => 'Bank Danamon',                      'kode_bank' => 'DANAMON','is_active' => true],
            ['nama_bank' => 'Bank Permata',                      'kode_bank' => 'PERMATA','is_active' => true],
            ['nama_bank' => 'Bank Tabungan Negara (BTN)',        'kode_bank' => 'BTN',    'is_active' => true],
            ['nama_bank' => 'Bank OCBC NISP',                    'kode_bank' => 'OCBC',   'is_active' => true],
            ['nama_bank' => 'Bank Mega',                         'kode_bank' => 'MEGA',   'is_active' => true],
            ['nama_bank' => 'Bank Panin',                        'kode_bank' => 'PANIN',  'is_active' => true],
            ['nama_bank' => 'Bank Maybank Indonesia',            'kode_bank' => 'MAYBANK','is_active' => true],
            ['nama_bank' => 'Bank HSBC Indonesia',               'kode_bank' => 'HSBC',   'is_active' => true],
            ['nama_bank' => 'Bank UOB Indonesia',                'kode_bank' => 'UOB',    'is_active' => true],
            ['nama_bank' => 'Bank Commonwealth',                 'kode_bank' => 'COMM',   'is_active' => true],
            ['nama_bank' => 'Bank Bukopin',                      'kode_bank' => 'BUKOPIN','is_active' => true],
            ['nama_bank' => 'Bank Sinarmas',                     'kode_bank' => 'SINARMAS','is_active' => true],
            ['nama_bank' => 'Bank BJB (Bank Jabar Banten)',      'kode_bank' => 'BJB',    'is_active' => true],
            ['nama_bank' => 'Bank DKI',                          'kode_bank' => 'DKI',    'is_active' => true],
            ['nama_bank' => 'BPD Jawa Tengah (Bank Jateng)',     'kode_bank' => 'JATENG', 'is_active' => true],
            ['nama_bank' => 'BPD Jawa Timur (Bank Jatim)',       'kode_bank' => 'JATIM',  'is_active' => true],
            ['nama_bank' => 'Bank Muamalat Indonesia',           'kode_bank' => 'MUAMALAT','is_active' => true],
            ['nama_bank' => 'Bank Neo Commerce (BNC)',           'kode_bank' => 'BNC',    'is_active' => true],
            ['nama_bank' => 'SeaBank Indonesia',                 'kode_bank' => 'SEABANK','is_active' => true],
            ['nama_bank' => 'Jenius (BTPN)',                     'kode_bank' => 'JENIUS', 'is_active' => true],
            ['nama_bank' => 'Bank Jago',                         'kode_bank' => 'JAGO',   'is_active' => true],
            ['nama_bank' => 'Allo Bank',                         'kode_bank' => 'ALLO',   'is_active' => true],
        ];

        foreach ($banks as $bank) {
            DB::table('master_bank')->insertOrIgnore([
                'nama_bank'  => $bank['nama_bank'],
                'kode_bank'  => $bank['kode_bank'],
                'gambar'     => null,
                'is_active'  => $bank['is_active'],
                'created_by' => null,
                'deleted_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('✅ MasterBankSeeder: ' . count($banks) . ' bank berhasil diseed.');
    }
}
