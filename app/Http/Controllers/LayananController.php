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
     * Mengambil daftar layanan medis. Endpoint ini mendukung:
     * 1. **Polosan (Tanpa Query)**: Mengambil semua daftar layanan secara keseluruhan.
     * 2. **Filter Kategori**: `kategori_layanan` atau `kategori`.
     * 3. **Limit & Pagination**: `limit=9` atau `per_page=9`, `page=1`, `offset=0`.
     * 4. **Pencarian**: `search` / `q`.
     *
     * @queryParam kategori_layanan string Saring layanan berdasarkan kategori.
     * @queryParam limit integer Batasi jumlah data yang dikembalikan (misal: 9).
     * @queryParam offset integer Offset data untuk pagination manual.
     * @queryParam per_page integer Jumlah data per halaman jika menggunakan pagination Laravel.
     * @queryParam page integer Halaman ke-n untuk pagination Laravel.
     * @queryParam search string Kata kunci pencarian nama layanan.
     */
    public function index(Request $request)
    {
        if ($request->query('ambil_kategori') === 'true') {
            $kategori = KategoriLayanan::all();

            return response()->json([
                'success' => true,
                'message' => 'Berhasil Mengambil data Kategori Layanan',
                'data' => $kategori
            ], 200);
        }

        $query = MasterLayanan::with('kategori');

        // Filter Kategori
        $kategoriParam = $request->input('kategori_layanan', $request->input('kategori'));
        if ($kategoriParam) {
            $query->whereHas('kategori', function($q) use ($kategoriParam) {
                $q->where('nama_kategori', $kategoriParam)
                  ->orWhere('id_kategori_layanan', $kategoriParam);
            });
        }

        // Search Keyword
        if ($request->filled('search') || $request->filled('q')) {
            $searchTerm = $request->input('search', $request->input('q'));
            $query->where(function($q) use ($searchTerm) {
                $q->where('nama_layanan', 'like', '%' . $searchTerm . '%')
                  ->orWhere('deskripsi_layanan', 'like', '%' . $searchTerm . '%');
            });
        }

        // Pengurutan
        $query->orderBy('created_at', 'desc');

        // Handle Laravel Pagination (jika dipanggil dengan ?paginate=true atau ?page=N)
        if ($request->boolean('paginate') || ($request->has('page') && !$request->has('offset'))) {
            $perPage = (int) $request->input('per_page', $request->input('limit', 9));
            $paginated = $query->paginate($perPage);

            // Transform item untuk backward compatibility `kategori_layanan`
            $paginated->getCollection()->transform(function ($item) {
                $item->kategori_layanan = $item->kategori ? $item->kategori->nama_kategori : null;
                return $item;
            });

            return response()->json([
                'success' => true,
                'message' => 'Berhasil Mengambil data Layanan',
                'data' => $paginated
            ], 200);
        }

        // Handle Limit & Offset
        if ($request->has('limit')) {
            $limit = (int) $request->input('limit');
            if ($limit > 0) {
                $query->limit($limit);
            }
        }

        if ($request->has('offset')) {
            $offset = (int) $request->input('offset');
            if ($offset >= 0) {
                $query->offset($offset);
            }
        }

        // Mapping hasil query
        $hasil = $query->get()->map(function ($item) {
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

