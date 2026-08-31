<?php

namespace Tests\Feature;

use App\Models\Users;
use App\Models\TenagaMedis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreateNakesViaAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock storage disk public agar file test tidak benar-benar tersimpan di folder storage asli
        Storage::fake('public');

        // Pastikan tabel master_provinsi punya dummy data untuk validasi id_wilayah_layanan
        DB::table('master_provinsi')->insert([
            'id_provinsi' => 1,
            'nama_provinsi' => 'DKI Jakarta'
        ]);
    }

    /**
     * Helper untuk membuat payload data Nakes yang valid
     */
    private function getValidPayload(array $overrides = []): array
    {
        return array_merge([
            // User Data
            'email'                => 'nakesbaru@example.com',
            'password'             => 'password123',

            // Admin Input
            'status'               => 'approved',
            'admin_notes'          => 'Data sudah diverifikasi penuh oleh admin.',

            // Biodata
            'nik'                  => '3171234567890001',
            'nama_lengkap'         => 'Dr. Budi Santoso',
            'nama_panggilan'       => 'Budi',
            'jenis_kelamin'        => 'L',
            'tempat_lahir'         => 'Jakarta',
            'tanggal_lahir'        => '1990-05-20',
            'agama'                => 'Islam',
            'no_telp'              => '081234567890',
            'id_wilayah_layanan'   => 1,
            'alamat_lengkap'       => 'Jl. Sudirman No. 123, Jakarta Selatan',
            'foto_profile'         => UploadedFile::fake()->image('profile.jpg'),

            // Profesi & Pendidikan
            'jenis_tenaga_medis'   => 'Dokter Umum',
            'universitas'          => 'Universitas Indonesia',
            'program_studi'        => 'Kedokteran',
            'tahun_lulus'          => 2015,
            'no_str'               => 'STR-123456789',
            'no_sip'               => 'SIP-987654321',

            // Berkas Wajib
            'file_ktp'             => UploadedFile::fake()->create('ktp.pdf', 1000, 'application/pdf'),
            'ijazah'               => UploadedFile::fake()->create('ijazah.pdf', 1000, 'application/pdf'),
            'file_skck'            => UploadedFile::fake()->create('skck.pdf', 1000, 'application/pdf'),
            'file_cv'              => UploadedFile::fake()->create('cv.pdf', 1000, 'application/pdf'),
            'file_str'             => UploadedFile::fake()->create('str.pdf', 1000, 'application/pdf'),
            'file_sip'             => UploadedFile::fake()->create('sip.pdf', 1000, 'application/pdf'),

            // Berkas Opsional
            'tempat_kerja'         => 'RS Cipto Mangunkusumo',
            'lama_bekerja'         => '5 Tahun',
            'dokumen_tambahan'     => [
                UploadedFile::fake()->create('sertifikat1.pdf', 500, 'application/pdf'),
            ]
        ], $overrides);
    }

    public function test_unauthenticated_user_cannot_access_create_nakes()
    {
        $response = $this->postJson('/api/admin/nakes/create', $this->getValidPayload());

        $response->assertStatus(401)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Akses ditolak. Silakan login terlebih dahulu.'
                 ]);
    }

    public function test_validation_fails_if_required_fields_are_missing()
    {
        $admin = Users::factory()->create();

        $response = $this->actingAs($admin)
                         ->postJson('/api/admin/nakes/create', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors([
                     'nik',
                     'nama_lengkap',
                     'nama_panggilan',
                     'jenis_kelamin',
                     'tempat_lahir',
                     'tanggal_lahir',
                     'agama',
                     'no_telp',
                     'id_wilayah_layanan',
                     'alamat_lengkap',
                     'jenis_tenaga_medis',
                     'universitas',
                     'program_studi',
                     'tahun_lulus',
                     'no_str',
                     'no_sip',
                     'file_ktp',
                     'ijazah',
                     'file_skck',
                     'file_cv',
                     'file_str',
                     'file_sip',
                     'status'
                 ]);
    }

    public function test_validation_fails_if_nik_format_is_invalid()
    {
        $admin = Users::factory()->create();

        $payload = $this->getValidPayload(['nik' => '12345']);

        $response = $this->actingAs($admin)
                         ->postJson('/api/admin/nakes/create', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['nik']);
    }

    public function test_admin_can_create_nakes_with_new_user_and_approved_status()
    {
        $admin = Users::factory()->create();
        $payload = $this->getValidPayload();

        $response = $this->actingAs($admin)
                         ->postJson('/api/admin/nakes/create', $payload);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Berhasil menambahkan data Nakes baru oleh admin.',
                 ]);

        $this->assertDatabaseHas('users', [
            'email' => 'nakesbaru@example.com',
            'name'  => 'Dr. Budi Santoso',
        ]);

        $createdUser = Users::where('email', 'nakesbaru@example.com')->first();

        // Assert Role 'nakes' Otomatis Terhubung
        $this->assertDatabaseHas('user_roles', [
            'nama_role' => 'nakes'
        ]);

        $this->assertDatabaseHas('tenaga_medis', [
            'user_id'            => $createdUser->id,
            'nik'                => '3171234567890001',
            'nama_lengkap'       => 'Dr. Budi Santoso',
            'status'             => 'approved',
            'jenis_tenaga_medis' => 'Dokter Umum',
        ]);

        $nakes = TenagaMedis::where('user_id', $createdUser->id)->first();
        Storage::disk('public')->assertExists($nakes->file_ktp);
        Storage::disk('public')->assertExists($nakes->ijazah);
        Storage::disk('public')->assertExists($nakes->file_str);
    }

    public function test_admin_can_create_nakes_for_existing_user_id()
    {
        $admin = Users::factory()->create();
        $existingUser = Users::factory()->create(['name' => 'Existing User']);

        $payload = $this->getValidPayload([
            'user_id'  => $existingUser->id,
            'status'   => 'pelatihan',
            'email'    => null,
            'password' => null,
        ]);

        $response = $this->actingAs($admin)
                         ->postJson('/api/admin/nakes/create', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('tenaga_medis', [
            'user_id' => $existingUser->id,
            'status'  => 'pelatihan',
        ]);
    }
}