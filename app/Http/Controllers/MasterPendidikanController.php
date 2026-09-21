<?php

namespace App\Http\Controllers;

use App\Models\MasterPendidikan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @group Master Pendidikan
 */
class MasterPendidikanController extends Controller
{
    public function index(Request $request)
    {
        [$data, $pagination] = $this->paginateQuery(MasterPendidikan::orderBy('nama_pendidikan'), $request);

        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => $pagination,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_pendidikan' => ['required', 'string', 'max:100', 'unique:master_pendidikan,nama_pendidikan'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pendidikan berhasil ditambahkan',
            'data' => MasterPendidikan::create($validated),
        ], 201);
    }

    public function show(MasterPendidikan $pendidikan)
    {
        return response()->json([
            'success' => true,
            'data' => $pendidikan,
        ]);
    }

    public function update(Request $request, MasterPendidikan $pendidikan)
    {
        $validated = $request->validate([
            'nama_pendidikan' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('master_pendidikan', 'nama_pendidikan')
                    ->ignore($pendidikan->id_pendidikan, 'id_pendidikan'),
            ],
        ]);

        $pendidikan->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pendidikan berhasil diperbarui',
            'data' => $pendidikan->fresh(),
        ]);
    }

    public function destroy(MasterPendidikan $pendidikan)
    {
        $pendidikan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pendidikan berhasil dihapus',
        ]);
    }
}