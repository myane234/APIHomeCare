<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksi_tambahan', function (Blueprint $table) {
            $table->id('id_transaksi_tambahan');
            $table->unsignedBigInteger('id_booking');
            $table->string('kode_booking')->unique();
            $table->string('midtrans_order_id')->unique()->nullable();
            $table->decimal('jumlah_total', 12, 2)->default(0);
            $table->string('metode_pembayaran')->nullable();
            $table->string('status_transaksi')->default('Belum Bayar');
            $table->timestamp('waktu_bayar')->nullable();
            $table->decimal('sb_tambahan', 12, 2)->default(0);
            $table->decimal('hpp_bhp_tambahan', 12, 2)->default(0);
            $table->string('midtrans_transaction_id')->nullable();
            $table->text('qr_string')->nullable();
            $table->text('qr_url')->nullable();
            $table->string('va_number')->nullable();
            $table->string('bank_va')->nullable();
            $table->string('payment_method')->nullable();
            $table->json('midtrans_response')->nullable();
            $table->timestamps();

            $table->foreign('id_booking')->references('id_booking')->on('bookings')->onDelete('cascade');
            $table->index(['id_booking', 'status_transaksi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi_tambahan');
    }
};
