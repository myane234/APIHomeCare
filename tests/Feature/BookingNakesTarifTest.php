<?php

namespace Tests\Feature;

use App\Models\Users;
use App\Models\Role;
use App\Models\Pasien;
use App\Models\MasterLayanan;
use App\Models\KategoriLayanan;
use App\Models\MasterKategoriTarif;
use App\Models\MasterTarif;
use App\Models\MasterKomponenBiaya;
use App\Models\BhpItem;
use App\Models\Booking;
use App\Models\Transaksi;
use App\Models\TenagaMedis;
use App\Models\WilayahLayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookingNakesTarifTest extends TestCase
{
    use RefreshDatabase;

    protected $provinsiId = '31';

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'https://api.sandbox.midtrans.com/v2/charge' => Http::response([
                'status_code' => '201',
                'status_message' => 'Success',
                'transaction_id' => 'mock-trans-123',
                'order_id' => 'BOOKING-1-123456',
                'gross_amount' => '150000.00',
            ], 201),
        ]);

        $wilayah = WilayahLayanan::firstOrCreate(
            ['id_provinsi' => '31'],
            ['nama_provinsi' => 'DKI Jakarta', 'is_active' => true]
        );
        $this->provinsiId = $wilayah->id_provinsi;
    }

    private function createTenagaMedisData(array $override = []): array
    {
        return array_merge([
            'id_wilayah_layanan' => $this->provinsiId,
            'nama_lengkap' => 'Nakes Test',
            'nama_panggilan' => 'Nakes',
            'jenis_kelamin' => 'P',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1995-01-01',
            'agama' => 'Islam',
            'no_telp' => '08123456789',
            'alamat_lengkap' => 'Jl. Kebon Jeruk No 12',
            'jenis_tenaga_medis' => 'Perawat',
            'universitas' => 'Universitas Indonesia',
            'program_studi' => 'Keperawatan',
            'tahun_lulus' => 2018,
            'no_str' => '1234567890',
            'no_sip' => '0987654321',
            'file_ktp' => 'dummy_ktp.jpg',
            'ijazah' => 'dummy_ijazah.jpg',
            'file_skck' => 'dummy_skck.jpg',
            'file_cv' => 'dummy_cv.jpg',
            'file_str' => 'dummy_str.jpg',
            'file_sip' => 'dummy_sip.jpg',
            'status' => 'approved',
        ], $override);
    }

    public function test_booking_calculates_tariffs_components_and_bhp_correctly(): void
    {
        // 1. Setup Patient
        Role::firstOrCreate(['nama_role' => 'pasien']);
        $user = Users::create([
            'email' => 'pasien_tarif@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        $user->roles()->attach('pasien');

        $pasien = Pasien::create([
            'id_user' => $user->id_user,
            'nama_lengkap' => 'Pasien Tarif',
            'nik' => '1234567890123456',
            'jenis_kelamin' => 'L',
            'alamat_utama' => 'Jl. Merdeka No 10',
        ]);

        // 2. Setup Nakes with geolocation
        Role::firstOrCreate(['nama_role' => 'tenaga medis']);
        Role::firstOrCreate(['nama_role' => 'nakes']);
        $nakesUser = Users::create([
            'email' => 'nakes_near@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        $nakesUser->roles()->attach(['tenaga medis', 'nakes']);

        $nakesPasien = Pasien::create([
            'id_user' => $nakesUser->id_user,
            'nama_lengkap' => 'Nakes Terdekat',
            'nik' => '6543210987654321',
            'jenis_kelamin' => 'P',
            'alamat_utama' => 'Jl. Sudirman',
        ]);

        $tenagaMedis = TenagaMedis::create($this->createTenagaMedisData([
            'id_user' => $nakesUser->id_user,
            'id_pasien' => $nakesPasien->id_pasien,
            'nama_lengkap' => 'Nakes Terdekat',
            'nik' => '6543210987654321',
            'latitude' => -6.2000000,
            'longitude' => 106.8160000,
        ]));

        // 3. Setup Master Data Layanan, BHP, Komponen
        $kategoriLayanan = KategoriLayanan::create([
            'nama_kategori' => 'Perawatan Umum'
        ]);

        $layanan = MasterLayanan::create([
            'id_kategori_layanan' => $kategoriLayanan->id_kategori_layanan,
            'nama_layanan' => 'Layanan Rawat Luka',
            'deskripsi_layanan' => 'Perawatan luka steril',
            'harga' => 100000.00,
            'include_transport' => false,
            'tipe_layanan' => 'tindakan',
        ]);

        $bhp = BhpItem::create([
            'nama_bhp' => 'Kain Kassa Steril',
            'harga_modal' => 5000.00,
            'tipe_margin' => 'nominal',
            'nilai_margin' => 5000.00, // harga_jual = 5000 + 5000 = 10000
            'is_active' => true,
        ]);

        $layanan->bhpItems()->attach($bhp->id_bhp, ['qty_default' => 2]);

        $kategoriTarif = MasterKategoriTarif::firstOrCreate(
            ['nama_kategori' => 'REGULER'],
            ['biaya_tambahan' => 0.00, 'is_default' => true]
        );

        $adminComp = MasterKomponenBiaya::create([
            'nama_komponen' => 'Biaya Admin',
            'tipe_komponen' => 'admin_aplikasi',
            'jenis_nilai' => 'nominal',
            'nilai' => 5000.00,
            'is_active' => true,
        ]);

        $taxComp = MasterKomponenBiaya::create([
            'nama_komponen' => 'PPN 11%',
            'tipe_komponen' => 'pajak',
            'jenis_nilai' => 'persen',
            'nilai' => 11.00,
            'is_active' => true,
        ]);

        $masterTarif = MasterTarif::create([
            'nama_template' => 'Template Reguler Layanan',
            'id_kategori_tarif' => $kategoriTarif->id_kategori_tarif,
            'id_layanan' => $layanan->id_layanan,
            'fee_nakes_tipe' => 'persen',
            'fee_nakes_nilai' => 80.00,
            'fee_nakes_nominal' => 80000.00,
            'fee_platform_nominal' => 20000.00,
            'is_active' => true,
        ]);

        $masterTarif->komponenTarif()->attach([$adminComp->id_komponen, $taxComp->id_komponen]);

        // 4. Send Booking Request as Patient
        $this->actingAs($user, 'sanctum');

        $payload = [
            'id_layanan' => $layanan->id_layanan,
            'id_kategori_tarif' => $kategoriTarif->id_kategori_tarif,
            'tanggal_kunjungan' => '2026-09-01',
            'jam_kunjungan' => '09:00',
            'alamat_kunjungan' => 'Jl. Merdeka No 10',
            'latitude_kunjungan' => -6.2000000,
            'longitude_kunjungan' => 106.8160000,
        ];

        $response = $this->postJson('/api/booking', $payload);

        if ($response->status() !== 200) {
            dump($response->json());
        }

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $bookingId = $response->json('data.booking.id_booking');
        $this->assertNotNull($bookingId);

        // Verify Transaksi calculation in DB
        $transaksi = Transaksi::where('id_booking', $bookingId)->first();
        $this->assertNotNull($transaksi);
        $this->assertEquals(100000.00, (float)$transaksi->sl);
        $this->assertEquals(20000.00, (float)$transaksi->sb); // 2 * 10000
        $this->assertEquals(5000.00, (float)$transaksi->ba);
    }

    public function test_nearest_nakes_list_endpoint(): void
    {
        Role::firstOrCreate(['nama_role' => 'tenaga medis']);

        $user1 = Users::create(['email' => 'nakes1@example.com', 'password' => bcrypt('password'), 'is_active' => true]);
        $user1->roles()->attach('tenaga medis');
        $pasien1 = Pasien::create(['id_user' => $user1->id_user, 'nama_lengkap' => 'Nakes Near', 'nik' => '1111111111111111', 'jenis_kelamin' => 'L', 'alamat_utama' => 'A']);
        TenagaMedis::create($this->createTenagaMedisData([
            'id_user' => $user1->id_user,
            'id_pasien' => $pasien1->id_pasien,
            'nama_lengkap' => 'Nakes Near (1km)',
            'nama_panggilan' => 'Near',
            'nik' => '1111111111111111',
            'latitude' => -6.2000000,
            'longitude' => 106.8160000,
        ]));

        $user2 = Users::create(['email' => 'nakes2@example.com', 'password' => bcrypt('password'), 'is_active' => true]);
        $user2->roles()->attach('tenaga medis');
        $pasien2 = Pasien::create(['id_user' => $user2->id_user, 'nama_lengkap' => 'Nakes Far', 'nik' => '2222222222222222', 'jenis_kelamin' => 'P', 'alamat_utama' => 'B']);
        TenagaMedis::create($this->createTenagaMedisData([
            'id_user' => $user2->id_user,
            'id_pasien' => $pasien2->id_pasien,
            'nama_lengkap' => 'Nakes Far (50km)',
            'nama_panggilan' => 'Far',
            'nik' => '2222222222222222',
            'latitude' => -6.6000000,
            'longitude' => 106.8160000,
        ]));

        $response = $this->getJson('/api/booking/nakes-terdekat?latitude=-6.2000000&longitude=106.8160000');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals('Nakes Near (1km)', $data[0]['nama_lengkap']);
    }

    public function test_nakes_accepts_and_updates_order_status(): void
    {
        Role::firstOrCreate(['nama_role' => 'pasien']);
        $pasienUser = Users::create(['email' => 'pasien_order@example.com', 'password' => bcrypt('password'), 'is_active' => true]);
        $pasienUser->roles()->attach('pasien');
        $pasien = Pasien::create(['id_user' => $pasienUser->id_user, 'nama_lengkap' => 'Pasien Order', 'nik' => '3333333333333333', 'jenis_kelamin' => 'L', 'alamat_utama' => 'C']);

        Role::firstOrCreate(['nama_role' => 'tenaga medis']);
        Role::firstOrCreate(['nama_role' => 'nakes']);
        $nakesUser = Users::create(['email' => 'nakes_order@example.com', 'password' => bcrypt('password'), 'is_active' => true]);
        $nakesUser->roles()->attach(['tenaga medis', 'nakes']);
        $nakesPasien = Pasien::create(['id_user' => $nakesUser->id_user, 'nama_lengkap' => 'Nakes Order', 'nik' => '4444444444444444', 'jenis_kelamin' => 'P', 'alamat_utama' => 'D']);
        $nakes = TenagaMedis::create($this->createTenagaMedisData([
            'id_user' => $nakesUser->id_user,
            'id_pasien' => $nakesPasien->id_pasien,
            'nama_lengkap' => 'Nakes Order Acceptor',
            'nama_panggilan' => 'Acceptor',
            'nik' => '4444444444444444',
            'latitude' => -6.200,
            'longitude' => 106.816,
        ]));

        $kategoriLayanan = KategoriLayanan::create(['nama_kategori' => 'Umum']);
        $layanan = MasterLayanan::create([
            'id_kategori_layanan' => $kategoriLayanan->id_kategori_layanan,
            'nama_layanan' => 'Layanan Cek',
            'harga' => 50000.00,
            'tipe_layanan' => 'tindakan',
        ]);

        $booking = Booking::create([
            'booking_code' => 'B-2608270001',
            'id_pasien' => $pasien->id_pasien,
            'id_layanan' => $layanan->id_layanan,
            'id_tenaga_medis' => $nakes->id_tenaga_medis,
            'tanggal_kunjungan' => '2026-09-01',
            'jam_kunjungan' => '10:00',
            'alamat_kunjungan' => 'Alamat Pasien',
            'latitude_kunjungan' => -6.200,
            'longitude_kunjungan' => 106.816,
            'status_booking' => 'Pending',
        ]);

        // Nakes login & accepts order
        $this->actingAs($nakesUser, 'sanctum');

        $acceptResponse = $this->postJson("/api/nakes/booking/{$booking->id_booking}/terima");

        $acceptResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status_booking', 'DiPerjalanan')
            ->assertJsonPath('data.id_tenaga_medis', $nakes->id_tenaga_medis);

        // Update status to Tindakan
        $statusResponse = $this->postJson("/api/nakes/booking/{$booking->id_booking}/status", [
            'status_booking' => 'Tindakan'
        ]);

        $statusResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status_booking', 'Tindakan');
    }
}
