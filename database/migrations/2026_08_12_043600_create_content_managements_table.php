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
        Schema::create('content_managements', function (Blueprint $table) {
            $table->id();
            
            // Home Content
            $table->string('home_banner')->nullable();
            $table->text('home_text_banner')->nullable();
            $table->text('home_description')->nullable();

            // Tentang Kami Content
            $table->string('about_banner')->nullable();
            $table->text('about_text_banner')->nullable();
            $table->text('about_description_text')->nullable();
            $table->string('about_description_image')->nullable();
            $table->text('visi_misi')->nullable();
            $table->text('cara_kerja')->nullable();
            $table->text('wilayah_layanan')->nullable();
            $table->text('komitmen')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_managements');
    }
};
