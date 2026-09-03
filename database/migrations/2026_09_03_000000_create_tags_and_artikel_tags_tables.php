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
        Schema::create('tags', function (Blueprint $table) {
            $table->id('id_tag');
            $table->string('nama_tag')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('artikel_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artikel_id')->constrained('artikels')->onDelete('cascade');
            $table->foreignId('tag_id')->constrained('tags', 'id_tag')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['artikel_id', 'tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artikel_tags');
        Schema::dropIfExists('tags');
    }
};
