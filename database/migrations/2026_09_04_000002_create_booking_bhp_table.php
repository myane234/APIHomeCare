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
        Schema::create('booking_bhp', function (Blueprint $table) {
            $table->id('id_booking_bhp');
            $table->unsignedBigInteger('id_booking');
            $table->unsignedBigInteger('id_layanan')->nullable();
            $table->unsignedBigInteger('id_bhp');
            $table->integer('qty_default')->default(1);
            $table->integer('qty_real')->default(1);
            $table->integer('qty_tambahan')->default(0);
            $table->decimal('harga_jual', 12, 2)->default(0);
            $table->decimal('harga_modal', 12, 2)->default(0);
            $table->decimal('total_sb_tambahan', 12, 2)->default(0);
            $table->decimal('total_hpp_tambahan', 12, 2)->default(0);
            $table->timestamps();

            $table->foreign('id_booking')->references('id_booking')->on('bookings')->onDelete('cascade');
            $table->foreign('id_layanan')->references('id_layanan')->on('master_layanan')->onDelete('set null');
            $table->foreign('id_bhp')->references('id_bhp')->on('master_bhp')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_bhp');
    }
};
