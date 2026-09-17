<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->unsignedBigInteger('id_layanan')->nullable()->after('id_promo');
        });

        foreach (Schema::getConnection()->table('promo_layanan')->get() as $promoLayanan) {
            Schema::getConnection()->table('promos')
                ->where('id_promo', $promoLayanan->id_promo)
                ->whereNull('id_layanan')
                ->update(['id_layanan' => $promoLayanan->id_layanan]);
        }

        Schema::table('promos', function (Blueprint $table) {
            $table->foreign('id_layanan')->references('id_layanan')->on('master_layanan')->cascadeOnDelete();
            $table->dropColumn('nama_paket');
        });
    }

    public function down(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->string('nama_paket')->nullable();
            $table->dropForeign(['id_layanan']);
            $table->dropColumn('id_layanan');
        });
    }
};