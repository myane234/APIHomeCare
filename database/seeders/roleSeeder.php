<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class roleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::firstOrCreate([
           'nama_role' =>  'admin'
        ]);

        Role::firstOrCreate([
            'nama_role' => 'pasien'
        ]);

        Role::firstOrCreate([
            'nama_role' => 'nakes'
        ]);
    }
}
