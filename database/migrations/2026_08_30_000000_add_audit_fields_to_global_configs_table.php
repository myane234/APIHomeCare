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
        Schema::table('global_configs', function (Blueprint $table) {
            if (!Schema::hasColumn('global_configs', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('maintenance_mode');
            }

            if (!Schema::hasColumn('global_configs', 'deleted_by')) {
                $table->unsignedBigInteger('deleted_by')->nullable()->after('created_by');
            }

            if (!Schema::hasColumn('global_configs', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('global_configs', function (Blueprint $table) {
            if (Schema::hasColumn('global_configs', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            $columns = array_filter([
                Schema::hasColumn('global_configs', 'created_by') ? 'created_by' : null,
                Schema::hasColumn('global_configs', 'deleted_by') ? 'deleted_by' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
