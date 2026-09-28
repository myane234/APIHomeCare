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
        Schema::create('notification_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')
                ->constrained('notification_templates')
                ->cascadeOnDelete();
            $table->string('cron_expression')->nullable()
                ->comment('Cron expression untuk jadwal pengiriman, misal: 0 9 * * 1');
            $table->string('delay_unit')->nullable()
                ->comment('Satuan delay: minutes, hours, days');
            $table->unsignedInteger('delay_value')->nullable()
                ->comment('Nilai delay setelah trigger, misal: 30 (menit)');
            $table->boolean('is_active')->default(true);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_schedules');
    }
};
