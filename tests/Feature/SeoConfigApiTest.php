<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminTier;
use App\Models\SeoConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoConfigApiTest extends TestCase
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

    public function test_get_seo_config_returns_default()
    {
        SeoConfig::truncate();

        $response = $this->getJson('/api/seo-config');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'meta_title',
                    'meta_description',
                    'meta_keywords',
                ]
            ]);

        $this->assertEquals('Smart Home Care - Layanan Kesehatan Home Care Terpercaya', $response->json('data.meta_title'));
    }

    public function test_update_seo_config_requires_auth()
    {
        $response = $this->postJson('/api/seo-config', [
            'meta_title' => 'Title Baru',
        ]);

        $response->assertStatus(401);
    }

    public function test_admin_can_update_seo_config()
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->postJson('/api/seo-config', [
            'meta_title' => 'Title Baru',
            'meta_description' => 'Desc Baru',
            'meta_keywords' => 'Keywords Baru',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Konfigurasi SEO berhasil diperbarui');

        $this->assertEquals('Title Baru', $response->json('data.meta_title'));
        $this->assertEquals('Desc Baru', $response->json('data.meta_description'));
        $this->assertEquals('Keywords Baru', $response->json('data.meta_keywords'));
    }
}
