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
        Schema::table('ulasans', function (Blueprint $table) {
            $table->string('email')->nullable()->after('nama_pengulas');
            $table->unsignedBigInteger('id_user')->nullable()->after('id');

            $table->foreign('id_user')
                  ->references('id_user')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ulasans', function (Blueprint $table) {
            $table->dropForeign(['id_user']);
            $table->dropColumn(['email', 'id_user']);
        });
    }
};
