<?php

namespace App\Http\Controllers;

use App\Models\Ulasan;
use App\Models\ContentManagement;
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
     * Public API: Pengiriman Ulasan oleh Pasien / Pengunjung
     */
    public function storePublic(Request $request)
    {
        $validated = $request->validate([
            'nama_pengulas' => 'required|string|max:255',
            'profesi_peran' => 'nullable|string|max:255',
            'foto'          => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'rating'        => 'required|integer|min:1|max:5',
            'komentar'      => 'required|string',
            'layanan_id'    => 'nullable|exists:master_layanan,id_master_layanan',
        ]);

        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('ulasan', 'public');
            $validated['foto'] = $path;
        }

        // Moderasi: ulasan publik default belum terpublikasi (is_published = false)
        $validated['is_published'] = false;
        $validated['urutan'] = 0;

        $ulasan = Ulasan::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Terima kasih! Ulasan Anda berhasil dikirim dan akan ditinjau oleh tim kami.',
            'data'    => $ulasan,
        ], 201);
    }

    /**
     * Admin API: Daftar Semua Ulasan (Publik & Pending Moderasi)
     */
    public function indexAdmin(Request $request)
    {
        $query = Ulasan::with('layanan');

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
            'data'    => $ulasan->load('layanan'),
        ], 201);
    }

    /**
     * Admin API: Detail Ulasan
     */
    public function show($id)
    {
        $ulasan = Ulasan::with('layanan')->find($id);

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
}
