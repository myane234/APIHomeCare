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
        AdminTier::updateOrCreate(
            ['slug' => 'super-admin'],
            [
                'nama_tier' => 'Super Admin',
                'deskripsi' => 'Super Admin dengan akses penuh ke sistem',
                'is_protected' => true,
                'permissions' => ['*'],
            ]
        );

        AdminTier::updateOrCreate(
            ['slug' => 'admin'],
            [
                'nama_tier' => 'Admin',
                'deskripsi' => 'Admin standar untuk manajemen konten dan data',
                'is_protected' => true,
                'permissions' => ['dashboard', 'kelola-konten', 'kelola-konten-home', 'kelola-konten-about', 'layanan', 'promo', 'artikel'],
            ]
        );

        AdminTier::updateOrCreate(
            ['slug' => 'editor'],
            [
                'nama_tier' => 'Editor',
                'deskripsi' => 'Admin khusus publikasi artikel dan layanan',
                'is_protected' => false,
                'permissions' => ['dashboard', 'layanan', 'promo', 'artikel', 'master-kategori'],
            ]
        );

        AdminTier::updateOrCreate(
            ['slug' => 'finance'],
            [
                'nama_tier' => 'Finance',
                'deskripsi' => 'Admin khusus untuk manajemen pembayaran dan keuangan',
                'is_protected' => false,
                'permissions' => ['dashboard', 'master-tarif', 'master-komponen-tarif', 'master-tarif-transport', 'master-kategori-pembayaran', 'master-metode-pembayaran', 'booking'],
            ]
        );
    }
}
