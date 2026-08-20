<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_bank', function (Blueprint $table) {
            if (!Schema::hasColumn('master_bank', 'gambar')) {
                $table->string('gambar')->nullable()->after('kode_bank');
            }

            if (!Schema::hasColumn('master_bank', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('is_active');
            }

            if (!Schema::hasColumn('master_bank', 'deleted_by')) {
                $table->unsignedBigInteger('deleted_by')->nullable()->after('created_by');
            }

            if (!Schema::hasColumn('master_bank', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if (Schema::hasColumn('master_bank', 'logo_bank') && Schema::hasColumn('master_bank', 'gambar')) {
            DB::statement('UPDATE master_bank SET gambar = logo_bank WHERE gambar IS NULL');
        }
    }

    public function down(): void
    {
        Schema::table('master_bank', function (Blueprint $table) {
            if (Schema::hasColumn('master_bank', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            $columns = array_filter([
                Schema::hasColumn('master_bank', 'gambar') ? 'gambar' : null,
                Schema::hasColumn('master_bank', 'created_by') ? 'created_by' : null,
                Schema::hasColumn('master_bank', 'deleted_by') ? 'deleted_by' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};