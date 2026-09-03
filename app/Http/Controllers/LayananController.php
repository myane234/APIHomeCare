<?php

namespace App\Http\Controllers;

use App\Models\KategoriLayanan;
use App\Models\MasterLayanan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

/**
 * @group CMS Layanan
 * 
 * Endpoint CMS Layanan
 */

class LayananController extends Controller
{
    /**
     * Mengambil daftar layanan medis. Endpoint ini mendukung dua cara pemanggilan:
 * 1. **Polosan (Tanpa Query)**: Mengambil semua daftar layanan secara keseluruhan.
 * 2. **Filter Kategori (Dengan Query)**: Menyaring daftar layanan berdasarkan kategori tertentu.
 *
 * @queryParam kategori string Saring layanan berdasarkan kategori. Must be one of: Fisioterapi, Kardiopulmoner, Neurologis, Muskuloskeletal, Ortopedi, Geniatri, Pasca Operasi. Example: Fisioterapi
 *
 * @response scenario="Semua Data (Polosan)" {
 *   "success": true,
 *   "message": "Berhasil Mengambil data Layanan",
 *   "data": [
 *     {
 *       "id_layanan": 1,
 *       "nama_layanan": "Fisioterapi Stroke Rumah",
 *       "kategori_layanan": "Fisioterapi",
 *       "harga": "150000.00",
 *       "durasi_menit": 60
 *     },
 *     {
 *       "id_layanan": 2,
 *       "nama_layanan": "Terapi Ortopedi",
 *       "kategori_layanan": "Ortopedi",
 *       "harga": "200000.00",
 *       "durasi_menit": 45
 *     }
 *   ]
 * }
 *
 * @response scenario="Dengan Filter Kategori" {
 *   "success": true,
 *   "message": "Berhasil Mengambil data Layanan",
 *   "data": [
 *     {
 *       "id_layanan": 1,
 *       "nama_layanan": "Fisioterapi Stroke Rumah",
 *       "kategori_layanan": "Fisioterapi",
 *       "harga": "150000.00",
 *       "durasi_menit": 60
 *     }
 *   ]
 * }
     */
    public function index(Request $request)
    {

        if($request->query('ambil_kategori') === 'true') {
            $kategori = KategoriLayanan::all();

            return response()->json([
                'success' => 'true',
                'message' => 'Barhasil Mengambil data Layanan',
                'data' => $kategori
            ], 200);
        }

        $query = MasterLayanan::with('kategori');

        if ($request->has('kategori_layanan')) {
            // Find category id by name if the frontend still passes name, or just use id
            $query->whereHas('kategori', function($q) use ($request) {
                $q->where('nama_kategori', $request->kategori_layanan)
                  ->orWhere('id_kategori_layanan', $request->kategori_layanan);
            });
        }

        // Mapping hasil query
        $hasil = $query->get()->map(function ($item) {
            // Map the relation back to property so frontend doesn't break if expecting string
            $item->kategori_layanan = $item->kategori ? $item->kategori->nama_kategori : null;
            return $item;
        });

        return response()->json([
            'success' => true,
            'message' => 'Berhasil Mengambil data Layanan',
            'data' => $hasil
        ], 200);
    }

    /**
     * Tambah layanan baru.
     */
    public function store(Request $request)
    {
        // Validasi wajib (required) untuk deskripsi dan foto
        $validated = $request->validate([
            'nama_layanan'        => ['required', 'string', 'max:255'],
            'deskripsi_layanan'   => ['required', 'string'],
            'foto_layanan'        => ['required', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'id_kategori_layanan' => ['required', 'exists:kategori_layanans,id_kategori_layanan'],
            'harga'               => ['required', 'numeric', 'min:0'],
            'include_transport'   => ['required', 'boolean'],
            'tipe_layanan'        => ['required', 'in:durasi,tindakan'],
            'durasi_menit'        => ['nullable', 'integer', 'min:1'],
        ]);

        // Handle upload file ke storage/app/public/layanan
        if ($request->hasFile('foto_layanan')) {
            $path = $request->file('foto_layanan')->store('layanan', 'public');
            $validated['foto_layanan'] = $path;
        }

        $layanan = MasterLayanan::create($validated);

        return response()->json($layanan, 201);
    }

    /**
     * Ambil detail layanan by id.
     */
    public function show($id)
    {
        $layanan = MasterLayanan::query()->findOrFail($id);
        
        return response()->json($layanan, 200);
    }

    /**
     * Edit layanan by id.
     */
     public function update(Request $request, $id)
    {
        $layanan = MasterLayanan::query()->findOrFail($id);

        $validated = $request->validate([
            'nama_layanan' => ['sometimes', 'required', 'string', 'max:255'],
            'deskripsi_layanan' => ['sometimes', 'required', 'string'],
            'foto_layanan' => ['sometimes', 'required', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'id_kategori_layanan' => ['sometimes', 'required', 'exists:kategori_layanans,id_kategori_layanan'],
            'harga' => ['sometimes', 'required', 'numeric'],
            'tipe_layanan' => ['sometimes', 'required', 'in:durasi,tindakan'],
            'durasi_menit' => ['nullable', 'integer'],
            'include_transport' => ['sometimes', 'required', 'boolean'],
        ]);

        // Handle jika ada upload foto baru untuk menggantikan foto lama
        if ($request->hasFile('foto_layanan')) {
            // Hapus foto lama dari storage biar gak menuh-menuhin server
            $rawFoto = $layanan->getRawOriginal('foto_layanan');
            if ($rawFoto) {
                Storage::disk('public')->delete($rawFoto);
            }
            
            $path = $request->file('foto_layanan')->store('layanan', 'public');
            $validated['foto_layanan'] = $path;
        }

        $layanan->fill($validated);
        $layanan->save();

        return response()->json($layanan, 200);
    }

    /**
     * Hapus layanan by id.
     */
    public function destroy($id)
    {
        $layanan = MasterLayanan::query()->findOrFail($id);
        
        $rawFoto = $layanan->getRawOriginal('foto_layanan');
        if ($rawFoto) {
            Storage::disk('public')->delete($rawFoto);
        }
        
        $layanan->delete();

        return response()->json(['message' => 'Deleted successfully'], 200);
    }
}

