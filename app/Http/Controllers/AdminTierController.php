<?php

namespace App\Http\Controllers;

use App\Models\AdminTier;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class AdminTierController extends Controller
{
    /**
     * Get all admin tiers
     */
    public function index()
    {
        $tiers = AdminTier::all();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil Mengambil Data Tier Admin',
            'data' => $tiers
        ]);
    }

    /**
     * Store a new admin tier
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_tier' => ['required', 'string', 'max:255', 'unique:admin_tiers,nama_tier'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:admin_tiers,slug'],
            'description' => ['nullable', 'string'],
            'deskripsi' => ['nullable', 'string'],
            'permissions' => ['nullable', 'array'],
        ]);

        try {
            $slug = !empty($validated['slug'])
                ? Str::slug($validated['slug'])
                : Str::slug($validated['nama_tier']);

            $deskripsi = $validated['deskripsi'] ?? $validated['description'] ?? null;
            $permissions = $validated['permissions'] ?? [];

            $tier = AdminTier::create([
                'nama_tier' => $validated['nama_tier'],
                'slug' => $slug,
                'deskripsi' => $deskripsi,
                'permissions' => $permissions,
                'is_protected' => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Berhasil Membuat Tier Admin Baru',
                'data' => $tier
            ], 201);
        } catch (Exception $e) {
            Log::error('Error Store Admin Tier: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal Membuat Tier Admin: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show admin tier detail
     */
    public function show($id)
    {
        $tier = is_numeric($id)
            ? AdminTier::find($id)
            : AdminTier::where('slug', $id)->orWhere('nama_tier', $id)->first();

        if (!$tier) {
            return response()->json([
                'success' => false,
                'message' => 'Tier Admin tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil Mengambil Detail Tier Admin',
            'data' => $tier
        ], 200);
    }

    /**
     * Update an existing admin tier
     */
    public function update(Request $request, $id)
    {
        $tier = is_numeric($id)
            ? AdminTier::find($id)
            : AdminTier::where('slug', $id)->orWhere('nama_tier', $id)->first();

        if (!$tier) {
            return response()->json([
                'success' => false,
                'message' => 'Tier Admin tidak ditemukan'
            ], 404);
        }

        $validated = $request->validate([
            'nama_tier' => ['sometimes', 'required', 'string', 'max:255', 'unique:admin_tiers,nama_tier,' . $tier->id_admin_tier . ',id_admin_tier'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'unique:admin_tiers,slug,' . $tier->id_admin_tier . ',id_admin_tier'],
            'description' => ['nullable', 'string'],
            'deskripsi' => ['nullable', 'string'],
            'permissions' => ['nullable', 'array'],
        ]);

        try {
            if (isset($validated['nama_tier']) && !$tier->is_protected) {
                $tier->nama_tier = $validated['nama_tier'];
            }

            if (isset($validated['slug']) && !$tier->is_protected && !empty($validated['slug'])) {
                $tier->slug = Str::slug($validated['slug']);
            }

            if (array_key_exists('deskripsi', $validated) || array_key_exists('description', $validated)) {
                $tier->deskripsi = $validated['deskripsi'] ?? $validated['description'] ?? $tier->deskripsi;
            }

            if (array_key_exists('permissions', $validated)) {
                $tier->permissions = $validated['permissions'] ?? [];
            }

            $tier->save();

            return response()->json([
                'success' => true,
                'message' => 'Berhasil Memperbarui Tier Admin',
                'data' => $tier
            ], 200);
        } catch (Exception $e) {
            Log::error('Error Update Admin Tier: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal Memperbarui Tier Admin: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an admin tier
     */
    public function destroy($id)
    {
        $tier = is_numeric($id)
            ? AdminTier::find($id)
            : AdminTier::where('slug', $id)->orWhere('nama_tier', $id)->first();

        if (!$tier) {
            return response()->json([
                'success' => false,
                'message' => 'Tier Admin tidak ditemukan'
            ], 404);
        }

        if ($tier->is_protected || in_array(strtolower($tier->slug), ['super-admin', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Tier bawaan sistem tidak dapat dihapus'
            ], 403);
        }

        try {
            $tier->delete();

            return response()->json([
                'success' => true,
                'message' => 'Berhasil Menghapus Tier Admin'
            ], 200);
        } catch (Exception $e) {
            Log::error('Error Destroy Admin Tier: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal Menghapus Tier Admin: ' . $e->getMessage()
            ], 500);
        }
    }
}
