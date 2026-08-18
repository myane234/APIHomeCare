<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthNewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup dynamic tiers
        AdminTier::create([
            'nama_tier' => 'Super Admin',
            'slug' => 'super-admin',
            'deskripsi' => 'Super Admin',
            'is_protected' => true,
        ]);

        AdminTier::create([
            'nama_tier' => 'Admin',
            'slug' => 'admin',
            'deskripsi' => 'Standard Admin',
            'is_protected' => true,
        ]);

        AdminTier::create([
            'nama_tier' => 'Finance',
            'slug' => 'finance',
            'deskripsi' => 'Finance Admin',
            'is_protected' => false,
        ]);
    }

    public function test_admin_can_login_directly_and_access_admin_endpoints(): void
    {
        // 1. Create standard admin
        $admin = Admin::create([
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'nama_lengkap' => 'Standard Admin Test',
            'tier_admin' => 'Admin',
            'is_active' => true,
        ]);

        // 2. Perform Login
        $response = $this->postJson('/api/admin/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nama', 'Standard Admin Test')
            ->assertJsonPath('data.tier_admin', 'Admin');

        $token = $response->json('data.token');
        $this->assertNotEmpty($token);

        // 3. Perform Login with Super Admin
        $superAdmin = Admin::create([
            'email' => 'superadmin@test.com',
            'password' => bcrypt('password123'),
            'nama_lengkap' => 'Super Admin Test',
            'tier_admin' => 'Super Admin',
            'is_active' => true,
        ]);

        $superResponse = $this->postJson('/api/super-admin/login', [
            'email' => 'superadmin@test.com',
            'password' => 'password123',
        ]);

        $superResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nama', 'Super Admin Test')
            ->assertJsonPath('data.tier_admin', 'Super Admin');

        $superToken = $superResponse->json('data.token');

        // 4. Test "me" endpoint
        $meResponse = $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/super-admin/me');

        $meResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'superadmin@test.com')
            ->assertJsonPath('data.tier_admin', 'Super Admin');
    }

    public function test_admin_tiers_are_flexible(): void
    {
        // Get tiers
        $response = $this->getJson('/api/manage-admin/tiers');
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    }
}
