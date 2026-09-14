<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('id_kota')->nullable()->after('id_layanan');
            $table->foreign('id_kota')
                ->references('id_kota')
                ->on('master_kota_kabupaten')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['id_kota']);
            $table->dropColumn('id_kota');
        });
    }
};
