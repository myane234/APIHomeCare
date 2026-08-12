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
        Schema::create('admins', function (Blueprint $table) {
            $table->bigIncrements('id_admin');
            $table->unsignedBigInteger('id_user');
            $table->string('nama_lengkap');
            $table->string('tier_admin');
            $table->timestamps();

            $table->foreign('id_user', 'fk_admins_user_id')
                  ->references('id_user')
                  ->on('users')
                  ->onUpdate('restrict')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};