<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. TABEL MASTER LAYANAN (Hanya Katalog, Tanpa Harga!)
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

        // 2. TABEL MASTER KATEGORI TARIF (BARU! Untuk kondisi: Cito, Malam, VIP)
        Schema::create('master_kategori_tarif', function (Blueprint $table) {
            $table->id('id_kategori_tarif');
            $table->string('nama_kategori')->comment('Ex: Reguler, Malam Hari, VIP');
            $table->boolean('is_default')->default(false)->comment('1 = Tarif Standar Utama');
            $table->timestamps();
        });

        // 3. TABEL MASTER TARIF TRANSPORT
        Schema::create('master_tarif_transport', function (Blueprint $table) {
            $table->id('id_transport');
            $table->unsignedBigInteger('id_kota');
            $table->decimal('tarif_awal', 10, 2)->comment('Tarif minimal/awal transport');
            $table->decimal('tarif_per_kilometer', 10, 2);
            $table->timestamps();

            $table->foreign('id_kota')->references('id_kota')->on('master_kota_kabupaten')->onDelete('cascade');
        });

        // 4. TABEL MASTER BHP
        Schema::create('master_bhp', function (Blueprint $table) {
            $table->id('id_bhp');
    $table->string('nama_bhp');
    $table->enum('tipe_bhp', ['satuan', 'paket'])->default('satuan');
    $table->decimal('harga_modal', 10, 2);
    $table->decimal('harga_jual', 10, 2)->default(0);
    $table->enum('tipe_margin', ['persen', 'nominal'])->default('persen');
    $table->decimal('nilai_margin', 10, 2)->default(0);
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

        // 6. TABEL MASTER KATEGORI PEMBAYARAN
        Schema::create('master_kategori_pembayaran', function (Blueprint $table) {
            $table->id('id_kategori_pembayaran');
            $table->string('nama_kategori')->comment('Ex: Bank Transfer, E-Wallet, QRIS');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 7. TABEL MASTER METODE PEMBAYARAN
        Schema::create('master_metode_pembayaran', function (Blueprint $table) {
            $table->id('id_metode');
            $table->unsignedBigInteger('id_kategori_pembayaran');
            $table->string('nama_metode');
            $table->enum('tipe_potongan', ['nominal', 'persen']);
            $table->decimal('nilai_potongan', 10, 2);
            $table->string('logo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('id_kategori_pembayaran')->references('id_kategori_pembayaran')->on('master_kategori_pembayaran')->onDelete('cascade');
        });

        // 8. TABEL MASTER KOMPONEN BIAYA
        Schema::create('master_komponen_biaya', function (Blueprint $table) {
            $table->id('id_komponen');
            $table->string('nama_komponen');
            $table->enum('tipe_komponen', ['pajak', 'admin_aplikasi', 'lainnya']);
            $table->enum('jenis_nilai', ['nominal', 'persen']);
            $table->decimal('nilai', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_komponen_biaya');
        Schema::dropIfExists('master_metode_pembayaran');
        Schema::dropIfExists('master_kategori_pembayaran');
        Schema::dropIfExists('mapping_layanan_bhp');
        Schema::dropIfExists('master_bhp');
        Schema::dropIfExists('master_tarif_transport');
        Schema::dropIfExists('master_kategori_tarif');
        Schema::dropIfExists('master_layanan');
    }
};