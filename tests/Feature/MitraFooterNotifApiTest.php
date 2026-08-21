<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Users;
use App\Models\NotificationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MitraFooterNotifApiTest extends TestCase
{
    use RefreshDatabase;

    private $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        Role::firstOrCreate(['nama_role' => 'admin']);

        // Setup Admin User
        $this->adminUser = Users::create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $this->adminUser->roles()->attach('admin');
    }

    public function test_get_mitra_content_returns_default()
    {
        $response = $this->getJson('/api/resource/content/mitra');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'mitra_banner',
                'mitra_text_banner',
                'mitra_description'
            ]);
    }

    public function test_update_mitra_requires_admin_role()
    {
        // Unauthenticated
        $response = $this->postJson('/api/resource/content/mitra', [
            'mitra_text_banner' => 'Gabung sebagai nakes',
        ]);
        $response->assertStatus(401);

        // Authenticated but non-admin (e.g. general user)
        $user = Users::create([
            'email' => 'user@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user, 'sanctum');

        $response2 = $this->postJson('/api/resource/content/mitra', [
            'mitra_text_banner' => 'Gabung sebagai nakes',
        ]);
        $response2->assertStatus(403);
    }

    public function test_admin_can_update_mitra_content()
    {
        Storage::fake('public');
        $this->actingAs($this->adminUser, 'sanctum');

        $banner = UploadedFile::fake()->image('mitra_banner.png');

        $response = $this->postJson('/api/resource/content/mitra', [
            'mitra_banner' => $banner,
            'mitra_text_banner' => 'Judul Gabung Mitra Baru',
            'mitra_description' => 'Deskripsi mitra detail',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Konten Gabung Mitra berhasil diperbarui');

        $this->assertNotNull($response->json('data.mitra_banner'));
        $this->assertEquals('Judul Gabung Mitra Baru', $response->json('data.mitra_text_banner'));
        $this->assertEquals('Deskripsi mitra detail', $response->json('data.mitra_description'));
    }

    public function test_get_footer_content_returns_default()
    {
        $response = $this->getJson('/api/resource/content/footer');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'footer_description',
                'footer_phone',
                'footer_email',
                'footer_address',
                'footer_socials'
            ]);
    }

    public function test_admin_can_update_footer_content()
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $socials = [
            [
                'name' => 'Facebook',
                'icon' => 'fa-facebook',
                'url' => 'https://facebook.com/homecare'
            ],
            [
                'name' => 'Instagram',
                'icon' => 'fa-instagram',
                'url' => 'https://instagram.com/homecare'
            ]
        ];

        $response = $this->postJson('/api/resource/content/footer', [
            'footer_description' => 'Ini deskripsi kaki website',
            'footer_phone' => '08123456789',
            'footer_email' => 'kontak@homecare.com',
            'footer_address' => 'Jl. Home Care No. 123',
            'footer_socials' => $socials,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Konten Footer berhasil diperbarui');

        $this->assertEquals('Ini deskripsi kaki website', $response->json('data.footer_description'));
        $this->assertEquals('08123456789', $response->json('data.footer_phone'));
        $this->assertEquals('kontak@homecare.com', $response->json('data.footer_email'));
        $this->assertEquals('Jl. Home Care No. 123', $response->json('data.footer_address'));
        $this->assertEquals($socials, $response->json('data.footer_socials'));
    }

    public function test_admin_can_manage_notification_templates()
    {
        $this->actingAs($this->adminUser, 'sanctum');

        // 1. Create Template
        $response = $this->postJson('/api/notification-templates', [
            'code' => 'booking_success',
            'name' => 'Pemesanan Berhasil',
            'title' => 'Booking #{{booking_id}} Berhasil',
            'body' => 'Halo {{name}}, pesanan Anda berhasil dibuat.',
            'channel' => 'push,email',
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'booking_success');

        $templateId = $response->json('data.id');

        // 2. Read Templates List
        $responseList = $this->getJson('/api/notification-templates');
        $responseList->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertCount(1, $responseList->json('data'));

        // 3. Read Single Template (by ID or Code)
        $responseSingle = $this->getJson("/api/notification-templates/{$templateId}");
        $responseSingle->assertStatus(200)
            ->assertJsonPath('data.code', 'booking_success');

        $responseSingleCode = $this->getJson("/api/notification-templates/booking_success");
        $responseSingleCode->assertStatus(200)
            ->assertJsonPath('data.id', $templateId);

        // 4. Update Template
        $responseUpdate = $this->putJson("/api/notification-templates/{$templateId}", [
            'code' => 'booking_success',
            'name' => 'Pemesanan Berhasil Terupdate',
            'title' => 'Booking #{{booking_id}} Sukses',
            'body' => 'Pesanan Anda sukses dibuat.',
            'channel' => 'all',
            'is_active' => false,
        ]);

        $responseUpdate->assertStatus(200)
            ->assertJsonPath('data.name', 'Pemesanan Berhasil Terupdate')
            ->assertJsonPath('data.is_active', false);

        // 5. Delete Template
        $responseDelete = $this->deleteJson("/api/notification-templates/{$templateId}");
        $responseDelete->assertStatus(200)
            ->assertJsonPath('success', true);

        // Verify deletion
        $this->assertDatabaseMissing('notification_templates', ['id' => $templateId]);
    }
}
