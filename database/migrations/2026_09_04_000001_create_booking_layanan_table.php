<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Multi-Layanan per Booking
 *
 * Menambahkan tabel pivot `booking_layanan` yang menyimpan rincian biaya
 * (SL, SB, HPP BHP, hak nakes) untuk setiap layanan dalam satu booking.
 *
 * Kolom `bookings.id_layanan` diubah menjadi nullable untuk backward
 * compatibility — akan diisi dengan layanan pertama dari layanan_ids.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Jadikan id_layanan di bookings nullable (backward compat)
        Schema::table('bookings', function (Blueprint $table) {
            // Hapus FK lama sebelum mengubah kolom
            $table->dropForeign(['id_layanan']);
            $table->unsignedBigInteger('id_layanan')->nullable()->change();
            $table->foreign('id_layanan')
                ->references('id_layanan')->on('master_layanan')
                ->nullOnDelete();
        });

        // 2. Buat tabel pivot booking_layanan
        Schema::create('booking_layanan', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('id_booking');
            $table->unsignedBigInteger('id_layanan');

            // Urutan layanan dalam satu booking (1 = layanan utama)
            $table->unsignedSmallInteger('urutan')->default(1);

            // Rincian biaya layanan ini
            $table->decimal('sl', 14, 2)->default(0)->comment('Tarif Jasa Layanan');
            $table->decimal('sb', 14, 2)->default(0)->comment('Biaya BHP / Bahan Habis Pakai');
            $table->decimal('hpp_bhp', 14, 2)->default(0)->comment('HPP BHP (modal)');
            $table->decimal('hak_nakes_layanan', 14, 2)->default(0)->comment('Fee Nakes untuk layanan ini (tanpa transport)');

            $table->timestamps();

            // FK & index
            $table->foreign('id_booking')
                ->references('id_booking')->on('bookings')
                ->cascadeOnDelete();

            $table->foreign('id_layanan')
                ->references('id_layanan')->on('master_layanan')
                ->cascadeOnDelete();

            $table->index(['id_booking', 'urutan']);
            $table->unique(['id_booking', 'id_layanan'], 'booking_layanan_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_layanan');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['id_layanan']);
            $table->unsignedBigInteger('id_layanan')->nullable(false)->change();
            $table->foreign('id_layanan')
                ->references('id_layanan')->on('master_layanan')
                ->cascadeOnDelete();
        });
    }
};
