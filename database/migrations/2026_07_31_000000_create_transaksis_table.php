<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transaksis', function (Blueprint $table) {
            $table->bigIncrements('id_transaksi');
            $table->unsignedBigInteger('id_booking')->unique();
            
            $table->decimal('jumlah_total', 12, 2)->default(0);
            $table->string('metode_pembayaran')->nullable();
            $table->string('status_transaksi')->default('Belum Bayar');
            $table->timestamp('waktu_bayar')->nullable();

            // Snapshot biaya
            $table->decimal('sl', 12, 2)->default(0);
            $table->decimal('sb', 12, 2)->default(0);
            $table->decimal('st', 12, 2)->default(0);
            $table->decimal('ba', 12, 2)->default(0);
            $table->decimal('ppn', 12, 2)->default(0);

            // Snapshot %
            $table->decimal('persen_ppn', 5, 2)->default(0);
            $table->decimal('persen_fee_nakes', 5, 2)->default(0);

            // Bagi hasil & biaya lain
            $table->decimal('fee_midtrans', 12, 2)->default(0);
            $table->decimal('hpp_bhp', 12, 2)->default(0);
            $table->decimal('hak_nakes', 12, 2)->default(0);
            $table->decimal('profit_hc', 12, 2)->default(0);

            $table->timestamps();

            // Foreign Keys
            $table->foreign('id_booking')->references('id_booking')->on('bookings')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksis');
    }
};