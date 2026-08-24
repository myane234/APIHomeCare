<?php

namespace App\Http\Controllers\SuperAdminMasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterTarif;
use App\Models\MasterLayanan;
use App\Models\KotaKabupaten;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Master Tarif System
 * 
 * @group Master Data
 * @subgroup Master Tarif
 */
class SuperAdminMasterTarif extends Controller
{
    /**
     * Tampilkan semua daftar Master Tarif
     */
    public function index()
    {
        $masterTarifs = MasterTarif::with(['layanan', 'kategoriTarif', 'kota.provinsi', 'provinsi', 'layananTermasuk', 'komponenTarif'])->get();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil data Master Tarif',
            'data' => $masterTarifs
        ], 200);
    }

    /**
     * Simpan Master Tarif baru
     * 
     * Jika id_provinsi diisi tanpa id_kota, maka tarif akan dibuat otomatis untuk seluruh kota di provinsi tersebut.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_template' => ['required', 'string', 'max:255'],
            'id_kategori_tarif' => ['required', 'exists:master_kategori_tarif,id_kategori_tarif'],
            'id_layanan' => ['nullable', 'required_without:layanan_ids', 'exists:master_layanan,id_layanan'],
            'id_kategori_layanan' => ['nullable', 'exists:kategori_layanans,id_kategori_layanan'],
            'layanan_ids' => ['sometimes', 'array'],
            'layanan_ids.*' => ['integer', 'exists:master_layanan,id_layanan'],
            'komponen_tarif_ids' => ['sometimes', 'array'],
            'komponen_tarif_ids.*' => ['integer', 'exists:master_komponen_biaya,id_komponen'],
            'id_provinsi' => ['nullable', 'exists:master_provinsi,id_provinsi'],
            'id_kota' => ['nullable', 'exists:master_kota_kabupaten,id_kota'],
            'fee_nakes_tipe' => ['sometimes', 'in:nominal,persen'],
            'fee_nakes_nilai' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $feeType = $request->input('fee_nakes_tipe', 'persen');
        $feeValue = (float) $request->input('fee_nakes_nilai', 0);
        if ($feeType === 'persen' && $feeValue > 100) {
            return response()->json(['message' => 'Fee nakes dalam persen tidak boleh lebih dari 100'], 422);
        }

        // Tentukan daftar target kota/kabupaten
        $targetKotas = [];
        if ($request->filled('id_kota')) {
            $kota = KotaKabupaten::findOrFail($request->id_kota);
            if ($request->filled('id_provinsi') && (int) $request->id_provinsi !== (int) $kota->id_provinsi) {
                return response()->json(['message' => 'Kota tidak termasuk dalam provinsi yang dipilih'], 422);
            }
            $targetKotas[] = [
                'id_kota' => $kota->id_kota,
                'id_provinsi' => $kota->id_provinsi,
            ];
        } elseif ($request->filled('id_provinsi')) {
            // Jika provinsi dipilih tanpa kota, semua kota di provinsi tersebut akan otomatis dimasukkan
            $kotasInProvinsi = KotaKabupaten::where('id_provinsi', $request->id_provinsi)->get();
            if ($kotasInProvinsi->isNotEmpty()) {
                foreach ($kotasInProvinsi as $k) {
                    $targetKotas[] = [
                        'id_kota' => $k->id_kota,
                        'id_provinsi' => $k->id_provinsi,
                    ];
                }
            } else {
                $targetKotas[] = [
                    'id_kota' => null,
                    'id_provinsi' => $request->id_provinsi,
                ];
            }
        } else {
            // Berlaku Nasional
            $targetKotas[] = [
                'id_kota' => null,
                'id_provinsi' => null,
            ];
        }

        // Ambil harga dasar dari layanan untuk kalkulasi fee nominal jika tipe persen
        $layananUtama = MasterLayanan::find($request->id_layanan);
        $hargaLayanan = $layananUtama ? (float) $layananUtama->harga : 0;

        $nominalFeeNakes = $feeType === 'nominal'
            ? $feeValue
            : ($hargaLayanan * ($feeValue / 100));
        $nominalFeePlatform = max(0, $hargaLayanan - $nominalFeeNakes);

        DB::beginTransaction();
        try {
            $createdTarifs = [];

            $layananIds = array_unique(array_filter(array_merge(
                $request->filled('id_layanan') ? [(int) $request->id_layanan] : [],
                array_map('intval', $request->input('layanan_ids', [])),
                $request->filled('id_kategori_layanan')
                    ? MasterLayanan::where('id_kategori_layanan', $request->id_kategori_layanan)->pluck('id_layanan')->all()
                    : []
            )));
            if (empty($layananIds)) {
                throw new \InvalidArgumentException('Minimal satu layanan harus dipilih');
            }
            $komponenIds = $request->input('komponen_tarif_ids', []);

            foreach ($targetKotas as $target) {
                $masterTarif = MasterTarif::updateOrCreate(
                    [
                        'nama_template' => $request->nama_template,
                        'id_kategori_tarif' => $request->id_kategori_tarif,
                        'id_layanan' => $layananIds[0],
                        'id_kota' => $target['id_kota'],
                    ],
                    [
                        'id_provinsi' => $target['id_provinsi'],
                        'fee_nakes_tipe' => $feeType,
                        'fee_nakes_nilai' => $feeValue,
                        'fee_nakes_nominal' => $nominalFeeNakes,
                        'fee_platform_nominal' => $nominalFeePlatform,
                        'is_active' => $request->is_active ?? true,
                    ]
                );

                $masterTarif->layananTermasuk()->sync($layananIds);
                if ($request->has('komponen_tarif_ids')) {
                    $masterTarif->komponenTarif()->sync($komponenIds);
                }

                $masterTarif->load(['layanan', 'kategoriTarif', 'kota.provinsi', 'provinsi', 'layananTermasuk', 'komponenTarif']);
                $createdTarifs[] = $masterTarif;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Master Tarif berhasil disimpan',
                'data' => count($createdTarifs) === 1 ? $createdTarifs[0] : $createdTarifs
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan master tarif: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tampilkan detail Master Tarif berdasarkan ID
     */
    public function show($id)
    {
        $masterTarif = MasterTarif::with(['layanan', 'kategoriTarif', 'kota.provinsi', 'provinsi', 'layananTermasuk', 'komponenTarif'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail Master Tarif',
            'data' => $masterTarif
        ], 200);
    }

    /**
     * Update Master Tarif
     */
    public function update(Request $request, $id)
    {
        $masterTarif = MasterTarif::findOrFail($id);

        $validated = $request->validate([
            'nama_template' => ['sometimes', 'required', 'string', 'max:255'],
            'id_kategori_tarif' => ['sometimes', 'required', 'exists:master_kategori_tarif,id_kategori_tarif'],
            'id_layanan' => ['nullable', 'required_without:layanan_ids', 'exists:master_layanan,id_layanan'],
            'id_kategori_layanan' => ['nullable', 'exists:kategori_layanans,id_kategori_layanan'],
            'layanan_ids' => ['sometimes', 'array'],
            'layanan_ids.*' => ['integer', 'exists:master_layanan,id_layanan'],
            'komponen_tarif_ids' => ['sometimes', 'array'],
            'komponen_tarif_ids.*' => ['integer', 'exists:master_komponen_biaya,id_komponen'],
            'id_provinsi' => ['nullable', 'exists:master_provinsi,id_provinsi'],
            'id_kota' => ['nullable', 'exists:master_kota_kabupaten,id_kota'],
            'fee_nakes_tipe' => ['sometimes', 'in:nominal,persen'],
            'fee_nakes_nilai' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        DB::beginTransaction();
        try {
            if ($request->filled('id_kota')) {
                $kota = KotaKabupaten::findOrFail($request->id_kota);
                $provinsiId = $request->has('id_provinsi') ? $request->id_provinsi : $masterTarif->id_provinsi;
                if ($provinsiId && (int) $provinsiId !== (int) $kota->id_provinsi) {
                    throw new \InvalidArgumentException('Kota tidak termasuk dalam provinsi yang dipilih');
                }
                $masterTarif->id_provinsi = $kota->id_provinsi;
                $masterTarif->id_kota = $kota->id_kota;
            } elseif ($request->has('id_provinsi')) {
                $masterTarif->id_provinsi = $request->id_provinsi;
                if ($request->id_provinsi !== null && !$request->filled('id_kota')) {
                    $kotasInProv = KotaKabupaten::where('id_provinsi', $request->id_provinsi)->get();
                    if ($kotasInProv->isNotEmpty()) {
                        $masterTarif->id_kota = $kotasInProv->first()->id_kota;
                    } else {
                        $masterTarif->id_kota = null;
                    }
                } elseif ($request->id_provinsi === null) {
                    $masterTarif->id_kota = null;
                }
            }

            if ($request->has('nama_template')) $masterTarif->nama_template = $request->nama_template;
            if ($request->has('id_kategori_tarif')) $masterTarif->id_kategori_tarif = $request->id_kategori_tarif;
            $layananIds = array_unique(array_filter(array_merge(
                $request->filled('id_layanan') ? [(int) $request->id_layanan] : [],
                array_map('intval', $request->input('layanan_ids', [])),
                $request->filled('id_kategori_layanan')
                    ? MasterLayanan::where('id_kategori_layanan', $request->id_kategori_layanan)->pluck('id_layanan')->all()
                    : []
            )));
            if (empty($layananIds)) {
                $layananIds = [(int) $masterTarif->id_layanan];
            }
            if ($request->has('id_layanan')) $masterTarif->id_layanan = $layananIds[0];
            if ($request->has('fee_nakes_tipe')) $masterTarif->fee_nakes_tipe = $request->fee_nakes_tipe;
            if ($request->has('fee_nakes_nilai')) $masterTarif->fee_nakes_nilai = $request->fee_nakes_nilai;
            if ($request->has('is_active')) $masterTarif->is_active = $request->is_active;

            $layananUtama = MasterLayanan::find($masterTarif->id_layanan);
            $hargaLayanan = $layananUtama ? (float) $layananUtama->harga : 0;
            $feeType = $masterTarif->fee_nakes_tipe ?: 'persen';
            $feeValue = (float) $masterTarif->fee_nakes_nilai;

            if ($feeType === 'persen' && $feeValue > 100) {
                throw new \InvalidArgumentException('Fee nakes dalam persen tidak boleh lebih dari 100');
            }

            $masterTarif->fee_nakes_nominal = $feeType === 'nominal' ? $feeValue : ($hargaLayanan * ($feeValue / 100));
            $masterTarif->fee_platform_nominal = max(0, $hargaLayanan - $masterTarif->fee_nakes_nominal);

            $masterTarif->save();

            // Jika update provinsi tanpa kota, propagasikan ke kota-kota dalam provinsi tersebut
            if ($request->has('id_provinsi') && $request->id_provinsi !== null && !$request->filled('id_kota')) {
                $kotasInProv = KotaKabupaten::where('id_provinsi', $request->id_provinsi)->get();
                foreach ($kotasInProv as $k) {
                    if ((string)$k->id_kota === (string)$masterTarif->id_kota) continue;

                    $otherTarif = MasterTarif::updateOrCreate(
                        [
                            'nama_template' => $masterTarif->nama_template,
                            'id_kategori_tarif' => $masterTarif->id_kategori_tarif,
                            'id_layanan' => $masterTarif->id_layanan,
                            'id_kota' => $k->id_kota,
                        ],
                        [
                            'id_provinsi' => $request->id_provinsi,
                            'fee_nakes_tipe' => $feeType,
                            'fee_nakes_nilai' => $feeValue,
                            'fee_nakes_nominal' => $masterTarif->fee_nakes_nominal,
                            'fee_platform_nominal' => $masterTarif->fee_platform_nominal,
                            'is_active' => $masterTarif->is_active,
                        ]
                    );

                    if ($request->has('layanan_ids') || $request->has('id_layanan') || $request->has('id_kategori_layanan')) {
                        $otherTarif->layananTermasuk()->sync($layananIds);
                    }
                    if ($request->has('komponen_tarif_ids')) {
                        $otherTarif->komponenTarif()->sync($request->input('komponen_tarif_ids', []));
                    }
                }
            }

            if ($request->has('layanan_ids') || $request->has('id_layanan') || $request->has('id_kategori_layanan')) {
                $masterTarif->layananTermasuk()->sync($layananIds);
            }
            if ($request->has('komponen_tarif_ids')) {
                $masterTarif->komponenTarif()->sync($request->input('komponen_tarif_ids', []));
            }

            DB::commit();

            $masterTarif->load(['layanan', 'kategoriTarif', 'kota.provinsi', 'provinsi', 'layananTermasuk', 'komponenTarif']);

            return response()->json([
                'success' => true,
                'message' => 'Master Tarif berhasil diupdate',
                'data' => $masterTarif
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate master tarif: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Hapus Master Tarif
     */
    public function destroy($id)
    {
        $masterTarif = MasterTarif::findOrFail($id);
        $masterTarif->delete();

        return response()->json([
            'success' => true,
            'message' => 'Master tarif berhasil dihapus'
        ], 200);
    }
}