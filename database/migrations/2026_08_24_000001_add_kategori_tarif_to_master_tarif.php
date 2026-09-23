<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('master_tarif', function (Blueprint $table) {
            $table->unsignedBigInteger('id_kategori_tarif')->nullable()->after('nama_template');
            $table->foreign('id_kategori_tarif')
                ->references('id_kategori_tarif')
                ->on('master_kategori_tarif')
                ->nullOnDelete();
        });

        $reguler = DB::table('master_kategori_tarif')
            ->where('nama_kategori', 'REGULER')
            ->first();

        if (!$reguler) {
            $id = DB::table('master_kategori_tarif')->insertGetId([
                'nama_kategori' => 'REGULER',
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $reguler = (object) ['id_kategori_tarif' => $id];
        }

        DB::table('master_tarif')
            ->whereNull('id_kategori_tarif')
            ->update(['id_kategori_tarif' => $reguler->id_kategori_tarif]);

        Schema::table('master_tarif', function (Blueprint $table) {
            $table->dropUnique('master_tarif_template_layanan_kota_unique');
            $table->unique(
                ['nama_template', 'id_kategori_tarif', 'id_layanan', 'id_kota'],
                'master_tarif_template_kategori_layanan_kota_unique'
            );
        });

        $cito = DB::table('master_kategori_tarif')
            ->where('nama_kategori', 'CITO')
            ->first();

        if (!$cito) {
            DB::table('master_kategori_tarif')->insert([
                'nama_kategori' => 'CITO',
                'is_default' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('master_tarif', function (Blueprint $table) {
            $table->dropUnique('master_tarif_template_kategori_layanan_kota_unique');
            $table->dropForeign(['id_kategori_tarif']);
            $table->dropColumn('id_kategori_tarif');
            $table->unique(
                ['nama_template', 'id_layanan', 'id_kota'],
                'master_tarif_template_layanan_kota_unique'
            );
        });

        DB::table('master_kategori_tarif')
            ->whereIn('nama_kategori', ['REGULER', 'CITO'])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('master_tarif')
                    ->whereColumn('master_tarif.id_kategori_tarif', 'master_kategori_tarif.id_kategori_tarif');
            })
            ->delete();
    }
};
