<?php

namespace Database\Seeders;

use App\Models\AdminTier;
use Illuminate\Database\Seeder;

class AdminTierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AdminTier::firstOrCreate(
            ['nama_tier' => 'Super Admin'],
            [
                'slug' => 'super-admin',
                'deskripsi' => 'Super Admin dengan akses penuh ke sistem',
                'is_protected' => true,
            ]
        );

        AdminTier::firstOrCreate(
            ['nama_tier' => 'Admin'],
            [
                'slug' => 'admin',
                'deskripsi' => 'Admin standar untuk manajemen data',
                'is_protected' => true,
            ]
        );

        // Contoh tier tambahan fleksibel lainnya
        AdminTier::firstOrCreate(
            ['nama_tier' => 'Finance'],
            [
                'slug' => 'finance',
                'deskripsi' => 'Admin khusus untuk manajemen pembayaran dan keuangan',
                'is_protected' => false,
            ]
        );
    }
}
