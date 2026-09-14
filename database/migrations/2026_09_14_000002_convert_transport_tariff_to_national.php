<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $transportIds = DB::table('master_tarif_transport')
            ->orderBy('id_transport')
            ->pluck('id_transport');

        if ($transportIds->count() > 1) {
            DB::table('master_tarif_transport')
                ->whereIn('id_transport', $transportIds->slice(1)->values())
                ->delete();
        }

        Schema::table('master_tarif_transport', function (Blueprint $table) {
            $table->dropForeign(['id_kota']);
            $table->dropColumn('id_kota');
        });
    }

    public function down(): void
    {
        Schema::table('master_tarif_transport', function (Blueprint $table) {
            $table->unsignedBigInteger('id_kota')->nullable();
            $table->foreign('id_kota')
                ->references('id_kota')
                ->on('master_kota_kabupaten')
                ->nullOnDelete();
        });
    }
};