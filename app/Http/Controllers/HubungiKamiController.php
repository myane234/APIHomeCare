<?php

namespace App\Http\Controllers;

use App\Models\ContentManagement;
use App\Models\GlobalConfig;
use App\Models\PesanKontak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * @group CMS Hubungi Kami / Contact Us
 *
 * Endpoint CMS untuk Pengelolaan Halaman Hubungi Kami & Pesan Masuk
 */
class HubungiKamiController extends Controller
{
    /**
     * Public API: Mengambil data konten halaman Hubungi Kami
     */
    public function getContentPublic()
    {
        $content = ContentManagement::firstOrCreate([]);
        $global = GlobalConfig::first();

        return response()->json([
            'success' => true,
            'data' => [
                'hubungi_banner'          => $content->hubungi_banner_url,
                'hubungi_banner_text'     => $content->hubungi_banner_text ?? 'Hubungi Kami',
                'hubungi_heading'         => $content->hubungi_heading ?? 'Ada Pertanyaan? Kami Siap Membantu Anda',
                'hubungi_description'     => $content->hubungi_description ?? 'Silakan tinggalkan pesan atau hubungi tim customer service kami untuk informasi lebih lanjut mengenai layanan Home Care.',
                'hubungi_phone'           => $content->hubungi_phone ?: ($global->phone_number ?? null),
                'hubungi_email'           => $content->hubungi_email ?: ($global->email ?? null),
                'hubungi_whatsapp'        => $content->hubungi_whatsapp ?: ($global->whatsapp_number ?? null),
                'hubungi_address'         => $content->hubungi_address ?: ($global->address ?? null),
                'hubungi_maps_link'       => $content->hubungi_maps_link,
                'hubungi_jam_operasional' => $content->hubungi_jam_operasional ?? 'Senin - Minggu: 08:00 - 20:00 WIB',
            ]
        ], 200);
    }

    /**
     * Public API: Pengiriman pesan/inquiry dari form Hubungi Kami
     */
    public function kirimPesan(Request $request)
    {
        $validated = $request->validate([
            'nama'   => 'required|string|max:255',
            'email'  => 'required|email|max:255',
            'no_hp'  => 'nullable|string|max:20',
            'subjek' => 'nullable|string|max:255',
            'pesan'  => 'required|string',
        ]);

        $validated['status'] = 'belum_dibaca';

        $pesan = PesanKontak::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pesan Anda berhasil terkirim. Tim kami akan segera menghubungi Anda.',
            'data'    => $pesan,
        ], 201);
    }

    /**
     * Admin API: Mengambil data setting CMS Hubungi Kami
     */
    public function getSettingsAdmin()
    {
        $content = ContentManagement::firstOrCreate([]);

        return response()->json([
            'success' => true,
            'data' => [
                'hubungi_banner'          => $content->hubungi_banner_url,
                'hubungi_banner_text'     => $content->hubungi_banner_text,
                'hubungi_heading'         => $content->hubungi_heading,
                'hubungi_description'     => $content->hubungi_description,
                'hubungi_phone'           => $content->hubungi_phone,
                'hubungi_email'           => $content->hubungi_email,
                'hubungi_whatsapp'        => $content->hubungi_whatsapp,
                'hubungi_address'         => $content->hubungi_address,
                'hubungi_maps_link'       => $content->hubungi_maps_link,
                'hubungi_jam_operasional' => $content->hubungi_jam_operasional,
            ]
        ], 200);
    }

    /**
     * Admin API: Memperbarui setting CMS Hubungi Kami
     */
    public function updateSettingsAdmin(Request $request)
    {
        $content = ContentManagement::firstOrCreate([]);

        $validated = $request->validate([
            'hubungi_banner'          => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'hubungi_banner_text'     => 'nullable|string|max:255',
            'hubungi_heading'         => 'nullable|string|max:255',
            'hubungi_description'     => 'nullable|string',
            'hubungi_phone'           => 'nullable|string|max:20',
            'hubungi_email'           => 'nullable|email|max:255',
            'hubungi_whatsapp'        => 'nullable|string|max:20',
            'hubungi_address'         => 'nullable|string',
            'hubungi_maps_link'       => 'nullable|string',
            'hubungi_jam_operasional' => 'nullable|string',
        ]);

        if ($request->hasFile('hubungi_banner')) {
            if ($content->hubungi_banner) {
                Storage::disk('public')->delete($content->hubungi_banner);
            }
            $path = $request->file('hubungi_banner')->store('content', 'public');
            $validated['hubungi_banner'] = $path;
        }

        $content->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan halaman Hubungi Kami berhasil diperbarui.',
            'data' => [
                'hubungi_banner'          => $content->hubungi_banner_url,
                'hubungi_banner_text'     => $content->hubungi_banner_text,
                'hubungi_heading'         => $content->hubungi_heading,
                'hubungi_description'     => $content->hubungi_description,
                'hubungi_phone'           => $content->hubungi_phone,
                'hubungi_email'           => $content->hubungi_email,
                'hubungi_whatsapp'        => $content->hubungi_whatsapp,
                'hubungi_address'         => $content->hubungi_address,
                'hubungi_maps_link'       => $content->hubungi_maps_link,
                'hubungi_jam_operasional' => $content->hubungi_jam_operasional,
            ]
        ], 200);
    }

    /**
     * Admin API: Daftar Pesan Masuk dari Pengunjung
     */
    public function indexPesan(Request $request)
    {
        $query = PesanKontak::query();

        // Filter Status: belum_dibaca, sudah_dibaca, dibalas
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->filled('search') || $request->filled('q')) {
            $searchTerm = $request->input('search', $request->input('q'));
            $query->where(function ($q) use ($searchTerm) {
                $q->where('nama', 'like', '%' . $searchTerm . '%')
                  ->orWhere('email', 'like', '%' . $searchTerm . '%')
                  ->orWhere('no_hp', 'like', '%' . $searchTerm . '%')
                  ->orWhere('subjek', 'like', '%' . $searchTerm . '%')
                  ->orWhere('pesan', 'like', '%' . $searchTerm . '%');
            });
        }

        $query->orderBy('created_at', 'desc');

        $perPage = $request->input('per_page', 10);
        if ($perPage === 'all') {
            $data = $query->get();
        } else {
            $perPage = (int) $perPage > 0 ? (int) $perPage : 10;
            $data = $query->paginate($perPage);
        }

        $totalBelumDibaca = PesanKontak::where('status', 'belum_dibaca')->count();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar pesan masuk',
            'unread_count' => $totalBelumDibaca,
            'data' => $data,
        ], 200);
    }

    /**
     * Admin API: Detail Pesan Masuk (Auto update status menjadi 'sudah_dibaca')
     */
    public function showPesan($id)
    {
        $pesan = PesanKontak::find($id);

        if (!$pesan) {
            return response()->json([
                'success' => false,
                'message' => 'Pesan tidak ditemukan.',
            ], 404);
        }

        if ($pesan->status === 'belum_dibaca') {
            $pesan->status = 'sudah_dibaca';
            $pesan->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil detail pesan',
            'data'    => $pesan,
        ], 200);
    }

    /**
     * Admin API: Update Status / Catatan Pesan
     */
    public function updatePesanStatus(Request $request, $id)
    {
        $pesan = PesanKontak::find($id);

        if (!$pesan) {
            return response()->json([
                'success' => false,
                'message' => 'Pesan tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'status'        => 'required|in:belum_dibaca,sudah_dibaca,dibalas',
            'catatan_admin' => 'nullable|string',
        ]);

        $pesan->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Status pesan berhasil diperbarui.',
            'data'    => $pesan,
        ], 200);
    }

    /**
     * Admin API: Hapus Pesan
     */
    public function destroyPesan($id)
    {
        $pesan = PesanKontak::find($id);

        if (!$pesan) {
            return response()->json([
                'success' => false,
                'message' => 'Pesan tidak ditemukan.',
            ], 404);
        }

        $pesan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pesan berhasil dihapus.',
        ], 200);
    }
}
