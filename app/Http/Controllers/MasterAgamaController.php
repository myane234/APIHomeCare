<?php

namespace App\Http\Controllers;

use App\Models\MasterAgama;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @group master agama controller
 */
class MasterAgamaController extends Controller
{
    public function index(Request $request)
    {
        [$data, $pagination] = $this->paginateQuery(MasterAgama::orderBy('nama_agama'), $request);

        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => $pagination,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_agama' => ['required', 'string', 'max:100', 'unique:master_agama,nama_agama'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Agama berhasil ditambahkan',
            'data' => MasterAgama::create($validated),
        ], 201);
    }

    public function show(MasterAgama $agama)
    {
        return response()->json([
            'success' => true,
            'data' => $agama,
        ]);
    }

    public function update(Request $request, MasterAgama $agama)
    {
        $validated = $request->validate([
            'nama_agama' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('master_agama', 'nama_agama')->ignore($agama->id_agama, 'id_agama'),
            ],
        ]);

        $agama->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Agama berhasil diperbarui',
            'data' => $agama->fresh(),
        ]);
    }

    public function destroy(MasterAgama $agama)
    {
        $agama->delete();

        return response()->json([
            'success' => true,
            'message' => 'Agama berhasil dihapus',
        ]);
    }
}