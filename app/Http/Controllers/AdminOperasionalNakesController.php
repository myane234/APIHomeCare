<?php

namespace App\Http\Controllers;

use App\Models\JadwalKerja;
use App\Models\OperasionalNakes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


/**
 * @group CRUD Operasional data nakes
 */
class AdminOperasionalNakesController extends Controller
{
    public function index(Request $request)
    {
        $query = OperasionalNakes::with(['tenagaMedis', 'wilayahLayanan'])->latest();

        if ($request->filled('status') && in_array($request->status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $request->status);
        }

        $data = $query->get();
        return response()->json(['success' => true, 'total' => $data->count(), 'data' => $data]);
    }

    public function show($id)
    {
        return response()->json([
            'success' => true,
            'data' => OperasionalNakes::with(['tenagaMedis', 'wilayahLayanan'])->findOrFail($id),
        ]);
    }

    public function approve($id)
    {
        $operasional = OperasionalNakes::findOrFail($id);
        if ($operasional->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Pengajuan ini sudah diproses.'], 400);
        }

        DB::transaction(function () use ($operasional) {
            $tenagaMedis = $operasional->tenagaMedis()->lockForUpdate()->firstOrFail();
            abort_if($tenagaMedis->status !== 'approved', 409, 'Nakes sudah tidak aktif.');

            $tenagaMedis->update(['id_wilayah_layanan' => $operasional->id_wilayah_layanan]);
            $tenagaMedis->kategoriLayanan()->sync($operasional->kategori_layanan);

            JadwalKerja::where('id_tenaga_medis', $tenagaMedis->id_tenaga_medis)->delete();
            foreach ($operasional->waktu_layanan as $jadwal) {
                JadwalKerja::create([
                    'id_tenaga_medis' => $tenagaMedis->id_tenaga_medis,
                    'hari' => $jadwal['hari'],
                    'jam_mulai' => $jadwal['jam_mulai'],
                    'jam_selesai' => $jadwal['jam_selesai'],
                ]);
            }

            $operasional->update(['status' => 'approved', 'admin_notes' => null]);
        });

        return response()->json(['success' => true, 'message' => 'Perubahan operasional Nakes disetujui.']);
    }

    public function reject(Request $request, $id)
    {
        $validated = $request->validate(['admin_notes' => ['required', 'string', 'max:1000']]);
        $operasional = OperasionalNakes::findOrFail($id);

        if ($operasional->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Pengajuan ini sudah diproses.'], 400);
        }

        $operasional->update(['status' => 'rejected', 'admin_notes' => $validated['admin_notes']]);
        return response()->json(['success' => true, 'message' => 'Pengajuan operasional Nakes ditolak.', 'data' => $operasional]);
    }
}
