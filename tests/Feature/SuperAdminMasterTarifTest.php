<?php

namespace Tests\Feature;

use App\Models\MasterLayanan;
use App\Models\MasterKomponenBiaya;
use App\Models\MasterTarif;
use App\Models\WilayahLayanan;
use App\Models\KotaKabupaten;
use App\Models\User;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminMasterTarifTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $admin = Admin::create([
            'user_id' => $this->user->id,
            'nama_admin' => 'Test Admin',
            'no_telepon' => '08123456789',
        ]);
    }

    public function test_store_master_tarif_with_provinsi_includes_all_cities(): void
    {
        $prov = WilayahLayanan::create([
            'nama_provinsi' => 'Jawa Barat',
            'is_active' => true,
        ]);

        $kota1 = KotaKabupaten::create([
            'id_kota' => '3201',
            'id_provinsi' => $prov->id_provinsi,
            'nama_kota' => 'Kab. Bogor',
        ]);

        $kota2 = KotaKabupaten::create([
            'id_kota' => '3273',
            'id_provinsi' => $prov->id_provinsi,
            'nama_kota' => 'Kota Bandung',
        ]);

        $layanan = MasterLayanan::create([
            'nama_layanan' => 'Layanan Test 1',
            'harga' => 150000,
            'is_active' => true,
        ]);

        $layanan2 = MasterLayanan::create([
            'nama_layanan' => 'Layanan Test 2',
            'harga' => 200000,
            'is_active' => true,
        ]);

        $komponen = MasterKomponenBiaya::create([
            'nama_komponen' => 'Biaya Admin',
            'tipe_komponen' => 'admin_aplikasi',
            'jenis_nilai' => 'nominal',
            'nilai' => 5000,
            'is_active' => true,
        ]);

        $payload = [
            'nama_template' => 'Reguler Jawa Barat',
            'id_layanan' => $layanan->id_layanan,
            'layanan_ids' => [$layanan->id_layanan, $layanan2->id_layanan],
            'komponen_tarif_ids' => [$komponen->id_komponen],
            'id_provinsi' => $prov->id_provinsi,
            'tarif_pasien' => 100000,
            'fee_nakes_tipe' => 'persen',
            'fee_nakes_nilai' => 80,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/master-tarif', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        // Check that MasterTarif was created for both cities in Jawa Barat
        $this->assertDatabaseHas('master_tarif', [
            'nama_template' => 'Reguler Jawa Barat',
            'id_kota' => '3201',
            'id_provinsi' => $prov->id_provinsi,
            'fee_nakes_nominal' => 80000,
            'fee_platform_nominal' => 20000,
            'total_biaya_admin' => 5000,
        ]);

        $this->assertDatabaseHas('master_tarif', [
            'nama_template' => 'Reguler Jawa Barat',
            'id_kota' => '3273',
            'id_provinsi' => $prov->id_provinsi,
        ]);

        $tarifCount = MasterTarif::where('nama_template', 'Reguler Jawa Barat')->count();
        $this->assertEquals(2, $tarifCount);
    }

    public function test_store_master_tarif_with_nominal_fee_nakes(): void
    {
        $layanan = MasterLayanan::create([
            'nama_layanan' => 'Layanan Nominal Test',
            'harga' => 200000,
            'is_active' => true,
        ]);

        $payload = [
            'nama_template' => 'Tarif Nominal Nakes',
            'id_layanan' => $layanan->id_layanan,
            'tarif_pasien' => 200000,
            'fee_nakes_tipe' => 'nominal',
            'fee_nakes_nilai' => 150000,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/master-tarif', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('master_tarif', [
            'nama_template' => 'Tarif Nominal Nakes',
            'fee_nakes_tipe' => 'nominal',
            'fee_nakes_nilai' => 150000,
            'fee_nakes_nominal' => 150000,
            'fee_platform_nominal' => 50000,
        ]);
    }
}
