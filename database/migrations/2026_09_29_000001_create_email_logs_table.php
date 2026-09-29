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
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('recipient_email')->index();
            $table->string('recipient_name')->nullable();
            $table->string('subject');
            $table->string('trigger_type')->default('manual')->index()
                ->comment('manual, booking_selesai_invoice, booking_reminder, post_treatment_followup, promo, broadcast');
            $table->foreignId('template_id')->nullable()->constrained('notification_templates')->nullOnDelete();
            $table->unsignedBigInteger('reference_id')->nullable()
                ->comment('ID relasi seperti id_booking atau id_transaksi');
            $table->string('reference_type')->nullable()
                ->comment('Model relasi misal: booking, transaksi');
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable()->comment('Data variabel / parameter saat email dikirim');
            $table->timestamp('sent_at')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
