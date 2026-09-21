<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->string('tipe_diskon')->default('persen')->after('deskripsi');
            $table->decimal('nilai_diskon', 12, 2)->nullable()->after('tipe_diskon');
        });

        Schema::getConnection()->table('promos')->update([
            'nilai_diskon' => Schema::getConnection()->raw('diskon_persen'),
        ]);

        Schema::table('promos', function (Blueprint $table) {
            $table->dropColumn('diskon_persen');
        });
    }

    public function down(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->decimal('diskon_persen', 5, 2)->nullable();
        });

        Schema::getConnection()->table('promos')->where('tipe_diskon', 'persen')->update([
            'diskon_persen' => Schema::getConnection()->raw('nilai_diskon'),
        ]);

        Schema::table('promos', function (Blueprint $table) {
            $table->dropColumn(['tipe_diskon', 'nilai_diskon']);
        });
    }
};