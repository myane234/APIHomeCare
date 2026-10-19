<?php

namespace App\Services;

use App\Events\InAppNotificationEvent;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * NotificationService
 *
 * Mengelola pengiriman in-app notification:
 *   - send()        : Kirim notifikasi ke satu user, opsional dari template
 *   - sendBulk()    : Kirim notifikasi ke banyak user sekaligus (bulk insert)
 *   - sendByCode()  : Kirim berdasarkan kode template dengan data placeholder
 *   - broadcast()   : Dispatch WebSocket event ke klien via Laravel Reverb
 */
class NotificationService
{
    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Kirim notifikasi ke satu user.
     *
     * @param  int         $userId     ID user penerima
     * @param  string      $userRole   Role penerima: pasien | nakes | admin
     * @param  string      $title      Judul notifikasi (sudah di-parse)
     * @param  string      $body       Isi notifikasi (sudah di-parse)
     * @param  array       $options    Opsi tambahan: template_id, action_url, data
     * @return Notification
     */
    public function send(
        int $userId,
        string $userRole,
        string $title,
        string $body,
        array $options = []
    ): Notification {
        $notification = Notification::create([
            'user_id'    => $userId,
            'user_role'  => $userRole,
            'template_id'=> $options['template_id'] ?? null,
            'title'      => $title,
            'body'       => $body,
            'action_url' => $options['action_url'] ?? null,
            'data'       => $options['data'] ?? null,
            'is_read'    => false,
            'created_by' => $options['created_by'] ?? 'system',
            'updated_by' => $options['created_by'] ?? 'system',
        ]);

        $this->broadcast($notification);

        return $notification;
    }

    /**
     * Kirim notifikasi ke banyak user sekaligus (bulk insert).
     *
     * Lebih efisien dibanding memanggil send() satu per satu karena menggunakan
     * single INSERT query. Event broadcast tetap dikirim per notifikasi.
     *
     * @param  array  $recipients  Array of ['user_id' => int, 'user_role' => string]
     * @param  string $title
     * @param  string $body
     * @param  array  $options     Opsi sama seperti send()
     * @return int    Jumlah notifikasi yang berhasil dibuat
     */
    public function sendBulk(
        array $recipients,
        string $title,
        string $body,
        array $options = []
    ): int {
        if (empty($recipients)) {
            return 0;
        }

        $now  = now();
        $rows = [];

        foreach ($recipients as $recipient) {
            $rows[] = [
                'user_id'    => $recipient['user_id'],
                'user_role'  => $recipient['user_role'],
                'template_id'=> $options['template_id'] ?? null,
                'title'      => $title,
                'body'       => $body,
                'action_url' => $options['action_url'] ?? null,
                'data'       => isset($options['data']) ? json_encode($options['data']) : null,
                'is_read'    => false,
                'created_by' => $options['created_by'] ?? 'system',
                'updated_by' => $options['created_by'] ?? 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Chunk bulk insert agar tidak membebani memory
        $chunks   = array_chunk($rows, 500);
        $inserted = 0;

        DB::transaction(function () use ($chunks, $recipients, $title, $body, $options, &$inserted) {
            foreach ($chunks as $chunk) {
                Notification::insert($chunk);
                $inserted += count($chunk);
            }
        });

        // Broadcast per penerima setelah insert selesai
        // Ambil ID yang baru diinsert untuk broadcast
        try {
            $latestIds = Notification::where('created_at', '>=', now()->subSeconds(5))
                ->whereIn('user_id', array_column($recipients, 'user_id'))
                ->orderByDesc('id')
                ->limit(count($recipients))
                ->get();

            foreach ($latestIds as $notification) {
                $this->broadcast($notification);
            }
        } catch (\Throwable $e) {
            Log::warning('NotificationService@sendBulk broadcast partial failure: ' . $e->getMessage());
        }

        return $inserted;
    }

    /**
     * Kirim notifikasi berdasarkan kode template.
     *
     * Placeholder dalam title/body template ditulis sebagai {variable_name}.
     * Nilai placeholder dikirim via $variables: ['variable_name' => 'nilai'].
     *
     * @param  string  $templateCode  Kode unik template
     * @param  int     $userId        ID user penerima
     * @param  string  $userRole      Role penerima
     * @param  array   $variables     Placeholder values, misal: ['nama' => 'John', 'booking_id' => 'BK-001']
     * @param  array   $options       Opsi tambahan: data (JSON), created_by
     * @return Notification|null      null jika template tidak aktif
     */
    public function sendByCode(
        string $templateCode,
        int $userId,
        string $userRole,
        array $variables = [],
        array $options = []
    ): ?Notification {
        $template = $this->findTemplateForRole($templateCode, $userRole);

        if (!$template) {
            Log::warning("NotificationService: template '{$templateCode}' (role: {$userRole}) tidak ditemukan atau tidak aktif.");
            return null;
        }

        $title = $this->parsePlaceholders($template->title, $variables);
        $body  = $this->parsePlaceholders($template->body, $variables);

        $actionUrl = $options['action_url'] ?? $template->action_url;
        if (!empty($actionUrl)) {
            $actionUrl = $this->parsePlaceholders($actionUrl, $variables);
        }

        $mergedOptions = array_merge([
            'template_id' => $template->id,
        ], $options, [
            'action_url' => $actionUrl,
        ]);

        return $this->send($userId, $userRole, $title, $body, $mergedOptions);
    }

    /**
     * Kirim bulk berdasarkan kode template dengan variable berbeda per user.
     *
     * @param  string  $templateCode
     * @param  array   $recipients   Array of ['user_id', 'user_role', 'variables' => []]
     * @param  array   $options
     * @return int     Jumlah notifikasi terkirim
     */
    public function sendBulkByCode(
        string $templateCode,
        array $recipients,
        array $options = []
    ): int {
        if (empty($recipients)) {
            return 0;
        }

        $now  = now();
        $rows = [];
        $loadedTemplates = [];

        foreach ($recipients as $recipient) {
            $userRole = $recipient['user_role'] ?? 'pasien';
            if (!array_key_exists($userRole, $loadedTemplates)) {
                $loadedTemplates[$userRole] = $this->findTemplateForRole($templateCode, $userRole);
            }

            $template = $loadedTemplates[$userRole];
            if (!$template) {
                Log::warning("NotificationService: template '{$templateCode}' (role: {$userRole}) tidak ditemukan atau tidak aktif.");
                continue;
            }

            $variables    = $recipient['variables'] ?? [];
            $rawActionUrl = $options['action_url'] ?? $template->action_url;
            $actionUrl    = !empty($rawActionUrl) ? $this->parsePlaceholders($rawActionUrl, $variables) : null;

            $rows[] = [
                'user_id'    => $recipient['user_id'],
                'user_role'  => $recipient['user_role'],
                'template_id'=> $template->id,
                'title'      => $this->parsePlaceholders($template->title, $variables),
                'body'       => $this->parsePlaceholders($template->body, $variables),
                'action_url' => $actionUrl,
                'data'       => isset($options['data']) ? json_encode($options['data']) : null,
                'is_read'    => false,
                'created_by' => $options['created_by'] ?? 'system',
                'updated_by' => $options['created_by'] ?? 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($rows)) {
            return 0;
        }

        $inserted = 0;
        $chunks   = array_chunk($rows, 500);

        DB::transaction(function () use ($chunks, &$inserted) {
            foreach ($chunks as $chunk) {
                Notification::insert($chunk);
                $inserted += count($chunk);
            }
        });

        // Broadcast per penerima
        try {
            $templateIds = array_filter(array_map(fn($t) => $t?->id, $loadedTemplates));
            $latestNotifs = Notification::whereIn('template_id', $templateIds)
                ->where('created_at', '>=', now()->subSeconds(5))
                ->whereIn('user_id', array_column($recipients, 'user_id'))
                ->orderByDesc('id')
                ->limit(count($recipients))
                ->get();

            foreach ($latestNotifs as $notification) {
                $this->broadcast($notification);
            }
        } catch (\Throwable $e) {
            Log::warning('NotificationService@sendBulkByCode broadcast partial failure: ' . $e->getMessage());
        }

        return $inserted;
    }

    /**
     * Cari template notifikasi terbaik yang sesuai dengan kode dan target role penerima.
     * Mendukung pemencilan kode unik CMS seperti 'booking_selesai_nakes' atau '.booking_selesai'.
     */
    public function findTemplateForRole(string $templateCode, string $userRole): ?NotificationTemplate
    {
        $cleanCode = ltrim($templateCode, '.');
        $userRole  = strtolower($userRole);

        $possibleCodes = [
            $cleanCode,
            "{$cleanCode}_{$userRole}",
            ".{$cleanCode}",
            ".{$cleanCode}_{$userRole}",
        ];

        $templates = NotificationTemplate::where('is_active', true)
            ->whereIn('code', $possibleCodes)
            ->get();

        if ($templates->isEmpty()) {
            return null;
        }

        $compatible = $templates->filter(function ($t) use ($cleanCode, $userRole) {
            return $this->calculateTemplateScore($t, $cleanCode, $userRole) < 90;
        });

        if ($compatible->isEmpty()) {
            return null;
        }

        return $compatible->sort(function ($a, $b) use ($cleanCode, $userRole) {
            $scoreA = $this->calculateTemplateScore($a, $cleanCode, $userRole);
            $scoreB = $this->calculateTemplateScore($b, $cleanCode, $userRole);
            return $scoreA <=> $scoreB;
        })->first();
    }

    private function calculateTemplateScore(NotificationTemplate $template, string $code, string $role): int
    {
        $tRole = strtolower(trim($template->target_role ?? ''));
        $uRole = strtolower(trim($role));
        $cleanTemplateCode = ltrim($template->code, '.');

        if ($cleanTemplateCode === "{$code}_{$uRole}" && $tRole === $uRole) {
            return 1;
        }
        if ($cleanTemplateCode === "{$code}_{$uRole}") {
            return 2;
        }
        if ($cleanTemplateCode === $code && $tRole === $uRole) {
            return 3;
        }
        if ($cleanTemplateCode === $code && $tRole === 'all') {
            return 4;
        }
        if ($cleanTemplateCode === $code && empty($tRole)) {
            return 5;
        }
        return 99;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Parse placeholder {variable} dalam teks dengan nilai dari $variables.
     *
     * Contoh:
     *   parsePlaceholders("Halo {nama}, booking #{booking_id} telah dikonfirmasi", [
     *       'nama'       => 'Budi',
     *       'booking_id' => 'BK-001',
     *   ])
     *   // => "Halo Budi, booking #BK-001 telah dikonfirmasi"
     *
     * @param  string $text
     * @param  array  $variables
     * @return string
     */
    public function parsePlaceholders(string $text, array $variables): string
    {
        if (empty($variables)) {
            return $text;
        }

        $search  = [];
        $replace = [];

        foreach ($variables as $key => $value) {
            $valStr = (string) $value;
            // Support both ${key} (CMS/JS format) and {key} (Laravel format)
            $search[]  = '${' . $key . '}';
            $replace[] = $valStr;

            $search[]  = '{' . $key . '}';
            $replace[] = $valStr;
        }

        return str_replace($search, $replace, $text);
    }

    /**
     * Dispatch broadcast event WebSocket ke klien.
     * Gagal silent – tidak menggagalkan proses pengiriman notifikasi.
     *
     * @param  Notification $notification
     * @return void
     */
    protected function broadcast(Notification $notification): void
    {
        try {
            event(new InAppNotificationEvent($notification));
        } catch (\Throwable $e) {
            Log::warning(
                "NotificationService: gagal broadcast notification #{$notification->id}: " . $e->getMessage()
            );
        }
    }
}
