<?php

namespace App\Http\Controllers;

use App\Models\MasterUniversitas;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MasterUniversitasController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => MasterUniversitas::orderBy('nama_universitas')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_universitas' => ['required', 'string', 'max:255', 'unique:master_universitas,nama_universitas'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['is_active'] = $request->has('is_active')
            ? $request->boolean('is_active')
            : true;

        return response()->json([
            'success' => true,
            'message' => 'Universitas berhasil ditambahkan',
            'data' => MasterUniversitas::create($validated),
        ], 201);
    }

    public function show(MasterUniversitas $universita)
    {
        return response()->json([
            'success' => true,
            'data' => $universita,
        ]);
    }

    public function update(Request $request, MasterUniversitas $universita)
    {
        $validated = $request->validate([
            'nama_universitas' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('master_universitas', 'nama_universitas')
                    ->ignore($universita->id_universitas, 'id_universitas'),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $universita->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Universitas berhasil diperbarui',
            'data' => $universita->fresh(),
        ]);
    }

    public function destroy(MasterUniversitas $universita)
    {
        $universita->delete();

        return response()->json([
            'success' => true,
            'message' => 'Universitas berhasil dihapus',
        ]);
    }
}
