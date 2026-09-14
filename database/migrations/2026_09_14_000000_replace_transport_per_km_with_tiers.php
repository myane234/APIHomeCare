<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('master_tarif_transport', function (Blueprint $table) {
            $table->decimal('tarif_per_10_km', 10, 2)->default(0)->after('id_kota');
        });

        Schema::table('master_tarif_transport', function (Blueprint $table) {
            $table->dropColumn(['tarif_awal', 'tarif_per_kilometer']);
        });
    }

    public function down(): void
    {
        Schema::table('master_tarif_transport', function (Blueprint $table) {
            $table->decimal('tarif_awal', 10, 2)->default(0)->after('id_kota');
            $table->decimal('tarif_per_kilometer', 10, 2)->default(0)->after('tarif_awal');
        });

        Schema::table('master_tarif_transport', function (Blueprint $table) {
            $table->dropColumn([
                'tarif_per_10_km',
            ]);
        });
    }
};
