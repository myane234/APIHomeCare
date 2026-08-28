<?php

namespace App\Http\Controllers;

use App\Models\OperasionalNakes;
use App\Models\TenagaMedis;
use Illuminate\Http\Request;

/**
 * @group Nakes Operasional Management
 */

class NakesOperasionalController extends Controller
{
    private function tenagaMedis(Request $request): ?TenagaMedis
    {
        $user = $request->user();

        return $user ? TenagaMedis::where('id_user', $user->id_user)
            ->where('status', 'approved')
            ->first() : null;
    }

    public function index(Request $request)
    {
        $tenagaMedis = $this->tenagaMedis($request);
        if (!$tenagaMedis) {
            return response()->json(['success' => false, 'message' => 'Hanya Nakes aktif yang dapat mengakses data operasional.'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => OperasionalNakes::with('wilayahLayanan')
                ->where('id_tenaga_medis', $tenagaMedis->id_tenaga_medis)
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $tenagaMedis = $this->tenagaMedis($request);
        if (!$tenagaMedis) {
            return response()->json(['success' => false, 'message' => 'Hanya Nakes aktif yang dapat mengajukan perubahan.'], 403);
        }

        $validated = $request->validate([
            'id_wilayah_layanan' => ['required', 'integer', 'exists:master_provinsi,id_provinsi'],
            'kategori_layanan' => ['required', 'array', 'min:1'],
            'kategori_layanan.*' => ['integer', 'exists:kategori_layanans,id_kategori_layanan'],
            'waktu_layanan' => ['required', 'array', 'min:1'],
            'waktu_layanan.*.hari' => ['required', 'in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu'],
            'waktu_layanan.*.jam_mulai' => ['required', 'date_format:H:i'],
            'waktu_layanan.*.jam_selesai' => ['required', 'date_format:H:i', 'after:waktu_layanan.*.jam_mulai'],
        ]);

        $existing = OperasionalNakes::where('id_tenaga_medis', $tenagaMedis->id_tenaga_medis)
            ->where('status', 'pending')->exists();
        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Masih ada pengajuan operasional yang menunggu persetujuan admin.'], 409);
        }

        $operasional = OperasionalNakes::create([
            'id_tenaga_medis' => $tenagaMedis->id_tenaga_medis,
            'id_wilayah_layanan' => $validated['id_wilayah_layanan'],
            'kategori_layanan' => array_values($validated['kategori_layanan']),
            'waktu_layanan' => $validated['waktu_layanan'],
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Perubahan operasional berhasil diajukan dan menunggu persetujuan admin.',
            'data' => $operasional,
        ], 201);
    }
}
