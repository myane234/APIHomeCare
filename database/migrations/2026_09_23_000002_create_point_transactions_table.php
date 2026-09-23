<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel mutasi poin pasien (audit trail lengkap).
     *
     * type:
     *   EARN     – poin masuk saat transaksi/booking selesai/dibayar
     *   REDEEM   – poin dipakai oleh pasien untuk diskon (reserved, belum diimplementasi)
     *   EXPIRED  – poin hangus karena melewati expired_at
     */
    public function up(): void
    {
        Schema::create('point_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('id_pasien')
                  ->comment('FK ke tabel pasiens');

            $table->unsignedBigInteger('id_booking')->nullable()
                  ->comment('FK ke tabel bookings (sumber EARN), nullable untuk EXPIRED/REDEEM');

            $table->unsignedBigInteger('id_transaksi')->nullable()
                  ->comment('FK ke tabel transaksis, nullable');

            // Tipe mutasi
            $table->enum('type', ['EARN', 'REDEEM', 'EXPIRED'])
                  ->comment('Jenis mutasi poin');

            // Jumlah poin (selalu positif, makna bergantung type)
            $table->unsignedInteger('amount')
                  ->default(0)
                  ->comment('Jumlah poin yang bergerak (selalu positif)');

            // Snapshot saldo setelah transaksi ini
            $table->unsignedInteger('balance_after')
                  ->default(0)
                  ->comment('Saldo points_balance pasien setelah mutasi ini');

            // Masa berlaku — hanya diisi pada EARN; EXPIRED/REDEEM bisa null
            $table->timestamp('expired_at')->nullable()
                  ->comment('Kapan poin ini kedaluwarsa (now() + point_expiry_days)');

            // Referensi ke baris EARN yang di-expire (untuk EXPIRED records)
            $table->unsignedBigInteger('reference_earn_id')->nullable()
                  ->comment('ID point_transactions EARN yang direferensikan oleh EXPIRED');

            // Catatan opsional
            $table->string('note')->nullable()
                  ->comment('Keterangan tambahan, mis. "Booking B-26-0012"');

            $table->timestamps();

            // ── Indeks & FK ───────────────────────────────────────────
            $table->foreign('id_pasien')
                  ->references('id_pasien')->on('pasiens')
                  ->onDelete('cascade');

            $table->foreign('id_booking')
                  ->references('id_booking')->on('bookings')
                  ->onDelete('set null');

            $table->foreign('id_transaksi')
                  ->references('id_transaksi')->on('transaksis')
                  ->onDelete('set null');

            $table->index(['id_pasien', 'type']);
            $table->index(['id_pasien', 'expired_at']);
            $table->index(['type', 'expired_at']); // untuk scheduler daily expire
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_transactions');
    }
};
