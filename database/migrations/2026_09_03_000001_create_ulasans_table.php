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
        Schema::create('ulasans', function (Blueprint $table) {
            $table->id();
            $table->string('nama_pengulas');
            $table->string('profesi_peran')->nullable()->comment('Contoh: Pasien Homecare, Keluarga Pasien, etc.');
            $table->string('foto')->nullable();
            $table->tinyInteger('rating')->default(5);
            $table->text('komentar');
            $table->unsignedBigInteger('layanan_id')->nullable();
            $table->boolean('is_published')->default(true);
            $table->integer('urutan')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ulasans');
    }
};
