<?php

namespace Tests\Feature;

use App\Events\InAppNotificationEvent;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\Pasien;
use App\Models\Role;
use App\Models\TenagaMedis;
use App\Models\Transaksi;
use App\Models\Users;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use Tests\TestCase;

class NotificationRoleAndTemplateTest extends TestCase
{
    use DatabaseTransactions;

    private Users $dualRoleUser;
    private Pasien $pasienProfile;
    private TenagaMedis $nakesProfile;

    protected function setUp(): void
    {
        parent::setUp();

        $prov = \App\Models\WilayahLayanan::first();
        if (!$prov) {
            $prov = \App\Models\WilayahLayanan::create([
                'id_provinsi' => 1,
                'nama_provinsi' => 'DKI Jakarta',
                'is_active' => true,
            ]);
        }
        $idWilayah = $prov->id_provinsi;

        Role::firstOrCreate(['nama_role' => 'pasien']);
        Role::firstOrCreate(['nama_role' => 'nakes']);

        // User with BOTH pasien and nakes roles
        $this->dualRoleUser = Users::create([
            'email'             => 'dualrole@example.com',
            'password'          => bcrypt('password123'),
            'is_active'         => true,
            'email_verified_at' => now(),
        ]);
        $this->dualRoleUser->roles()->attach(['pasien', 'nakes']);

        $this->pasienProfile = Pasien::create([
            'id_user'      => $this->dualRoleUser->id_user,
            'nama_lengkap' => 'Pasien Dual Role',
            'no_telepon'   => '08123456789',
        ]);

        $this->nakesProfile = TenagaMedis::create([
            'id_user'            => $this->dualRoleUser->id_user,
            'id_pasien'          => $this->pasienProfile->id_pasien,
            'id_wilayah_layanan' => $idWilayah,
            'nama_lengkap'       => 'Nakes Dual Role',
            'nama_panggilan'     => 'Nakes',
            'jenis_kelamin'      => 'L',
            'tempat_lahir'       => 'Jakarta',
            'tanggal_lahir'      => '1990-01-01',
            'agama'              => 'Islam',
            'no_telp'            => '08123456789',
            'alamat_lengkap'     => 'Jl. Test No. 1',
            'jenis_tenaga_medis' => 'Dokter',
            'universitas'        => 'Universitas Indonesia',
            'program_studi'      => 'Kedokteran',
            'tahun_lulus'        => 2015,
            'no_str'             => '1234567890123456',
            'no_sip'             => '1234567890123456',
            'nik'                => '1234567890123456',
            'file_ktp'           => 'ktp.jpg',
            'ijazah'             => 'ijazah.jpg',
            'file_skck'          => 'skck.jpg',
            'file_cv'            => 'cv.pdf',
            'file_str'           => 'str.jpg',
            'file_sip'           => 'sip.jpg',
            'foto_profile'       => 'profile.jpg',
            'is_active'          => true,
        ]);
    }

    /**
     * Test 1: NotificationService::sendByCode() selects template matching recipient's target_role.
     */
    public function test_send_by_code_selects_correct_target_role_template()
    {
        NotificationTemplate::updateOrCreate(
            ['code' => 'booking_selesai'],
            [
                'name'         => 'Booking Selesai Pasien',
                'target_role'  => 'pasien',
                'title'        => 'Layanan Pasien Selesai',
                'body'         => 'Terima kasih telah menggunakan layanan kami.',
                'action_url'   => '/booking/123',
                'is_active'    => true,
            ]
        );

        NotificationTemplate::updateOrCreate(
            ['code' => 'booking_selesai_nakes'],
            [
                'name'         => 'Booking Selesai Nakes',
                'target_role'  => 'nakes',
                'title'        => 'Tindakan Nakes Selesai',
                'body'         => 'Laporan tindakan Anda telah disimpan.',
                'action_url'   => '/nakes/booking/123',
                'is_active'    => true,
            ]
        );

        $service = new NotificationService();

        // Send to PASIEN role
        $notifPasien = $service->sendByCode('booking_selesai', $this->dualRoleUser->id_user, 'pasien');
        $this->assertNotNull($notifPasien);
        $this->assertEquals('Layanan Pasien Selesai', $notifPasien->title);
        $this->assertEquals('/booking/123', $notifPasien->action_url);

        // Send to NAKES role (sendByCode automatically matches booking_selesai_nakes for nakes role)
        $notifNakes = $service->sendByCode('booking_selesai', $this->dualRoleUser->id_user, 'nakes');
        $this->assertNotNull($notifNakes);
        $this->assertEquals('Tindakan Nakes Selesai', $notifNakes->title);
        $this->assertEquals('/nakes/booking/123', $notifNakes->action_url);
    }

    /**
     * Test 2: Dual role user fetching notifications with ?role=pasien vs ?role=nakes.
     */
    public function test_dual_role_user_notification_filtering_and_unread_count()
    {
        // Seed 1 unread notification for PASIEN and 2 for NAKES
        Notification::create([
            'user_id'   => $this->dualRoleUser->id_user,
            'user_role' => 'pasien',
            'title'     => 'Notifikasi Pasien 1',
            'body'      => 'Isi notifikasi pasien',
            'is_read'   => false,
        ]);

        Notification::create([
            'user_id'   => $this->dualRoleUser->id_user,
            'user_role' => 'nakes',
            'title'     => 'Notifikasi Nakes 1',
            'body'      => 'Isi notifikasi nakes 1',
            'is_read'   => false,
        ]);

        Notification::create([
            'user_id'   => $this->dualRoleUser->id_user,
            'user_role' => 'nakes',
            'title'     => 'Notifikasi Nakes 2',
            'body'      => 'Isi notifikasi nakes 2',
            'is_read'   => false,
        ]);

        $this->actingAs($this->dualRoleUser, 'sanctum');

        // GET unread count for Pasien role
        $resUnreadPasien = $this->getJson('/api/notifications/unread-count?role=pasien');
        $resUnreadPasien->assertStatus(200)
            ->assertJsonPath('data.unread_count', 1);

        // GET unread count for Nakes role
        $resUnreadNakes = $this->getJson('/api/notifications/unread-count?role=nakes');
        $resUnreadNakes->assertStatus(200)
            ->assertJsonPath('data.unread_count', 2);

        // GET index for Pasien role
        $resIndexPasien = $this->getJson('/api/notifications?role=pasien');
        $resIndexPasien->assertStatus(200)
            ->assertJsonPath('unread_count', 1);
        $this->assertCount(1, $resIndexPasien->json('data'));
        $this->assertEquals('Notifikasi Pasien 1', $resIndexPasien->json('data.0.title'));

        // GET index for Nakes role
        $resIndexNakes = $this->getJson('/api/notifications?role=nakes');
        $resIndexNakes->assertStatus(200)
            ->assertJsonPath('unread_count', 2);
        $this->assertCount(2, $resIndexNakes->json('data'));
    }

    /**
     * Test 3: TransaksiObserver triggers payment_success and prevents duplicate on webhook retries.
     */
    public function test_transaksi_payment_success_notification_and_idempotency()
    {
        NotificationTemplate::updateOrCreate(
            ['code' => 'payment_success'],
            [
                'name'         => 'Pembayaran Berhasil',
                'target_role'  => 'pasien',
                'title'        => 'Pembayaran Berhasil',
                'body'         => 'Pembayaran Rp {amount} untuk {booking_id} berhasil.',
                'is_active'    => true,
            ]
        );

        $booking = Booking::create([
            'id_pasien'          => $this->pasienProfile->id_pasien,
            'booking_code'       => 'BK-1001',
            'kode_booking'       => 'BK-1001',
            'status_booking'     => 'Pending',
            'tanggal_kunjungan'  => now()->addDay(),
            'jam_kunjungan'      => '09:00:00',
            'alamat_kunjungan'   => 'Jl. Kunjungan No. 1',
            'latitude_kunjungan' => -6.200000,
            'longitude_kunjungan'=> 106.816666,
        ]);

        $transaksi = Transaksi::create([
            'id_booking'      => $booking->id_booking,
            'status_transaksi'=> 'Pending',
            'jumlah_total'    => 150000,
        ]);

        // First status update to Lunas -> triggers notification
        $transaksi->status_transaksi = 'Lunas';
        $transaksi->save();

        $this->assertDatabaseHas('notifications', [
            'user_id'   => $this->dualRoleUser->id_user,
            'user_role' => 'pasien',
            'title'     => 'Pembayaran Berhasil',
        ]);

        $countBefore = Notification::where('user_id', $this->dualRoleUser->id_user)
            ->where('user_role', 'pasien')
            ->where('title', 'Pembayaran Berhasil')
            ->count();
        $this->assertEquals(1, $countBefore);

        // Simulated webhook retry: updating transaction again
        $transaksi->status_transaksi = 'settlement';
        $transaksi->save();

        $countAfter = Notification::where('user_id', $this->dualRoleUser->id_user)
            ->where('user_role', 'pasien')
            ->where('title', 'Pembayaran Berhasil')
            ->count();

        // Count should remain 1 (no duplicate created)
        $this->assertEquals(1, $countAfter);
    }

    /**
     * Test 4: InAppNotificationEvent payload includes user_id and user_role.
     */
    public function test_in_app_notification_event_payload_includes_user_context()
    {
        $notif = Notification::create([
            'user_id'   => 42,
            'user_role' => 'pasien',
            'title'     => 'Test Event',
            'body'      => 'Test Body',
            'is_read'   => false,
        ]);

        $event = new InAppNotificationEvent($notif);

        $this->assertEquals(42, $event->payload['user_id']);
        $this->assertEquals('pasien', $event->payload['user_role']);
        $this->assertEquals('private-notifications.pasien.42', $event->broadcastOn()[0]->name);
    }
}
