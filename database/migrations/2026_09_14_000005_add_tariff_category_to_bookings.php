<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('id_kategori_tarif')->nullable()->after('id_layanan');
            $table->foreign('id_kategori_tarif')
                ->references('id_kategori_tarif')
                ->on('master_kategori_tarif')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['id_kategori_tarif']);
            $table->dropColumn('id_kategori_tarif');
        });
    }
};