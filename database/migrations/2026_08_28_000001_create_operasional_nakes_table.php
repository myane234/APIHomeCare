<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operasional_nakes', function (Blueprint $table) {
            $table->id('id_operasional_nakes');
            $table->unsignedBigInteger('id_tenaga_medis')->index();
            $table->unsignedBigInteger('id_wilayah_layanan')->index();
            $table->json('kategori_layanan');
            $table->json('waktu_layanan');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operasional_nakes');
    }
};
