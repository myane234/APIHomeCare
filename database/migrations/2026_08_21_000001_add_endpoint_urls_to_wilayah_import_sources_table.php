<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wilayah_import_sources', function (Blueprint $table) {
            $table->text('provinces_url')->nullable()->after('base_url');
            $table->text('regencies_url')->nullable()->after('provinces_url');
            $table->text('districts_url')->nullable()->after('regencies_url');
            $table->text('villages_url')->nullable()->after('districts_url');
        });
    }

    public function down(): void
    {
        Schema::table('wilayah_import_sources', function (Blueprint $table) {
            $table->dropColumn([
                'provinces_url',
                'regencies_url',
                'districts_url',
                'villages_url',
            ]);
        });
    }
};