<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah kolom saldo ke tabel tenaga_medis
        Schema::table('tenaga_medis', function (Blueprint $table) {
            $table->decimal('saldo', 12, 2)->default(0)->after('no_rekening')
                  ->comment('Saldo tersedia nakes yang siap di-withdraw (dalam Rupiah)');
        });

        // Buat tabel withdrawals
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->bigIncrements('id_withdrawal');
            $table->unsignedBigInteger('id_tenaga_medis')->index();
            $table->decimal('jumlah', 12, 2)->comment('Nominal yang di-withdraw');

            // Status alur: pending → processing → success/failed
            $table->enum('status', ['pending', 'processing', 'success', 'failed'])
                  ->default('pending');

            // Snapshot data rekening nakes saat withdraw dibuat
            // (disimpan snapshot agar riwayat tetap akurat walau nakes ganti rekening)
            $table->string('beneficiary_name')->comment('Snapshot nama_pemilik_rekening saat withdraw');
            $table->string('beneficiary_account', 30)->comment('Snapshot no_rekening saat withdraw');
            $table->string('beneficiary_bank', 20)->comment('Snapshot kode_bank (lowercase) saat withdraw');
            $table->string('beneficiary_bank_name')->comment('Snapshot nama_bank saat withdraw');

            // Data dari Midtrans Iris
            $table->string('iris_reference_no')->nullable()->unique()
                  ->comment('Reference number dari Midtrans Iris');
            $table->json('iris_response')->nullable()
                  ->comment('Response mentah dari Midtrans Iris');

            // Info tambahan
            $table->text('failure_reason')->nullable()
                  ->comment('Alasan gagal jika status = failed');
            $table->timestamp('processed_at')->nullable()
                  ->comment('Waktu Iris mengkonfirmasi transfer');

            $table->timestamps();

            $table->foreign('id_tenaga_medis')
                  ->references('id_tenaga_medis')
                  ->on('tenaga_medis')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');

        Schema::table('tenaga_medis', function (Blueprint $table) {
            $table->dropColumn('saldo');
        });
    }
};
