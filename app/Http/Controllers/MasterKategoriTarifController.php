<?php

namespace App\Http\Controllers;

use App\Models\MasterKategoriTarif;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MasterKategoriTarifController extends Controller
{
    private const DAYS = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'];

    private function validateTrigger(Request $request, ?MasterKategoriTarif $current = null): array
    {
        $daysInput = $request->has('hari_berlaku')
            ? $request->input('hari_berlaku', [])
            : ($current?->hari_berlaku ?? []);
        $days = collect($daysInput)
            ->map(fn ($day) => strtolower((string) $day))
            ->unique()
            ->sort()
            ->values()
            ->all();
        $start = $request->has('jam_mulai') ? $request->input('jam_mulai') : $current?->jam_mulai;
        $end = $request->has('jam_selesai') ? $request->input('jam_selesai') : $current?->jam_selesai;
        $isDefault = $request->boolean('is_default', $current?->is_default ?? false);

        if ($isDefault) {
            $days = [];
            $start = null;
            $end = null;
        } elseif ($start || $end || $days) {
            if (count($days) === 0 || !$start || !$end) {
                abort(422, 'Hari berlaku, jam mulai, dan jam selesai harus diisi bersama.');
            }
            if (array_diff($days, self::DAYS)) {
                abort(422, 'Hari berlaku tidak valid.');
            }
        }

        $query = MasterKategoriTarif::query()
            ->where('id_kategori_tarif', '!=', $current?->id_kategori_tarif ?? 0);

        if ($isDefault) {
            if ($query->where('is_default', true)->exists()) {
                abort(422, 'Hanya boleh ada satu kategori tarif default.');
            }
        } elseif ($days && $start && $end) {
            $duplicate = $query->where('is_default', false)->get()->contains(function ($item) use ($days, $start, $end) {
                $existingDays = collect($item->hari_berlaku ?? [])->map(fn ($day) => strtolower((string) $day))->sort()->values()->all();
                return $existingDays === $days
                    && substr((string) $item->jam_mulai, 0, 5) === substr($start, 0, 5)
                    && substr((string) $item->jam_selesai, 0, 5) === substr($end, 0, 5);
            });

            if ($duplicate) {
                abort(422, 'Trigger hari dan jam tersebut sudah digunakan kategori tarif lain.');
            }
        }

        return [
            'hari_berlaku' => $days ?: null,
            'jam_mulai' => $start ?: null,
            'jam_selesai' => $end ?: null,
            'is_default' => $isDefault,
        ];
    }

    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil kategori tarif',
            'data' => MasterKategoriTarif::orderByDesc('is_default')->orderBy('nama_kategori')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_kategori' => ['required', 'string', 'max:100', 'unique:master_kategori_tarif,nama_kategori'],
            'biaya_tambahan' => ['required', 'numeric', 'min:0'],
            'is_default' => ['sometimes', 'boolean'],
            'hari_berlaku' => ['nullable', 'array'],
            'hari_berlaku.*' => ['string'],
            'jam_mulai' => ['nullable', 'date_format:H:i'],
            'jam_selesai' => ['nullable', 'date_format:H:i'],
        ]);
        $validated = array_merge($validated, $this->validateTrigger($request));

        return DB::transaction(function () use ($validated) {
            if (($validated['is_default'] ?? false) === true) {
                MasterKategoriTarif::query()->update(['is_default' => false]);
            }

            $kategori = MasterKategoriTarif::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Kategori tarif berhasil ditambahkan',
                'data' => $kategori,
            ], 201);
        });
    }

    public function show(MasterKategoriTarif $masterKategoriTarif)
    {
        return response()->json([
            'success' => true,
            'message' => 'Detail kategori tarif',
            'data' => $masterKategoriTarif,
        ]);
    }

    public function update(Request $request, MasterKategoriTarif $masterKategoriTarif)
    {
        $validated = $request->validate([
            'nama_kategori' => ['sometimes', 'required', 'string', 'max:100', 'unique:master_kategori_tarif,nama_kategori,' . $masterKategoriTarif->id_kategori_tarif . ',id_kategori_tarif'],
            'biaya_tambahan' => ['sometimes', 'required', 'numeric', 'min:0'],
            'is_default' => ['sometimes', 'boolean'],
            'hari_berlaku' => ['nullable', 'array'],
            'hari_berlaku.*' => ['string'],
            'jam_mulai' => ['nullable', 'date_format:H:i'],
            'jam_selesai' => ['nullable', 'date_format:H:i'],
        ]);
        $validated = array_merge($validated, $this->validateTrigger($request, $masterKategoriTarif));

        return DB::transaction(function () use ($validated, $masterKategoriTarif) {
            if (($validated['is_default'] ?? false) === true) {
                MasterKategoriTarif::where('id_kategori_tarif', '!=', $masterKategoriTarif->getKey())
                    ->update(['is_default' => false]);
            }

            $masterKategoriTarif->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Kategori tarif berhasil diubah',
                'data' => $masterKategoriTarif->fresh(),
            ]);
        });
    }

    public function destroy(MasterKategoriTarif $masterKategoriTarif)
    {
        if ($masterKategoriTarif->masterTarifs()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori tarif tidak dapat dihapus karena masih digunakan oleh master tarif',
            ], 422);
        }

        $masterKategoriTarif->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kategori tarif berhasil dihapus',
        ]);
    }
}
