<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Legality;
use App\Models\GlobalConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewApisTest extends TestCase
{
    use RefreshDatabase;

    private Admin $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an admin user for authenticated endpoints
        $this->adminUser = Admin::create([
            'nama_lengkap' => 'Super Admin Test',
            'email' => 'superadmin@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
            'tier_admin' => 'super_admin',
        ]);
    }

    /**
     * Test Web Setting APIs (Public GET & Admin POST)
     */
    public function test_web_setting_api_flow(): void
    {
        Storage::fake('public');

        // 1. Test public GET web-setting
        $response = $this->getJson('/api/web-setting');
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'app_logo',
                    'app_favicon'
                ]
            ]);

        // 2. Test admin POST web-setting (unauthenticated should fail)
        $uploadResponse = $this->postJson('/api/web-setting', [
            'app_logo' => UploadedFile::fake()->image('logo.png'),
            'app_favicon' => UploadedFile::fake()->image('favicon.ico'),
        ]);
        $uploadResponse->assertStatus(401);

        // 3. Test admin POST web-setting (authenticated should succeed)
        $this->actingAs($this->adminUser, 'sanctum');

        $uploadResponse = $this->postJson('/api/web-setting', [
            'app_logo' => UploadedFile::fake()->image('logo.png'),
            'app_favicon' => UploadedFile::fake()->image('favicon.png'),
        ]);

        $uploadResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'app_logo',
                    'app_favicon'
                ]
            ]);

        // Verify stored settings and files
        $config = GlobalConfig::first();
        $this->assertNotNull($config->app_logo);
        $this->assertNotNull($config->app_favicon);
        Storage::disk('public')->assertExists($config->app_logo);
        Storage::disk('public')->assertExists($config->app_favicon);
    }

    /**
     * Test Legality APIs (Public GET detail & Admin CRUD)
     */
    public function test_legality_api_flow(): void
    {
        // 1. Create default legality using seeder
        $this->seed(\Database\Seeders\LegalitySeeder::class);

        // 2. Test public fetch of legality by key
        $response = $this->getJson('/api/legalitas/detail/syarat-ketentuan-pasien');
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.key', 'syarat-ketentuan-pasien')
            ->assertJsonPath('data.title', 'Syarat & Ketentuan Pasien');

        $responsePrivasi = $this->getJson('/api/legalitas/detail/kebijakan-privasi');
        $responsePrivasi->assertStatus(200)
            ->assertJsonPath('data.key', 'kebijakan-privasi')
            ->assertJsonPath('data.title', 'Kebijakan Privasi');

        // Test public list of legalities (should return all 3 seeded active ones)
        $listResponse = $this->getJson('/api/legalitas/list');
        $listResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');

        // Test list filters out inactive ones
        $inactive = Legality::where('key', 'kebijakan-privasi')->first();
        $inactive->update(['is_active' => false]);

        $listResponse2 = $this->getJson('/api/legalitas/list');
        $listResponse2->assertStatus(200)
            ->assertJsonCount(2, 'data');

        // Restore active status for subsequent tests
        $inactive->update(['is_active' => true]);

        // 3. Test admin CRUD (unauthenticated should fail)
        $createResponse = $this->postJson('/api/legalitas', [
            'key' => 'tentang-kami',
            'title' => 'Tentang Kami',
            'content' => 'Teks tentang kami...',
        ]);
        $createResponse->assertStatus(401);

        // 4. Test admin CRUD (authenticated should succeed)
        $this->actingAs($this->adminUser, 'sanctum');

        // Store
        $createResponse = $this->postJson('/api/legalitas', [
            'key' => 'tentang-kami',
            'title' => 'Tentang Kami',
            'content' => 'Teks tentang kami...',
            'is_active' => true,
        ]);
        $createResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.key', 'tentang-kami');

        $legalityId = $createResponse->json('data.id');

        // Index
        $indexResponse = $this->getJson('/api/legalitas');
        $indexResponse->assertStatus(200)
            ->assertJsonCount(4, 'data'); // 3 from seeder + 1 new

        // Show
        $showResponse = $this->getJson("/api/legalitas/{$legalityId}");
        $showResponse->assertStatus(200)
            ->assertJsonPath('data.title', 'Tentang Kami');

        // Update
        $updateResponse = $this->putJson("/api/legalitas/{$legalityId}", [
            'key' => 'tentang-kami-updated',
            'title' => 'Tentang Kami Baru',
            'content' => 'Teks tentang kami baru...',
        ]);
        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.title', 'Tentang Kami Baru');

        // Delete
        $deleteResponse = $this->deleteJson("/api/legalitas/{$legalityId}");
        $deleteResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Verify database deletion
        $this->assertDatabaseMissing('legalities', [
            'id' => $legalityId,
        ]);
    }
}
