<?php

namespace App\Http\Controllers;

use App\Models\Legality;
use Illuminate\Http\Request;

class LegalityController extends Controller
{
    /**
     * Display a listing of legal documents (Admin only).
     */
    public function index()
    {
        $legalities = Legality::orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $legalities
        ], 200);
    }

    /**
     * Store a newly created legal document (Admin only).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string|unique:legalities,key|max:255',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        $legality = Legality::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Dokumen legalitas berhasil dibuat',
            'data' => $legality
        ], 201);
    }

    /**
     * Display the specified legal document (Admin only).
     */
    public function show($id)
    {
        $legality = Legality::find($id);

        if (!$legality) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen legalitas tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $legality
        ], 200);
    }

    /**
     * Update the specified legal document (Admin only).
     */
    public function update(Request $request, $id)
    {
        $legality = Legality::find($id);

        if (!$legality) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen legalitas tidak ditemukan'
            ], 404);
        }

        $validated = $request->validate([
            'key' => 'required|string|unique:legalities,key,' . $legality->id . '|max:255',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_active' => 'nullable|boolean',
        ]);

        if ($request->has('is_active')) {
            $validated['is_active'] = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN);
        }

        $legality->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Dokumen legalitas berhasil diperbarui',
            'data' => $legality
        ], 200);
    }

    /**
     * Remove the specified legal document from storage (Admin only).
     */
    public function destroy($id)
    {
        $legality = Legality::find($id);

        if (!$legality) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen legalitas tidak ditemukan'
            ], 404);
        }

        $title = $legality->title;
        $legality->delete();

        return response()->json([
            'success' => true,
            'message' => 'Dokumen legalitas berhasil dihapus'
        ], 200);
    }

    /**
     * Get active legal document by key (Public endpoint).
     */
    public function getPublicLegality($key)
    {
        $legality = Legality::where('key', $key)->where('is_active', true)->first();

        if (!$legality) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen legalitas tidak ditemukan atau tidak aktif'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'key' => $legality->key,
                'title' => $legality->title,
                'content' => $legality->content,
                'updated_at' => $legality->updated_at,
            ]
        ], 200);
    }

    /**
     * Get list of active legal documents (Public).
     */
    public function publicList()
    {
        $legalities = Legality::where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $legalities
        ], 200);
    }
}
