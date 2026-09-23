<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menambahkan kolom saldo poin ke tabel pasiens:
     *   - points_balance : Saldo poin aktif pasien saat ini. Default 0.
     */
    public function up(): void
    {
        Schema::table('pasiens', function (Blueprint $table) {
            $table->unsignedInteger('points_balance')
                  ->default(0)
                  ->comment('Saldo poin aktif pasien')
                  ->after('avatar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pasiens', function (Blueprint $table) {
            $table->dropColumn('points_balance');
        });
    }
};
