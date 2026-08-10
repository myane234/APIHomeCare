<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. TABEL MASTER LAYANAN
        Schema::create('master_layanan', function (Blueprint $table) {
            $table->id('id_layanan');
            $table->unsignedBigInteger('id_kategori_layanan');
            $table->string('nama_layanan');
            $table->text('deskripsi_layanan')->nullable();
            $table->string('foto_layanan')->default('layanan/default.jpg');
            $table->enum('tipe_layanan', ['durasi', 'tindakan']);
            $table->integer('durasi_menit')->nullable();
            $table->timestamps();

            $table->foreign('id_kategori_layanan')
                ->references('id_kategori_layanan')
                ->on('kategori_layanans')
                ->onDelete('cascade');
        });

        // 2. TABEL MASTER TARIF LAYANAN (Core Jasa)
        // Schema::create('master_tarif_layanan', function (Blueprint $table) {
        //     $table->id('id_tarif');
        //     $table->unsignedBigInteger('id_layanan');


        //     $table->unsignedBigInteger('id_kota')->nullable()->comment('Null = Nasional');

        //     $table->decimal('tarif_pasien', 12, 2);
        //     $table->integer('potongan_persen')->default(20);
        //     $table->boolean('is_active')->default(true);
        //     $table->timestamps();

        // 2. TABEL MASTER TARIF LAYANAN (Core Jasa - Blueprint Pembagian)
        Schema::create('master_tarif_layanan', function (Blueprint $table) {
            $table->id('id_tarif');
            $table->unsignedBigInteger('id_layanan');
            $table->unsignedBigInteger('id_kota')->nullable()->comment('Null = Nasional');

            $table->decimal('tarif_dasar', 12, 2)->comment('Harga dasar layanan');
            $table->integer('persentase_nakes')->default(80)->comment('Porsi pendapatan nakes (%)');
            $table->integer('persentase_homecare')->default(20)->comment('Porsi margin homecare (%)');

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            //     $table->foreign('id_layanan')
            //         ->references('id_layanan')
            //         ->on('master_layanan')
            //         ->onDelete('cascade');

            //     $table->foreign('id_kota')
            //         ->references('id_kota')
            //         ->on('master_kota_kabupaten')
            //         ->onUpdate('cascade')
            //         ->onDelete('cascade');
            // });
            $table->foreign('id_layanan')->references('id_layanan')->on('master_layanan')->onDelete('cascade');
            $table->foreign('id_kota')->references('id_kota')->on('master_kota_kabupaten')->onDelete('cascade');
        });

        // 3. TABEL MASTER TARIF TRANSPORT
        Schema::create('master_tarif_transport', function (Blueprint $table) {
            $table->id('id_transport');
            $table->unsignedBigInteger('id_kota');

            $table->decimal('tarif_awal', 10, 2)->comment('Tarif minimal/awal transport');
            $table->decimal('tarif_per_kilometer', 10, 2);

            $table->foreign('id_kota')
                ->references('id_kota')
                ->on('master_kota_kabupaten')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->timestamps();
        });

        // 4. TABEL MASTER BHP
        Schema::create('master_bhp', function (Blueprint $table) {
            $table->id('id_bhp');
            $table->string('nama_bhp');
            $table->enum('tipe_bhp', ['satuan', 'paket']);

            $table->decimal('harga_modal', 10, 2);
            $table->decimal('harga_jual', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 5. TABEL MAPPING LAYANAN BHP
        Schema::create('mapping_layanan_bhp', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_layanan');
            $table->unsignedBigInteger('id_bhp');

            $table->integer('qty_default')->default(1);
            $table->boolean('is_mandatory')->default(true);
            $table->timestamps();

            $table->foreign('id_layanan')->references('id_layanan')->on('master_layanan')->onDelete('cascade');
            $table->foreign('id_bhp')->references('id_bhp')->on('master_bhp')->onDelete('cascade');
        });

        // 6. TABEL MASTER KATEGORI PEMBAYARAN (Baru)
        Schema::create('master_kategori_pembayaran', function (Blueprint $table) {
            $table->id('id_kategori_pembayaran');
            $table->string('nama_kategori')->comment('Ex: Bank Transfer, E-Wallet, QRIS, Paylater, Cash');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 7. TABEL MASTER METODE PEMBAYARAN (Payment Gateway)
        Schema::create('master_metode_pembayaran', function (Blueprint $table) {
            $table->id('id_metode');
            $table->unsignedBigInteger('id_kategori_pembayaran');

            $table->string('nama_metode')->comment('Ex: BCA VA, ShopeePay, Mandiri VA');
            $table->enum('tipe_potongan', ['nominal', 'persen']);
            $table->decimal('nilai_potongan', 10, 2);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('id_kategori_pembayaran')
                ->references('id_kategori_pembayaran')
                ->on('master_kategori_pembayaran')
                ->onDelete('cascade');
        });

        // 8. TABEL MASTER KOMPONEN BIAYA (PPN, Biaya Aplikasi, Asuransi)
        Schema::create('master_komponen_biaya', function (Blueprint $table) {
            $table->id('id_komponen');
            $table->string('nama_komponen')->comment('Ex: PPN 11%, Biaya Layanan Aplikasi, Asuransi Nakes');
            $table->enum('tipe_komponen', ['pajak', 'admin_aplikasi', 'asuransi', 'lainnya']);

            $table->enum('jenis_nilai', ['nominal', 'persen']);
            $table->decimal('nilai', 10, 2)->comment('Ex: 11 untuk PPN, 2000 untuk admin app');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 9. TABEL MASTER TARIF (BUNDLING / CETAKAN UTAMA)
        // Schema::create('master_tarif', function (Blueprint $table) {
        //     $table->id('id_master_tarif');
        //     $table->string('nama_tarif')->comment('Contoh: Reguler, VIP, Khusus');
        //     $table->unsignedBigInteger('id_tarif_layanan');
        //     $table->unsignedBigInteger('id_tarif_transport')->nullable();

        //     $table->boolean('is_active')->default(true);
        //     $table->timestamps();

        //     $table->foreign('id_tarif_layanan')->references('id_tarif')->on('master_tarif_layanan')->onDelete('cascade');
        //     $table->foreign('id_tarif_transport')->references('id_transport')->on('master_tarif_transport')->onDelete('cascade');
        // });
    }

    public function down(): void
    {
        // Schema::dropIfExists('master_tarif');
        Schema::dropIfExists('master_komponen_biaya');
        Schema::dropIfExists('master_metode_pembayaran');
        Schema::dropIfExists('master_kategori_pembayaran');
        Schema::dropIfExists('mapping_layanan_bhp');
        Schema::dropIfExists('master_bhp');
        Schema::dropIfExists('master_tarif_transport');
        Schema::dropIfExists('master_tarif_layanan');
        Schema::dropIfExists('master_layanan');
    }
};