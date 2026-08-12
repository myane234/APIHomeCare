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
        Schema::create('master_kelurahan', function (Blueprint $table) {
            $table->string('id_kelurahan')->primary();
            $table->string('id_kecamatan');
            $table->string('nama_kelurahan');
            $table->timestamps();

            $table->foreign('id_kecamatan')
                  ->references('id_kecamatan')
                  ->on('master_kecamatan')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_kelurahan');
    }
};
