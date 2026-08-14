<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_bank', function (Blueprint $table) {
            $table->bigIncrements('id_bank');
            $table->string('nama_bank');
            $table->string('kode_bank', 10)->nullable();
            $table->string('logo_bank')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_bank');
    }
};
