<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('promos', 'id_layanan')) {
            Schema::table('promos', function (Blueprint $table) {
                $table->unsignedBigInteger('id_layanan')->nullable()->after('id_promo');
            });
        }

        foreach (Schema::getConnection()->table('promo_layanan')->get() as $promoLayanan) {
            Schema::getConnection()->table('promos')
                ->where('id_promo', $promoLayanan->id_promo)
                ->whereNull('id_layanan')
                ->update(['id_layanan' => $promoLayanan->id_layanan]);
        }

        Schema::table('promos', function (Blueprint $table) {

            $foreignKeys = collect(Schema::getForeignKeys('promos'))->pluck('name');
            $fkName = 'promos_id_layanan_foreign';
            if (!$foreignKeys->contains($fkName)) {
                $table->foreign('id_layanan')->references('id_layanan')->on('master_layanan')->cascadeOnDelete();
            }


            if (Schema::hasColumn('promos', 'nama_paket')) {
                $table->dropColumn('nama_paket');
            }
        });
    }

    public function down(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            if (!Schema::hasColumn('promos', 'nama_paket')) {
                $table->string('nama_paket')->nullable();
            }

            $foreignKeys = collect(Schema::getForeignKeys('promos'))->pluck('name');
            if ($foreignKeys->contains('promos_id_layanan_foreign')) {
                $table->dropForeign(['id_layanan']);
            }

            if (Schema::hasColumn('promos', 'id_layanan')) {
                $table->dropColumn('id_layanan');
            }
        });
    }
};