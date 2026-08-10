<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * NOTE: Migration ini HARUS jalan SETELAH tabel-tabel berikut ada:
     * - master_layanan
     * - master_kota_kabupaten
     */
    public function up(): void
    {
        Schema::create('master_tarif', function (Blueprint $table) {
            $table->id('id_master_tarif');

            $table->string('nama_template')->comment('Ex: Reguler, Weekend, Lansia -- baris dgn nama sama = satu paket');

            $table->unsignedBigInteger('id_layanan');
            $table->unsignedBigInteger('id_kota')->nullable()->comment('Null = tarif nasional/default');

            // --- Harga inti ---
            $table->decimal('tarif_pasien', 12, 2)->comment('Harga fixed dibayar pasien');

            // --- Transport (referensi cepat, dihitung final saat booking) ---
            $table->decimal('transport_base_fare', 10, 2)->default(0);
            $table->decimal('transport_per_km', 10, 2)->default(0);

            // --- BHP ---
            $table->decimal('total_bhp', 10, 2)->default(0)->comment('SUM harga_jual x qty dari mapping_layanan_bhp');

            // --- Nakes ---
            $table->unsignedTinyInteger('potongan_persen_nakes')->default(20);
            $table->decimal('fee_nakes_nominal', 12, 2)->default(0)->comment('tarif_pasien x potongan_persen_nakes / 100');

            // --- Komponen biaya (hasil kalkulasi dari master_komponen_biaya) ---
            $table->decimal('total_ppn', 10, 2)->default(0);
            $table->decimal('total_biaya_admin', 10, 2)->default(0);
            $table->decimal('total_asuransi', 10, 2)->default(0);

            // --- Ringkasan ---
            $table->decimal('subtotal', 12, 2)->default(0)->comment('tarif_pasien + total_bhp + semua komponen');
            $table->decimal('total_tarif_final', 12, 2)->default(0)->comment('Belum termasuk transport (dihitung dinamis saat booking)');

            // --- Metadata sync ---
            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->nullable()->comment('Kapan terakhir di-rebuild dari tabel sumber');

            $table->timestamps();

            // Constraint inti: 1 nama_template x 1 layanan x 1 kota = 1 baris harga
            // (kombinasi layanan+kota boleh berulang di TEMPLATE (nama) berbeda)
            $table->unique(['nama_template', 'id_layanan', 'id_kota'], 'master_tarif_template_layanan_kota_unique');

            $table->index('nama_template');

            $table->foreign('id_layanan')
                ->references('id_layanan')
                ->on('master_layanan')
                ->onDelete('cascade')
                ->onUpdate('restrict');

            $table->foreign('id_kota')
                ->references('id_kota')
                ->on('master_kota_kabupaten')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_tarif');
    }
};