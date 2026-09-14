<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterTarifTransportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $transport = DB::table('master_tarif_transport')->orderBy('id_transport')->first();

        if ($transport) {
            DB::table('master_tarif_transport')
                ->where('id_transport', $transport->id_transport)
                ->update([
                    'tarif_per_10_km' => 20000.00,
                    'updated_at' => now(),
                ]);
            return;
        }

        DB::table('master_tarif_transport')->insert([
            'tarif_per_10_km' => 20000.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
