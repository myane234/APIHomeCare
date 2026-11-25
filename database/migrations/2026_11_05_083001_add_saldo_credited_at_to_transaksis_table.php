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
        Schema::table('transaksis', function (Blueprint $table) {
            $table->timestamp('saldo_credited_at')
                  ->nullable()
                  ->after('waktu_bayar')
                  ->comment('Timestamp saat saldo nakes berhasil dikreditkan. NULL = belum dikreditkan. Guard idempotency.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            $table->dropColumn('saldo_credited_at');
        });
    }
};
