<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('master_tarif', function (Blueprint $table) {
            $table->id('id_master_tarif');
            $table->string('nama_template');
            $table->unsignedBigInteger('id_layanan');
            $table->unsignedBigInteger('id_provinsi')->nullable();
            $table->unsignedBigInteger('id_kota')->nullable()->comment('Null = Berlaku Nasional');

            $table->decimal('tarif_pasien', 12, 2)->default(0);
            $table->decimal('transport_base_fare', 10, 2)->default(0);
            $table->decimal('transport_per_km', 10, 2)->default(0);
            $table->enum('fee_nakes_tipe', ['nominal', 'persen'])->default('persen');
            $table->decimal('fee_nakes_nilai', 12, 2)->default(80);
            $table->decimal('fee_nakes_nominal', 12, 2)->default(0);
            $table->decimal('fee_platform_nominal', 12, 2)->default(0);
            $table->decimal('persen_ppn', 5, 2)->default(0);
            $table->decimal('total_ppn', 10, 2)->default(0);
            $table->decimal('total_biaya_admin', 10, 2)->default(0);
            $table->decimal('total_biaya_lainnya', 10, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total_tarif_final', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['nama_template', 'id_layanan', 'id_kota'], 'master_tarif_template_layanan_kota_unique');
            $table->index('nama_template');

            $table->foreign('id_layanan')->references('id_layanan')->on('master_layanan')->onDelete('cascade');
            $table->foreign('id_provinsi')->references('id_provinsi')->on('master_provinsi')->nullOnDelete();
            $table->foreign('id_kota')->references('id_kota')->on('master_kota_kabupaten')->onDelete('cascade');
        });

        Schema::create('master_tarif_layanan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_master_tarif');
            $table->unsignedBigInteger('id_layanan');
            $table->timestamps();

            $table->unique(['id_master_tarif', 'id_layanan']);
            $table->foreign('id_master_tarif')->references('id_master_tarif')->on('master_tarif')->cascadeOnDelete();
            $table->foreign('id_layanan')->references('id_layanan')->on('master_layanan')->cascadeOnDelete();
        });

        Schema::create('master_tarif_komponen', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_master_tarif');
            $table->unsignedBigInteger('id_komponen');
            $table->timestamps();

            $table->unique(['id_master_tarif', 'id_komponen']);
            $table->foreign('id_master_tarif')->references('id_master_tarif')->on('master_tarif')->cascadeOnDelete();
            $table->foreign('id_komponen')->references('id_komponen')->on('master_komponen_biaya')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_tarif_komponen');
        Schema::dropIfExists('master_tarif_layanan');
        Schema::dropIfExists('master_tarif');
    }
};