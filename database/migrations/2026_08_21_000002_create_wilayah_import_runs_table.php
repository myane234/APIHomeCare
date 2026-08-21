<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wilayah_import_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('status')->default('queued');
            $table->unsignedInteger('total_provinces')->default(0);
            $table->unsignedInteger('processed_provinces')->default(0);
            $table->unsignedInteger('processed_cities')->default(0);
            $table->unsignedInteger('processed_districts')->default(0);
            $table->unsignedInteger('processed_villages')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wilayah_import_runs');
    }
};
