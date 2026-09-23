<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom point-related ke tabel transaksis.
     *
     * points_used     : Jumlah poin yang dipakai pasien pada transaksi ini.
     *                   0 berarti tidak memakai poin.
     *
     * points_discount : Nominal diskon dalam Rupiah yang berasal dari poin.
     *                   Karena 1 poin = Rp 1, maka points_discount == points_used.
     */
    public function up(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            $table->unsignedInteger('points_used')
                  ->default(0)
                  ->comment('Jumlah poin yang dipakai pasien pada transaksi ini')
                  ->after('profit_hc');

            $table->decimal('points_discount', 15, 2)
                  ->default(0)
                  ->comment('Nominal diskon Rupiah dari poin (1 poin = Rp 1)')
                  ->after('points_used');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            $table->dropColumn(['points_used', 'points_discount']);
        });
    }
};
