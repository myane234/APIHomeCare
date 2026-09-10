<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chat;
use App\Models\KategoriLayanan;
use App\Models\MasterLayanan;
use App\Models\Pasien;
use App\Models\Role;
use App\Models\TenagaMedis;
use App\Models\Users;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminChatRoomsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_get_paginated_chat_rooms_without_waiting_for_nakes_chat(): void
    {
        $adminUser = Users::create([
            'email' => 'admin_chat@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $roleAdmin = Role::firstOrCreate(['nama_role' => 'admin']);
        $adminUser->roles()->attach($roleAdmin);

        $pasienUser = Users::create([
            'email' => 'pasien_chat@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $pasien = Pasien::create([
            'id_user' => $pasienUser->id_user,
            'nama_lengkap' => 'Pasien Chat Test',
            'nik' => '1111222233334444',
            'jenis_kelamin' => 'L',
            'alamat_utama' => 'Alamat Pasien',
        ]);

        $nakesUser = Users::create([
            'email' => 'nakes_chat@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $nakesPasien = Pasien::create([
            'id_user' => $nakesUser->id_user,
            'nama_lengkap' => 'Nakes Chat Test',
            'nik' => '5555666677778888',
            'jenis_kelamin' => 'P',
            'alamat_utama' => 'Alamat Nakes',
        ]);
        $nakes = TenagaMedis::create([
            'id_user' => $nakesUser->id_user,
            'id_pasien' => $nakesPasien->id_pasien,
            'nama_lengkap' => 'Nakes Chat Ready',
            'nik' => '5555666677778888',
            'jenis_tenaga_medis' => 'Perawat',
            'no_str' => 'STR-999',
            'latitude' => -6.200,
            'longitude' => 106.816,
            'status' => 'approved',
        ]);

        $kategori = KategoriLayanan::create(['nama_kategori' => 'Umum']);
        $layanan = MasterLayanan::create([
            'id_kategori_layanan' => $kategori->id_kategori_layanan,
            'nama_layanan' => 'Layanan Rawat',
            'harga' => 100000,
            'tipe_layanan' => 'tindakan',
        ]);

        $booking = Booking::create([
            'booking_code' => 'B-CHAT-001',
            'id_pasien' => $pasien->id_pasien,
            'id_layanan' => $layanan->id_layanan,
            'id_tenaga_medis' => $nakes->id_tenaga_medis,
            'tanggal_kunjungan' => '2026-09-15',
            'jam_kunjungan' => '10:00',
            'alamat_kunjungan' => 'Alamat Pasien',
            'latitude_kunjungan' => -6.200,
            'longitude_kunjungan' => 106.816,
            'status_booking' => 'DiPerjalanan',
        ]);

        $this->actingAs($adminUser, 'sanctum');

        $response = $this->getJson('/api/admin/chat-rooms?per_page=10&page=1');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.booking_id', $booking->id_booking)
            ->assertJsonPath('data.0.pasien.name', 'Pasien Chat Test')
            ->assertJsonPath('data.0.nakes.name', 'Nakes Chat Ready');
    }

    public function test_admin_can_view_chat_room_detail_and_messages(): void
    {
        $adminUser = Users::create([
            'email' => 'admin_detail@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $pasienUser = Users::create([
            'email' => 'pasien_detail@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $pasien = Pasien::create([
            'id_user' => $pasienUser->id_user,
            'nama_lengkap' => 'Pasien Detail',
            'nik' => '1212121212121212',
            'jenis_kelamin' => 'L',
            'alamat_utama' => 'Alamat Pasien Detail',
        ]);

        $nakesUser = Users::create([
            'email' => 'nakes_detail@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $nakesPasien = Pasien::create([
            'id_user' => $nakesUser->id_user,
            'nama_lengkap' => 'Nakes Detail',
            'nik' => '3434343434343434',
            'jenis_kelamin' => 'P',
            'alamat_utama' => 'Alamat Nakes Detail',
        ]);
        $nakes = TenagaMedis::create([
            'id_user' => $nakesUser->id_user,
            'id_pasien' => $nakesPasien->id_pasien,
            'nama_lengkap' => 'Nakes Detail',
            'nik' => '3434343434343434',
            'jenis_tenaga_medis' => 'Bidan',
            'no_str' => 'STR-888',
            'latitude' => -6.200,
            'longitude' => 106.816,
            'status' => 'approved',
        ]);

        $booking = Booking::create([
            'booking_code' => 'B-DETAIL-001',
            'id_pasien' => $pasien->id_pasien,
            'id_tenaga_medis' => $nakes->id_tenaga_medis,
            'tanggal_kunjungan' => '2026-09-15',
            'jam_kunjungan' => '11:00',
            'alamat_kunjungan' => 'Alamat Pasien Detail',
            'status_booking' => 'DiPerjalanan',
        ]);

        // Create 2 chat messages
        Chat::create([
            'id_booking' => $booking->id_booking,
            'id_pengirim' => $pasienUser->id_user,
            'pesan' => 'Halo nakes, apakah sudah dekat?',
            'waktu_kirim' => now()->subMinute(),
        ]);

        Chat::create([
            'id_booking' => $booking->id_booking,
            'id_pengirim' => $nakesUser->id_user,
            'pesan' => 'Halo, sebentar lagi sampai ya.',
            'waktu_kirim' => now(),
        ]);

        $this->actingAs($adminUser, 'sanctum');

        $response = $this->getJson("/api/admin/chat-rooms/{$booking->id_booking}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.room_info.booking_id', $booking->id_booking)
            ->assertJsonPath('data.total_messages', 2)
            ->assertJsonPath('data.messages.0.sender_type', 'pasien')
            ->assertJsonPath('data.messages.0.content', 'Halo nakes, apakah sudah dekat?')
            ->assertJsonPath('data.messages.1.sender_type', 'nakes')
            ->assertJsonPath('data.messages.1.content', 'Halo, sebentar lagi sampai ya.');
    }
}
