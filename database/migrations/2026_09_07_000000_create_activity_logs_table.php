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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id('id_log');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_type', 50)->default('Admin'); // Admin, Pasien, Tenaga Medis, System
            $table->string('user_name')->nullable();
            $table->string('action', 100); // e.g. CREATE_PROMO, UPDATE_PROFILE, LOGIN, DELETE_ARTIKEL
            $table->text('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
