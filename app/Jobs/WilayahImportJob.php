<?php

namespace App\Jobs;

use App\Models\WilayahImportLog;
use App\Models\WilayahImportRun;
use App\Services\WilayahImportService;
use Database\Seeders\WilayahSeeder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class WilayahImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200;
    public int $tries = 1;

    public function __construct(public string $runId)
    {
    }

    public function handle(WilayahImportService $importService): void
    {
        $run = WilayahImportRun::findOrFail($this->runId);
        $run->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
        $this->log($run, 'info', 'Import wilayah dimulai.');

        try {
            app(WilayahSeeder::class)->run($importService, function (string $event, array $data) use ($run): void {
                match ($event) {
                    'source_loaded' => $this->sourceLoaded($run, $data),
                    'province_started' => $this->provinceStarted($run, $data),
                    'city_processed' => $this->cityProcessed($run, $data),
                    default => null,
                };
            });

            $run->refresh();
            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
            ]);
            $this->log($run, 'info', 'Import seluruh wilayah selesai.');
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'finished_at' => now(),
            ]);
            $this->log($run, 'error', 'Import gagal: ' . $exception->getMessage());
            throw $exception;
        }
    }

    private function sourceLoaded(WilayahImportRun $run, array $data): void
    {
        $run->update(['total_provinces' => $data['total_provinces']]);
        $this->log($run, 'info', "Sumber API terbaca: {$data['total_provinces']} provinsi.");
    }

    private function provinceStarted(WilayahImportRun $run, array $data): void
    {
        $province = $data['province'];
        $run->increment('processed_provinces');
        $this->log($run, 'info', "Memproses provinsi: {$province['name']}.");
    }

    private function cityProcessed(WilayahImportRun $run, array $data): void
    {
        $city = $data['city'];
        $districts = $city['districts'] ?? [];
        $villages = array_sum(array_map(
            static fn (array $district): int => count($district['villages'] ?? []),
            $districts
        ));

        $run->increment('processed_cities');
        $run->increment('processed_districts', count($districts));
        $run->increment('processed_villages', $villages);
        $this->log($run, 'info', "Selesai: {$city['name']} ({$villages} kelurahan/desa).");
    }

    private function log(WilayahImportRun $run, string $level, string $message): void
    {
        WilayahImportLog::create([
            'run_id' => $run->id,
            'level' => $level,
            'message' => $message,
        ]);
    }
}
