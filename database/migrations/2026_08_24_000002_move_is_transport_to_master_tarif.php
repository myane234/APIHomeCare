<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('master_layanan', function (Blueprint $table) {
            $table->dropColumn('include_transport');
        });

        Schema::table('master_tarif', function (Blueprint $table) {
            $table->boolean('is_transport')->default(false)->after('fee_platform_nominal')->comment('True = tarif ini termasuk transport');
        });
    }

    public function down(): void
    {
        Schema::table('master_tarif', function (Blueprint $table) {
            $table->dropColumn('is_transport');
        });

        Schema::table('master_layanan', function (Blueprint $table) {
            $table->boolean('include_transport')->default(false)->after('harga')->comment('True = transport sudah termasuk di harga');
        });
    }
};
