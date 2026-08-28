<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            if (!Schema::hasColumn('transaksis', 'midtrans_transaction_id')) {
                $table->string('midtrans_transaction_id')->nullable()->after('status_transaksi');
            }
            if (!Schema::hasColumn('transaksis', 'midtrans_order_id')) {
                $table->string('midtrans_order_id')->nullable()->after('midtrans_transaction_id');
            }
            if (!Schema::hasColumn('transaksis', 'qr_string')) {
                $table->longText('qr_string')->nullable()->comment('QR Code string untuk QRIS');
            }
            if (!Schema::hasColumn('transaksis', 'qr_url')) {
                $table->longText('qr_url')->nullable()->comment('URL untuk generate QR code');
            }
            if (!Schema::hasColumn('transaksis', 'va_number')) {
                $table->string('va_number')->nullable()->comment('Virtual Account Number');
            }
            if (!Schema::hasColumn('transaksis', 'bank_va')) {
                $table->string('bank_va')->nullable()->comment('Nama bank VA (BCA, BNI, BRI, dll)');
            }
            if (!Schema::hasColumn('transaksis', 'payment_method')) {
                $table->string('payment_method')->nullable()->comment('Payment method: qris, bank_transfer, gopay, dll');
            }
            if (!Schema::hasColumn('transaksis', 'midtrans_response')) {
                $table->json('midtrans_response')->nullable()->comment('Full response dari Midtrans');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            $columnsToDrop = array_filter([
                'midtrans_transaction_id',
                'midtrans_order_id',
                'qr_string',
                'qr_url',
                'va_number',
                'bank_va',
                'payment_method',
                'midtrans_response',
            ], fn($col) => Schema::hasColumn('transaksis', $col));

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};