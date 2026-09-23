<?php

namespace App\Console\Commands;

use App\Services\PointService;
use Illuminate\Console\Command;

/**
 * Artisan Command: points:expire
 *
 * Usage:
 *   php artisan points:expire
 *   php artisan points:expire --dry-run   (simulasi tanpa perubahan DB)
 */
class ExpirePoints extends Command
{
    protected $signature = 'points:expire
                            {--dry-run : Simulasi tanpa menyimpan perubahan ke database}';

    protected $description = 'Hanguskan poin pasien yang sudah melewati masa berlaku (expired_at)';

    public function __construct(protected PointService $pointService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        $this->info(' Memeriksa poin yang sudah expired...');

        if ($isDryRun) {
            $this->warn('tidak ada perubahan yang disimpan.');
            $this->previewExpireable();
            return self::SUCCESS;
        }

        $result = $this->pointService->expireAll();

        $this->info("Selesai.");
        $this->table(
            ['Keterangan', 'Jumlah'],
            [
                ['Baris EXPIRED dibuat', $result['expired_rows']],
                ['Pasien terdampak',     $result['affected_patients']],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Tampilkan preview tanpa menyimpan (dry-run).
     */
    private function previewExpireable(): void
    {
        $rows = \App\Models\PointTransaction::expireable()
            ->with('pasien:id_pasien,nama_lengkap')
            ->get(['id', 'id_pasien', 'amount', 'expired_at', 'note']);

        if ($rows->isEmpty()) {
            $this->info('Tidak ada poin yang perlu di-expire saat ini.');
            return;
        }

        $this->info("Ditemukan {$rows->count()} baris EARN yang akan di-expire:");
        $this->table(
            ['ID', 'Pasien', 'Poin', 'Expired At', 'Note'],
            $rows->map(fn ($r) => [
                $r->id,
                $r->pasien?->nama_lengkap ?? $r->id_pasien,
                $r->amount,
                $r->expired_at?->format('Y-m-d H:i'),
                $r->note,
            ])->toArray()
        );
    }
}
