<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */

    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Foreign key to master_tarif explicitly
            $table->foreign('id_master_tarif')->references('id_master_tarif')->on('master_tarif')->onUpdate('restrict')->onDelete('restrict');
            
            $table->foreign('id_pasien')->references('id_pasien')->on('pasiens')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('id_promo')->references('id_promo')->on('promos')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('id_tenaga_medis')->references('id_tenaga_medis')->on('tenaga_medis')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('id_metode_pembayaran')->references('id_metode')->on('master_metode_pembayaran')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['id_master_tarif']);
            $table->dropForeign(['id_pasien']);
            $table->dropForeign(['id_promo']);
            $table->dropForeign(['id_tenaga_medis']);
            $table->dropForeign(['id_metode_pembayaran']);
        });
    }
};