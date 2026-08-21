<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wilayah_import_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('run_id');
            $table->string('level')->default('info');
            $table->text('message');
            $table->timestamps();
            $table->index('run_id');
            $table->foreign('run_id')->references('id')->on('wilayah_import_runs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wilayah_import_logs');
    }
};
