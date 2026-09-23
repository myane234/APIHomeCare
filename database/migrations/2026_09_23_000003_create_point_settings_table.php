<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel konfigurasi sistem poin (single-row, id = 1).
     *
     *   point_rate        – Nominal Rp untuk mendapatkan 1 poin.
     *                       Contoh: 10000 → setiap kelipatan Rp10.000 = 1 poin.
     *                       Rumus: floor(jumlah_total / point_rate) = poin didapat.
     *
     *   point_expiry_days – Masa berlaku poin dalam hari sejak poin diperoleh.
     *                       Default 365 (1 tahun).
     *
     *   is_active         – Apakah fitur poin aktif secara global. Default true.
     */
    public function up(): void
    {
        Schema::create('point_settings', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('point_rate')
                  ->default(10000)
                  ->comment('Nominal Rp untuk mendapatkan 1 poin. Contoh: 10000 → Rp10.000 = 1 poin');

            $table->unsignedSmallInteger('point_expiry_days')
                  ->default(365)
                  ->comment('Masa berlaku poin dalam hari sejak diperoleh');

            $table->boolean('is_active')
                  ->default(true)
                  ->comment('Aktifkan / nonaktifkan fitur poin secara global');

            $table->string('updated_by')->nullable()
                  ->comment('ID admin terakhir yang mengubah setting ini');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_settings');
    }
};
