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
        Schema::table('admin_tiers', function (Blueprint $table) {
            if (!Schema::hasColumn('admin_tiers', 'permissions')) {
                $table->json('permissions')->nullable()->after('deskripsi');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_tiers', function (Blueprint $table) {
            if (Schema::hasColumn('admin_tiers', 'permissions')) {
                $table->dropColumn('permissions');
            }
        });
    }
};
