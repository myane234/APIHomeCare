<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom max_point_discount_percent ke tabel point_settings.
     *
     * max_point_discount_percent: Persentase maksimal dari total tagihan yang bisa
     * dipotong menggunakan poin. Default 50 (artinya maks 50% dari total tagihan).
     *
     * Contoh:
     *   Total tagihan Rp 200.000
     *   max_point_discount_percent = 50
     *   → Maks diskon dari poin = Rp 100.000
     *   → Pasien hanya bisa redeem maksimal 100.000 poin
     */
    public function up(): void
    {
        Schema::table('point_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_point_discount_percent')
                  ->default(50)
                  ->comment('Persentase maks dari total tagihan yang bisa dipotong poin. Default 50%.')
                  ->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('point_settings', function (Blueprint $table) {
            $table->dropColumn('max_point_discount_percent');
        });
    }
};
