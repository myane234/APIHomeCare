<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->bigIncrements('id_admin');
            $table->string('foto_profile')->nullable();
            $table->string('nama_lengkap');
            $table->string('email')->unique();
            $table->text('deskripsi')->nullable();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->string('tier_admin');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};