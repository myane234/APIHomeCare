<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan kolom-kolom yang dibutuhkan ke tabel master_layanan:
     *  - id_master_tarif  : FK ke tabel master_tarif (template blueprint)
     *  - harga            : harga dasar layanan (SL / tarif jasa)
     *  - include_transport: apakah transport sudah termasuk di harga
     */
    public function up(): void
    {
        Schema::table('master_layanan', function (Blueprint $table) {
            // FK ke template tarif
            $table->unsignedBigInteger('id_master_tarif')
                  ->nullable()
                  ->after('id_layanan')
                  ->comment('FK ke master_tarif — template komponen biaya yang dipakai layanan ini');

            // Harga dasar layanan (SL)
            $table->decimal('harga', 12, 2)
                  ->default(0)
                  ->after('deskripsi_layanan')
                  ->comment('Harga jasa layanan (SL) sebelum komponen biaya lain');

            // Flag transport
            $table->boolean('include_transport')
                  ->default(false)
                  ->after('harga')
                  ->comment('True = transport sudah termasuk di harga, tidak dihitung terpisah');

            $table->foreign('id_master_tarif')
                  ->references('id_master_tarif')
                  ->on('master_tarif')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('master_layanan', function (Blueprint $table) {
            $table->dropForeign(['id_master_tarif']);
            $table->dropColumn(['id_master_tarif', 'harga', 'include_transport']);
        });
    }
};
