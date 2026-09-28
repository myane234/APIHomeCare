<?php

namespace App\Http\Controllers;

use App\Models\Ulasan;
use App\Models\ContentManagement;
use App\Models\Booking;
use App\Models\TenagaMedis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * @group CMS Ulasan / Testimonial
 *
 * Endpoint CMS untuk Ulasan Pelanggan / Pasien
 */
class UlasanController extends Controller
{
    /**
     * Public API: Mengambil daftar ulasan yang aktif/terpublikasi
     */
    public function indexPublic(Request $request)
    {
        $content = ContentManagement::firstOrCreate([]);

        $query = Ulasan::with('layanan')
            ->where('is_published', true);

        // Filter Rating
        if ($request->filled('rating')) {
            $query->where('rating', (int) $request->rating);
        }

        // Pencarian (Search)
        if ($request->filled('search') || $request->filled('q')) {
            $searchTerm = $request->input('search', $request->input('q'));
            $query->where(function ($q) use ($searchTerm) {
                $q->where('nama_pengulas', 'like', '%' . $searchTerm . '%')
                  ->orWhere('profesi_peran', 'like', '%' . $searchTerm . '%')
                  ->orWhere('komentar', 'like', '%' . $searchTerm . '%');
            });
        }

        // Pengurutan
        $query->orderBy('urutan', 'asc')->orderBy('created_at', 'desc');

        // Pagination
        $perPage = $request->input('per_page', 10);
        if ($perPage === 'all') {
            $data = $query->get();
        } else {
            $perPage = (int) $perPage > 0 ? (int) $perPage : 10;
            $data = $query->paginate($perPage);
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar ulasan',
            'ulasan_heading' => $content->ulasan_heading ?? 'Apa Kata Mereka tentang Kami',
            'ulasan_subheading' => $content->ulasan_subheading ?? 'Ulasan jujur dari pasien dan keluarga yang telah menggunakan layanan Home Care kami.',
            'data' => $data,
        ], 200);
    }

    /**
     * Protected API: Mengambil informasi user login untuk auto-fill form ulasan
     */
    public function userInfo(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $pasien = $user->pasien;
        $namaPengulas = $pasien?->nama_lengkap ?? $user->email;
        $avatar = $pasien?->avatar ?? $user->avatar;

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil informasi user untuk ulasan',
            'data' => [
                'id_user'       => $user->id_user,
                'email'         => $user->email,
                'nama_pengulas' => $namaPengulas,
                'foto'          => $avatar,
                'foto_url'      => $avatar ? (str_starts_with($avatar, 'http') ? $avatar : url(Storage::url($avatar))) : null,
            ],
        ], 200);
    }

    /**
     * Authenticated API: Pengiriman Ulasan oleh Pasien / Pengunjung yang sudah Login
     */
    public function storePublic(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'nama_pengulas' => 'nullable|string|max:255',
            'profesi_peran' => 'nullable|string|max:255',
            'foto'          => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'rating'        => 'required|integer|min:1|max:5',
            'komentar'      => 'required|string',
            'layanan_id'    => 'nullable|exists:master_layanan,id_master_layanan',
        ]);

        if ($user) {
            $validated['id_user'] = $user->id_user;
            $validated['email']   = $user->email;
            $pasien = $user->pasien;
            if (empty($validated['nama_pengulas'])) {
                $validated['nama_pengulas'] = $pasien?->nama_lengkap ?? $user->email;
            }
        }

        if (empty($validated['nama_pengulas'])) {
            $validated['nama_pengulas'] = 'Pengunjung Portal';
        }

        if (empty($validated['profesi_peran'])) {
            $validated['profesi_peran'] = 'Pasien';
        }

        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('ulasan', 'public');
            $validated['foto'] = $path;
        } elseif ($user) {
            $pasien = $user->pasien;
            $validated['foto'] = $pasien?->avatar ?? $user->avatar;
        }

        $validated['is_published'] = true;
        $validated['urutan'] = 0;

        $ulasan = Ulasan::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Terima kasih! Ulasan Anda berhasil dikirim.',
            'data'    => $ulasan->load('layanan'),
        ], 201);
    }

    /**
     * Admin API: Daftar Semua Ulasan (Publik & Pending Moderasi)
     */
    public function indexAdmin(Request $request)
    {
        $query = Ulasan::with(['layanan', 'user']);

        // Filter Status Publikasi
        if ($request->filled('is_published')) {
            $isPublished = filter_var($request->is_published, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_published', $isPublished);
        }

        // Filter Rating
        if ($request->filled('rating')) {
            $query->where('rating', (int) $request->rating);
        }

        // Search
        if ($request->filled('search') || $request->filled('q')) {
            $searchTerm = $request->input('search', $request->input('q'));
            $query->where(function ($q) use ($searchTerm) {
                $q->where('nama_pengulas', 'like', '%' . $searchTerm . '%')
                  ->orWhere('email', 'like', '%' . $searchTerm . '%')
                  ->orWhere('profesi_peran', 'like', '%' . $searchTerm . '%')
                  ->orWhere('komentar', 'like', '%' . $searchTerm . '%');
            });
        }

        $query->orderBy('urutan', 'asc')->orderBy('created_at', 'desc');

        $perPage = $request->input('per_page', 10);
        if ($perPage === 'all') {
            $data = $query->get();
        } else {
            $perPage = (int) $perPage > 0 ? (int) $perPage : 10;
            $data = $query->paginate($perPage);
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil data ulasan (Admin)',
            'data'    => $data,
        ], 200);
    }

    /**
     * Admin API: Tambah Ulasan Manual
     */
    public function storeAdmin(Request $request)
    {
        $validated = $request->validate([
            'nama_pengulas' => 'required|string|max:255',
            'email'         => 'nullable|email|max:255',
            'profesi_peran' => 'nullable|string|max:255',
            'foto'          => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'rating'        => 'required|integer|min:1|max:5',
            'komentar'      => 'required|string',
            'layanan_id'    => 'nullable|exists:master_layanan,id_master_layanan',
            'is_published'  => 'nullable',
            'urutan'        => 'nullable|integer',
        ]);

        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('ulasan', 'public');
            $validated['foto'] = $path;
        }

        if ($request->has('is_published')) {
            $validated['is_published'] = filter_var($request->is_published, FILTER_VALIDATE_BOOLEAN);
        } else {
            $validated['is_published'] = true;
        }

        $validated['urutan'] = $request->input('urutan', 0);

        $ulasan = Ulasan::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Ulasan berhasil ditambahkan oleh Admin',
            'data'    => $ulasan->load(['layanan', 'user']),
        ], 201);
    }

    /**
     * Admin API: Detail Ulasan
     */
    public function show($id)
    {
        $ulasan = Ulasan::with(['layanan', 'user'])->find($id);

        if (!$ulasan) {
            return response()->json([
                'success' => false,
                'message' => 'Data ulasan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil detail ulasan',
            'data'    => $ulasan,
        ], 200);
    }

    /**
     * Admin API: Update Ulasan
     */
    public function updateAdmin(Request $request, $id)
    {
        $ulasan = Ulasan::find($id);

        if (!$ulasan) {
            return response()->json([
                'success' => false,
                'message' => 'Data ulasan tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'nama_pengulas' => 'nullable|string|max:255',
            'profesi_peran' => 'nullable|string|max:255',
            'foto'          => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'rating'        => 'nullable|integer|min:1|max:5',
            'komentar'      => 'nullable|string',
            'layanan_id'    => 'nullable|exists:master_layanan,id_master_layanan',
            'is_published'  => 'nullable',
            'urutan'        => 'nullable|integer',
            'remove_foto'   => 'nullable',
        ]);

        if ($request->hasFile('foto')) {
            if ($ulasan->foto) {
                Storage::disk('public')->delete($ulasan->foto);
            }
            $path = $request->file('foto')->store('ulasan', 'public');
            $validated['foto'] = $path;
        } elseif ($request->has('remove_foto') && filter_var($request->remove_foto, FILTER_VALIDATE_BOOLEAN)) {
            if ($ulasan->foto) {
                Storage::disk('public')->delete($ulasan->foto);
            }
            $validated['foto'] = null;
        }

        if ($request->has('is_published')) {
            $validated['is_published'] = filter_var($request->is_published, FILTER_VALIDATE_BOOLEAN);
        }

        $ulasan->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Ulasan berhasil diperbarui',
            'data'    => $ulasan->fresh(['layanan']),
        ], 200);
    }

    /**
     * Admin API: Toggle Publish/Unpublish Status
     */
    public function togglePublish($id)
    {
        $ulasan = Ulasan::find($id);

        if (!$ulasan) {
            return response()->json([
                'success' => false,
                'message' => 'Data ulasan tidak ditemukan.',
            ], 404);
        }

        $ulasan->is_published = !$ulasan->is_published;
        $ulasan->save();

        return response()->json([
            'success' => true,
            'message' => $ulasan->is_published ? 'Ulasan berhasil dipublikasikan' : 'Ulasan berhasil disembunyikan',
            'data'    => $ulasan,
        ], 200);
    }

    /**
     * Admin API: Hapus Ulasan
     */
    public function destroy($id)
    {
        $ulasan = Ulasan::find($id);

        if (!$ulasan) {
            return response()->json([
                'success' => false,
                'message' => 'Data ulasan tidak ditemukan.',
            ], 404);
        }

        if ($ulasan->foto) {
            Storage::disk('public')->delete($ulasan->foto);
        }

        $ulasan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ulasan berhasil dihapus.',
        ], 200);
    }

    /**
     * Authenticated Patient API: Mengecek booking yang berstatus 'Selesai' dan belum diulas (Pending Mini Review)
     */
    public function getPendingReviews(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $pasien = $user->pasien;
        if (!$pasien) {
            return response()->json([
                'success' => true,
                'message' => 'Tidak ada booking yang memerlukan ulasan',
                'data'    => [],
            ], 200);
        }

        // Cari booking dengan status Selesai yang belum memiliki record ulasan
        $pendingBookings = Booking::with([
                'layanan',
                'tenagaMedis',
                'layananItems.layanan',
            ])
            ->where('id_pasien', $pasien->id_pasien)
            ->where('status_booking', 'Selesai')
            ->doesntHave('ulasan')
            ->orderBy('tanggal_kunjungan', 'desc')
            ->orderBy('jam_kunjungan', 'desc')
            ->get();

        $formatted = $pendingBookings->map(function ($booking) {
            $nakes = $booking->tenagaMedis;
            $fotoNakes = $nakes?->foto_profile ?? $nakes?->pas_foto;
            $fotoNakesUrl = $fotoNakes ? (str_starts_with($fotoNakes, 'http') ? $fotoNakes : url(Storage::url($fotoNakes))) : null;

            return [
                'id_booking'             => $booking->id_booking,
                'booking_code'           => $booking->booking_code,
                'tanggal_kunjungan'      => $booking->tanggal_kunjungan,
                'jam_kunjungan'          => $booking->jam_kunjungan,
                'id_layanan'             => $booking->id_layanan,
                'nama_layanan'           => $booking->layanan?->nama_layanan ?? 'Pelayanan Home Care',
                'tenaga_medis'           => $nakes ? [
                    'id_tenaga_medis'    => $nakes->id_tenaga_medis,
                    'nama_lengkap'       => $nakes->nama_lengkap,
                    'jenis_tenaga_medis' => $nakes->jenis_tenaga_medis,
                    'foto_url'           => $fotoNakesUrl,
                ] : null,
            ];
        });

        // Ambil konfigurasi UI mini ulasan agar FE bisa langsung render
        $content = ContentManagement::firstOrCreate([]);

        return response()->json([
            'success'     => true,
            'message'     => 'Berhasil memeriksa status ulasan pelayanan',
            'has_pending' => $formatted->isNotEmpty(),
            'ui_config'   => $content->mini_ulasan_formatted,
            'data'        => $formatted,
        ], 200);
    }

    /**
     * Authenticated Patient API: Mengirimkan Mini Ulasan Pasca Pelayanan untuk Booking tertentu
     */
    public function storeMiniUlasan(Request $request, $id_booking)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $booking = Booking::with(['tenagaMedis', 'layanan', 'ulasan'])->find($id_booking);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Data booking tidak ditemukan.',
            ], 404);
        }

        // Cek kepemilikan booking (jika user adalah pasien)
        $pasien = $user->pasien;
        if ($pasien && $booking->id_pasien != $pasien->id_pasien && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk mengulas booking ini.',
            ], 403);
        }

        // Cek status booking
        if ($booking->status_booking !== 'Selesai') {
            return response()->json([
                'success' => false,
                'message' => 'Ulasan hanya dapat diberikan setelah pelayanan berstatus Selesai.',
            ], 422);
        }

        // Cek apakah booking sudah pernah diulas
        if ($booking->ulasan) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah memberikan ulasan untuk pelayanan ini.',
                'data'    => $booking->ulasan,
            ], 409);
        }

        $validated = $request->validate([
            'rating'        => 'required|integer|min:1|max:5',
            'komentar'      => 'nullable|string',
            'quick_tags'    => 'nullable|array',
            'quick_tags.*'  => 'string|max:100',
            'foto'          => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'nama_pengulas' => 'nullable|string|max:255',
            'profesi_peran' => 'nullable|string|max:255',
        ]);

        $namaPengulas = $validated['nama_pengulas'] ?? $pasien?->nama_lengkap ?? $user->name ?? $user->email;
        $profesiPeran = $validated['profesi_peran'] ?? 'Pasien';

        $fotoPath = null;
        if ($request->hasFile('foto')) {
            $fotoPath = $request->file('foto')->store('ulasan', 'public');
        } else {
            $fotoPath = $pasien?->avatar ?? $user->avatar;
        }

        $content = ContentManagement::firstOrCreate([]);
        $uiConfig = $content->mini_ulasan_formatted;

        $ulasan = Ulasan::create([
            'id_user'         => $user->id_user,
            'id_booking'      => $booking->id_booking,
            'id_tenaga_medis' => $booking->id_tenaga_medis,
            'layanan_id'      => $booking->id_layanan,
            'nama_pengulas'   => $namaPengulas,
            'email'           => $user->email,
            'profesi_peran'   => $profesiPeran,
            'foto'            => $fotoPath,
            'rating'          => $validated['rating'],
            'komentar'        => $validated['komentar'] ?? 'Pelayanan sangat baik dan memuaskan.',
            'quick_tags'      => $validated['quick_tags'] ?? [],
            'is_published'    => true,
            'urutan'          => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => $uiConfig['success_message'] ?? 'Terima kasih! Ulasan Anda berhasil dikirim.',
            'data'    => $ulasan->load(['booking', 'tenagaMedis', 'layanan']),
        ], 201);
    }
}
