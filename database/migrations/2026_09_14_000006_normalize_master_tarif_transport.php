<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $claimedServices = [];
        $transportTarifs = DB::table('master_tarif')
            ->where('is_transport', true)
            ->orderBy('id_master_tarif')
            ->get(['id_master_tarif', 'id_layanan']);

        foreach ($transportTarifs as $tarif) {
            $serviceIds = DB::table('master_tarif_layanan')
                ->where('id_master_tarif', $tarif->id_master_tarif)
                ->pluck('id_layanan')
                ->push($tarif->id_layanan)
                ->unique()
                ->map(fn ($id) => (int) $id)
                ->all();

            if (array_intersect($claimedServices, $serviceIds)) {
                DB::table('master_tarif')
                    ->where('id_master_tarif', $tarif->id_master_tarif)
                    ->update(['is_transport' => false]);
                continue;
            }

            $claimedServices = array_values(array_unique(array_merge($claimedServices, $serviceIds)));
        }
    }

    public function down(): void
    {
        // Normalization is intentionally not reversed.
    }
};
