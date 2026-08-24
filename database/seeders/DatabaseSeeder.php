<?php

namespace Database\Seeders;

use App\Models\Users;
use App\Models\Pasien;
//use App\Models\Layanan;
use Database\Seeders\WilayahSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            roleSeeder::class,
            AdminTierSeeder::class,
            AdminSeeder::class,
            SuperAdminSeeder::class,
            KategoriLayananSeeder::class,
            KategoriArtikelSeeder::class,
            MasterBankSeeder::class,
            WilayahSeeder::class,
            MasterPendidikanSeeder::class,
            MasterUniversitasSeeder::class,
            LayananSeeder::class,
            ArtikelSeeder::class,
            PromoSeeder::class,
            GlobalConfigSeeder::class,
        ]);

        // Jika ingin membuat data pasien tetap langsung di sini, silakan:
        Pasien::factory(10)->create();

        Pasien::factory(5)->create([
            'id_user' => Users::factory()->google(),
        ]);

        $this->call(TenagaMedisSeeder::class);
    }
}
