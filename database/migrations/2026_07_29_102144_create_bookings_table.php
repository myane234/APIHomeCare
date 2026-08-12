<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->bigIncrements('id_booking');
            $table->string('booking_code');
            $table->unsignedBigInteger('id_pasien')->index('bookings_id_pasien_foreign');
            $table->unsignedBigInteger('id_layanan')->index('bookings_id_layanan_foreign');
            $table->unsignedBigInteger('id_master_tarif')->index('bookings_id_master_tarif_foreign');
            $table->unsignedBigInteger('id_tenaga_medis')->index('bookings_id_tenaga_medis_foreign');
            $table->unsignedBigInteger('id_promo')->nullable()->index('bookings_id_promo_foreign');
            $table->date('tanggal_kunjungan');
            $table->time('jam_kunjungan');
            $table->string('alamat_kunjungan');
            $table->decimal('latitude_kunjungan', 10, 7);
            $table->decimal('longitude_kunjungan', 11, 7);
            $table->enum('status_booking', ['Pending', 'DiPerjalanan', 'Tindakan', 'Selesai', 'Dibatalkan']);
            // Kolom Breakdown Transaksi 
            $table->unsignedBigInteger('id_metode_pembayaran')->nullable()->index('bookings_id_metode_pembayaran_foreign');
            $table->decimal('tarif_dasar', 12, 2)->default(0)->comment('Berdasarkan master_tarif_layanan');
            $table->decimal('potongan_promo', 12, 2)->default(0);
            $table->decimal('pajak_ppn', 12, 2)->default(0)->comment('PPN yang dibebankan ke pasien');
            $table->decimal('fee_pg_midtrans', 12, 2)->default(0)->comment('Biaya payment gateway');
            $table->decimal('komisi_nakes', 12, 2)->default(0)->comment('Bagian bersih perawat');
            $table->decimal('komisi_homecare', 12, 2)->default(0)->comment('Bagian dari prosentase master');
            $table->decimal('biaya_admin', 12, 2)->default(0)->comment('Additional flat admin_fee');
            $table->decimal('total_tagihan_pasien', 12, 2)->default(0)->comment('Total tagihan kesuluruhan');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
