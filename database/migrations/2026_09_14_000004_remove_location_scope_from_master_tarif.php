<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::table('master_tarif')->update([
            'id_provinsi' => null,
            'id_kota' => null,
        ]);

        $duplicateGroups = DB::table('master_tarif')
            ->select('nama_template', 'id_kategori_tarif', 'id_layanan', DB::raw('MIN(id_master_tarif) as keep_id'))
            ->groupBy('nama_template', 'id_kategori_tarif', 'id_layanan')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateGroups as $group) {
            DB::table('master_tarif')
                ->where('nama_template', $group->nama_template)
                ->where('id_kategori_tarif', $group->id_kategori_tarif)
                ->where('id_layanan', $group->id_layanan)
                ->where('id_master_tarif', '!=', $group->keep_id)
                ->delete();
        }

        Schema::table('master_tarif', function (Blueprint $table) {
            $table->dropUnique('master_tarif_template_kategori_layanan_kota_unique');
            $table->dropForeign(['id_provinsi']);
            $table->dropForeign(['id_kota']);
            $table->dropColumn(['id_provinsi', 'id_kota']);
            $table->unique(
                ['nama_template', 'id_kategori_tarif', 'id_layanan'],
                'master_tarif_template_kategori_layanan_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('master_tarif', function (Blueprint $table) {
            $table->unsignedBigInteger('id_provinsi')->nullable();
            $table->unsignedBigInteger('id_kota')->nullable();
            $table->foreign('id_provinsi')->references('id_provinsi')->on('master_provinsi')->nullOnDelete();
            $table->foreign('id_kota')->references('id_kota')->on('master_kota_kabupaten')->cascadeOnDelete();
            $table->dropUnique('master_tarif_template_kategori_layanan_unique');
            $table->unique(
                ['nama_template', 'id_kategori_tarif', 'id_layanan', 'id_kota'],
                'master_tarif_template_kategori_layanan_kota_unique'
            );
        });
    }
};