<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_kunjungan', function (Blueprint $table) {
            $table->id('id_riwayat_kunjungan');
            $table->unsignedBigInteger('id_booking');
            $table->unsignedBigInteger('id_tenaga_medis');
            $table->enum('status_kunjungan', ['DiPerjalanan', 'Tindakan', 'Selesai'])->default('DiPerjalanan');
            $table->timestamps();

            $table->foreign('id_booking')
                ->references('id_booking')->on('bookings')
                ->cascadeOnDelete();
            $table->foreign('id_tenaga_medis')
                ->references('id_tenaga_medis')->on('tenaga_medis')
                ->cascadeOnDelete();
            $table->unique(['id_booking', 'id_tenaga_medis']);
            $table->index(['id_tenaga_medis', 'status_kunjungan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_kunjungan');
    }
};
