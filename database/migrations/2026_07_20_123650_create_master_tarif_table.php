<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('master_tarif', function (Blueprint $table) {
            $table->id('id_tarif');
            
            // Relasi ke Layanan dan Skenario (Kategori Tarif)
            $table->unsignedBigInteger('id_layanan');
            $table->unsignedBigInteger('id_kategori_tarif');
            $table->unsignedBigInteger('id_kota')->nullable()->comment('Null = Berlaku Nasional');

            // Inti Harga & Porsi Nakes
            $table->decimal('harga_dasar', 12, 2)->comment('Harga jual murni ke pasien');
            $table->integer('persentase_nakes')->default(80)->comment('Porsi pendapatan nakes (%)');
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Constraint: 1 Layanan + 1 Skenario + 1 Kota = Tidak boleh duplikat (unik)
            $table->unique(['id_layanan', 'id_kategori_tarif', 'id_kota'], 'master_tarif_unik');

            // Foreign Keys
            $table->foreign('id_layanan')->references('id_layanan')->on('master_layanan')->onDelete('cascade');
            $table->foreign('id_kategori_tarif')->references('id_kategori_tarif')->on('master_kategori_tarif')->onDelete('cascade');
            $table->foreign('id_kota')->references('id_kota')->on('master_kota_kabupaten')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_tarif');
    }
};