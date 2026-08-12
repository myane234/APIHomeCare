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
            $table->unsignedBigInteger('id_promo')->nullable();
            
            // --- Rincian Tagihan ke Pasien (Di-kalkulasi saat Checkout)
            $table->decimal('tarif_layanan', 12, 2)->default(0);
            $table->decimal('tarif_bhp', 12, 2)->default(0);
            $table->decimal('tarif_transport', 12, 2)->default(0);
            $table->decimal('pajak_ppn', 12, 2)->default(0);
            $table->decimal('biaya_admin_app', 12, 2)->default(0);
            $table->decimal('potongan_promo', 12, 2)->default(0);
            $table->decimal('total_tagihan_pasien', 12, 2)->default(0)->comment('Total akhir dibayar pasien');
            
            
            $table->decimal('hpp_bhp', 12, 2)->default(0)->comment('Modal BHP');
            $table->decimal('komisi_nakes', 12, 2)->default(0)->comment('Uang bersih perawat');
            $table->decimal('profit_homecare', 12, 2)->default(0)->comment('Uang bersih platform');
            $table->decimal('fee_midtrans', 12, 2)->default(0);
            
            $table->unsignedBigInteger('id_metode_pembayaran')->nullable();
            $table->enum('status_bayar', ['Belum Bayar', 'Lunas', 'Gagal', 'Refund'])->default('Belum Bayar');
            $table->timestamp('waktu_bayar')->nullable();
            $table->timestamps();

            // Foreign Keys
            $table->foreign('id_booking')->references('id_booking')->on('bookings')->onDelete('cascade');
            $table->foreign('id_promo')->references('id_promo')->on('promos')->onDelete('set null');
            $table->foreign('id_metode_pembayaran')->references('id_metode')->on('master_metode_pembayaran')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksis');
    }
};