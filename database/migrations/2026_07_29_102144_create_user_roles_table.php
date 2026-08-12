<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_user');
            $table->string('nama_role');
            $table->timestamps();
            
            $table->foreign('id_user', 'fk_user_roles_user')
                  ->references('id_user')
                  ->on('users')
                  ->cascadeOnDelete();

            $table->foreign('nama_role', 'fk_user_roles_role')
                  ->references('nama_role')
                  ->on('roles')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};