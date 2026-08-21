<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Users;
use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Buat record di tabel admins secara langsung
        Admin::firstOrCreate(
            ['email' => 'faruq@homecare.com'],
            [
                'password' => Hash::make('faruqganteng'),
                'nama_lengkap' => 'Faruq Admin',
                'tier_admin' => 'Admin',
                'is_active' => true,
            ]
        );
    }
}
