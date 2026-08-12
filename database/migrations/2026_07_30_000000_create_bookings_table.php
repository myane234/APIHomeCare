<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->bigIncrements('id_booking');
            $table->string('booking_code')->unique();
            
            // Siapa, apa, dan oleh siapa
            $table->unsignedBigInteger('id_pasien');
            $table->unsignedBigInteger('id_layanan');
            $table->unsignedBigInteger('id_tenaga_medis');
            
            // Waktu dan Tempat
            $table->date('tanggal_kunjungan');
            $table->time('jam_kunjungan');
            $table->string('alamat_kunjungan');
            $table->decimal('latitude_kunjungan', 10, 7);
            $table->decimal('longitude_kunjungan', 11, 7);
            
            // Status Lapangan
            $table->enum('status_booking', ['Pending', 'DiPerjalanan', 'Tindakan', 'Selesai', 'Dibatalkan'])->default('Pending');
            $table->timestamps();

            // Foreign keys
            $table->foreign('id_pasien')->references('id_pasien')->on('pasiens')->onDelete('cascade');
            $table->foreign('id_layanan')->references('id_layanan')->on('master_layanan')->onDelete('cascade');
            $table->foreign('id_tenaga_medis')->references('id_tenaga_medis')->on('tenaga_medis')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};