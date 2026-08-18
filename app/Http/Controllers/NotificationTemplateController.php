<?php

namespace App\Http\Controllers;

use App\Models\NotificationTemplate;
use Illuminate\Http\Request;

class NotificationTemplateController extends Controller
{
    /**
     * Tampilkan semua template notifikasi.
     */
    public function index()
    {
        $templates = NotificationTemplate::all();
        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar template notifikasi',
            'data' => $templates
        ], 200);
    }

    /**
     * Tambah template notifikasi baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', 'unique:notification_templates,code'],
            'name' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'channel' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $template = NotificationTemplate::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Template notifikasi berhasil ditambahkan',
            'data' => $template
        ], 201);
    }

    /**
     * Tampilkan detail template notifikasi.
     */
    public function show($id)
    {
        // Mendukung pencarian berdasarkan ID ataupun Code unik
        $template = NotificationTemplate::where('id', $id)
            ->orWhere('code', $id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil detail template notifikasi',
            'data' => $template
        ], 200);
    }

    /**
     * Update template notifikasi.
     */
    public function update(Request $request, $id)
    {
        $template = NotificationTemplate::where('id', $id)
            ->orWhere('code', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', 'unique:notification_templates,code,' . $template->id],
            'name' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'channel' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $template->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Template notifikasi berhasil diupdate',
            'data' => $template
        ], 200);
    }

    /**
     * Hapus template notifikasi.
     */
    public function destroy($id)
    {
        $template = NotificationTemplate::where('id', $id)
            ->orWhere('code', $id)
            ->firstOrFail();
            
        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template notifikasi berhasil dihapus'
        ], 200);
    }
}
