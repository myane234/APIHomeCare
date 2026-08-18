<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Users;
use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seeder untuk akun Super Admin.
     * Email   : admin@gmail.com
     * Password: faruqganteng
     */
    public function run(): void
    {
        // Buat atau update record di tabel admins dengan tier Super Admin
        $admin = Admin::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'password'     => Hash::make('faruqganteng'),
                'nama_lengkap' => 'Super Admin',
                'tier_admin'   => 'Super Admin',
                'is_active'    => true,
            ]
        );

        // Pastikan password selalu sinkron (jika admin sudah ada)
        if (!Hash::check('faruqganteng', $admin->password)) {
            $admin->password = Hash::make('faruqganteng');
            $admin->save();
        }

        $this->command->info('✅ Super Admin seeded: admin@gmail.com / faruqganteng');
    }
}
