<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminTier;
use App\Models\GlobalConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GlobalConfigApiTest extends TestCase
{
    use RefreshDatabase;

    private $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup Admin
        AdminTier::create([
            'nama_tier' => 'Admin',
            'slug' => 'admin',
            'deskripsi' => 'Standard Admin',
            'is_protected' => true,
        ]);

        $this->adminUser = Admin::create([
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'nama_lengkap' => 'Standard Admin Test',
            'tier_admin' => 'Admin',
            'is_active' => true,
        ]);
    }

    public function test_get_global_config_returns_default()
    {
        // Delete any existing config to check dynamic creation/fallback
        GlobalConfig::truncate();

        $response = $this->getJson('/api/global-config');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'app_name',
                    'app_logo',
                    'app_favicon',
                    'whatsapp_number',
                    'phone_number',
                    'email',
                    'address',
                    'socials',
                    'maintenance_mode',
                ]
            ]);

        $this->assertEquals('Smart Home Care', $response->json('data.app_name'));
        $this->assertFalse($response->json('data.maintenance_mode'));
        $this->assertIsArray($response->json('data.socials'));
    }

    public function test_update_global_config_requires_auth()
    {
        $response = $this->postJson('/api/global-config', [
            'app_name' => 'Home Care Edited',
        ]);

        $response->assertStatus(401);
    }

    public function test_admin_can_update_global_config()
    {
        Storage::fake('public');
        $this->actingAs($this->adminUser, 'sanctum');

        $logo = UploadedFile::fake()->image('logo.png');
        $favicon = UploadedFile::fake()->image('favicon.png');

        $socials = [
            [
                'name' => 'Facebook',
                'icon' => 'fa-facebook',
                'url' => 'https://facebook.com/homecare',
                'text' => '@smarthomecare'
            ],
            [
                'name' => 'Instagram',
                'icon' => 'fa-instagram',
                'text' => 'Instagram Smart Home'
            ]
        ];

        $response = $this->postJson('/api/global-config', [
            'app_name' => 'Home Care Baru',
            'app_logo' => $logo,
            'app_favicon' => $favicon,
            'whatsapp_number' => '6289999999',
            'phone_number' => '02199999',
            'email' => 'adminbaru@test.com',
            'address' => 'Alamat Baru',
            'socials' => $socials,
            'maintenance_mode' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Konfigurasi global berhasil diperbarui');

        $this->assertEquals('Home Care Baru', $response->json('data.app_name'));
        $this->assertTrue($response->json('data.maintenance_mode'));
        $this->assertEquals($socials, $response->json('data.socials'));

        $this->assertNotNull($response->json('data.app_logo'));
        $this->assertNotNull($response->json('data.app_favicon'));

        // Verify storage contains the stored files
        $logoPath = str_replace(url('/storage/'), '', $response->json('data.app_logo'));
        $faviconPath = str_replace(url('/storage/'), '', $response->json('data.app_favicon'));

        Storage::disk('public')->assertExists($logoPath);
        Storage::disk('public')->assertExists($faviconPath);

        // Test stringified JSON socials (for multipart/form-data frontend upload compatibility)
        $stringifiedSocials = json_encode($socials);
        $responseStringified = $this->postJson('/api/global-config', [
            'socials' => $stringifiedSocials,
        ]);
        $responseStringified->assertStatus(200);
        $this->assertEquals($socials, $responseStringified->json('data.socials'));
    }
}
