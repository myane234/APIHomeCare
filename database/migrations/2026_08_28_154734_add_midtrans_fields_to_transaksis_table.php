<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            $table->string('midtrans_order_id')->nullable()->unique()->after('id_booking');
            $table->string('midtrans_transaction_id')->nullable()->after('midtrans_order_id');
            $table->string('payment_method')->nullable()->after('midtrans_transaction_id');
            $table->string('va_number')->nullable()->after('payment_method');
            $table->string('bank_va')->nullable()->after('va_number');
            $table->text('qr_string')->nullable()->after('bank_va');
            $table->text('qr_url')->nullable()->after('qr_string');
            $table->json('midtrans_response')->nullable()->after('qr_url');
        });
    }

    public function down(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            $table->dropColumn([
                'midtrans_order_id',
                'midtrans_transaction_id',
                'payment_method',
                'va_number',
                'bank_va',
                'qr_string',
                'qr_url',
                'midtrans_response',
            ]);
        });
    }
};