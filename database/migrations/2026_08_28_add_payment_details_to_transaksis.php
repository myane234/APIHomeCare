<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            // Payment details dari Midtrans
            $table->string('midtrans_transaction_id')->nullable()->after('status_transaksi');
            $table->string('midtrans_order_id')->nullable()->after('midtrans_transaction_id');
            
            // Untuk QRIS
            $table->longText('qr_string')->nullable()->after('midtrans_order_id')->comment('QR Code string untuk QRIS');
            $table->longText('qr_url')->nullable()->after('qr_string')->comment('URL untuk generate QR code');
            
            // Untuk Bank Transfer (VA)
            $table->string('va_number')->nullable()->after('qr_url')->comment('Virtual Account Number');
            $table->string('bank_va')->nullable()->after('va_number')->comment('Nama bank VA (BCA, BNI, BRI, dll)');
            
            // Payment details lainnya
            $table->string('payment_method')->nullable()->after('bank_va')->comment('Payment method: qris, bank_transfer, gopay, dll');
            $table->json('midtrans_response')->nullable()->after('payment_method')->comment('Full response dari Midtrans');
            
            $table->index('midtrans_transaction_id');
            $table->index('midtrans_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            $table->dropIndex(['midtrans_transaction_id']);
            $table->dropIndex(['midtrans_order_id']);
            $table->dropColumn([
                'midtrans_transaction_id',
                'midtrans_order_id',
                'qr_string',
                'qr_url',
                'va_number',
                'bank_va',
                'payment_method',
                'midtrans_response',
            ]);
        });
    }
};
