<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @group Tag Artikel
 *
 * Endpoint Master Data Tag Artikel
 */
class TagController extends Controller
{
    /**
     * Tampilkan daftar semua tag.
     */
    public function index(Request $request)
    {
        $query = Tag::withCount('artikels');

        if ($request->has('search')) {
            $query->where('nama_tag', 'like', '%' . $request->search . '%')
                  ->orWhere('slug', 'like', '%' . $request->search . '%');
        }

        $tags = $query->orderBy('nama_tag', 'asc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil data Tag',
            'data'    => $tags,
        ], 200);
    }

    /**
     * Tambah tag baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_tag' => ['required', 'string', 'max:100', 'unique:tags,nama_tag'],
            'slug'     => ['nullable', 'string', 'max:100', 'unique:tags,slug'],
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['nama_tag']);
        }

        $tag = Tag::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Tag berhasil ditambahkan',
            'data'    => $tag,
        ], 201);
    }

    /**
     * Tampilkan detail tag beserta artikelnya.
     */
    public function show($id)
    {
        $tag = Tag::withCount('artikels')->where('id_tag', $id)->orWhere('slug', $id)->firstOrFail();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil detail Tag',
            'data'    => $tag,
        ], 200);
    }

    /**
     * Update data tag.
     */
    public function update(Request $request, $id)
    {
        $tag = Tag::findOrFail($id);

        $validated = $request->validate([
            'nama_tag' => ['sometimes', 'required', 'string', 'max:100', 'unique:tags,nama_tag,' . $id . ',id_tag'],
            'slug'     => ['sometimes', 'required', 'string', 'max:100', 'unique:tags,slug,' . $id . ',id_tag'],
        ]);

        if (isset($validated['nama_tag']) && empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['nama_tag']);
        }

        $tag->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Tag berhasil diupdate',
            'data'    => $tag,
        ], 200);
    }

    /**
     * Hapus tag.
     */
    public function destroy($id)
    {
        $tag = Tag::findOrFail($id);
        $tag->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tag berhasil dihapus',
        ], 200);
    }
}
