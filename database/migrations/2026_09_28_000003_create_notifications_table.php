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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // Penerima notifikasi
            $table->unsignedBigInteger('user_id')
                ->comment('ID user penerima (id_user dari tabel users / pasien / tenaga_medis)');
            $table->string('user_role')
                ->comment('Role penerima: pasien, nakes, admin');

            // Relasi ke template (nullable – bisa dikirim manual tanpa template)
            $table->foreignId('template_id')
                ->nullable()
                ->constrained('notification_templates')
                ->nullOnDelete();

            // Konten notifikasi (hasil parse placeholder dari template)
            $table->string('title');
            $table->text('body');
            $table->string('action_url')->nullable();

            // Data tambahan (metadata / payload JSON bebas)
            $table->json('data')->nullable()
                ->comment('Payload JSON tambahan, misal booking_id, transaksi_id, dll');

            // Status baca
            $table->boolean('is_read')->default(false)->index();
            $table->timestamp('read_at')->nullable();

            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            // Composite index untuk query inbox (filter user + unread + urutan terbaru)
            $table->index(['user_id', 'is_read', 'created_at'], 'notifications_user_read_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
