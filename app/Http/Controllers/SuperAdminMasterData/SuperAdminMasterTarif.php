<?php

namespace App\Http\Controllers\SuperAdminMasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterTarif;
use App\Models\MasterKomponenBiaya;
use App\Models\MasterTarifTransport;
use App\Models\KotaKabupaten;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Master Tarif Blueprint
 * 
 * @group Master Data
 * 
 * @subgroup Master Tarif Blueprint
 * 
 * @resource Master Tarif Blueprint
 */
class SuperAdminMasterTarif extends Controller
{
    /**
     * Tampilkan semua daftar template Master Tarif
     * 
     * @response 200 {
     *  "success": true,
     *  "message": "Berhasil mengambil data Master Tarif Blueprint",
     *  "data": [
     *      {
     *          "id_master_tarif": 1,
     *          "nama_template": "Reguler",
     *          "id_layanan": 1,
     *          "id_kota": null,
     *          "tarif_pasien": 150000.00,
     *          "transport_base_fare": 0.00,
     *          "transport_per_km": 0.00,
    *          "fee_nakes_tipe": "persen",
    *          "fee_nakes_nilai": 80,
     *          "fee_nakes_nominal": 120000.00,
     *          "fee_platform_nominal": 30000.00,
     *          "persen_ppn": 11.00,
     *          "total_ppn": 16500.00,
     *          "total_biaya_admin": 5000.00,
     *          "subtotal": 196500.00,
     *          "total_tarif_final": 196500.00,
     *          "is_active": true
     *      }
     *  ]
     * }
     */
    public function index()
    {
        $masterTarifs = MasterTarif::with(['layanan', 'kota.provinsi', 'provinsi', 'layananTermasuk', 'komponenTarif'])->get();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil data Master Tarif Blueprint',
            'data' => $masterTarifs
        ], 200);
    }

    /**
     * Simpan template Master Tarif baru (Blueprint) dan kalkulasi komponennya
     * 
     * @bodyParam nama_template string required Nama template tarif (e.g. Reguler, Weekend, Lansia)
     * @bodyParam id_layanan int required ID layanan yang bersangkutan
     * @bodyParam id_kota int optional ID kota/kabupaten (null untuk default nasional)
     * @bodyParam tarif_pasien numeric required Tarif dasar layanan medis yang dibayar pasien sebelum komponen lain
    * @bodyParam fee_nakes_tipe string optional Jenis fee nakes: nominal atau persen
    * @bodyParam fee_nakes_nilai numeric optional Nilai fee sesuai fee_nakes_tipe
     * @bodyParam is_active boolean optional Status keaktifan template tarif ini
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_template' => ['required', 'string', 'max:255'],
            'id_layanan' => ['required', 'exists:master_layanan,id_layanan'],
            'layanan_ids' => ['sometimes', 'array'],
            'layanan_ids.*' => ['integer', 'exists:master_layanan,id_layanan'],
            'komponen_tarif_ids' => ['sometimes', 'array'],
            'komponen_tarif_ids.*' => ['integer', 'exists:master_komponen_biaya,id_komponen'],
            'id_provinsi' => ['nullable', 'exists:master_provinsi,id_provinsi'],
            'id_kota' => ['nullable', 'exists:master_kota_kabupaten,id_kota'],
            'tarif_pasien' => ['required', 'numeric', 'min:0'],
            'fee_nakes_tipe' => ['sometimes', 'in:nominal,persen'],
            'fee_nakes_nilai' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $feeType = $request->input('fee_nakes_tipe', 'persen');
        $feeValue = (float) $request->input('fee_nakes_nilai', $request->input('potongan_persen_nakes', 80));
        if ($feeType === 'persen' && $feeValue > 100) {
            return response()->json(['message' => 'Fee nakes dalam persen tidak boleh lebih dari 100'], 422);
        }
        if ($request->filled('id_kota')) {
            $kota = KotaKabupaten::findOrFail($request->id_kota);
            if ($request->filled('id_provinsi') && (int) $request->id_provinsi !== (int) $kota->id_provinsi) {
                return response()->json(['message' => 'Kota tidak termasuk dalam provinsi yang dipilih'], 422);
            }
            $request->merge(['id_provinsi' => $kota->id_provinsi]);
        }

        DB::beginTransaction();
        try {
            // 1. Kalkulasi Tarif Transportasi Berdasarkan Kota
            $tarifTransportBaseFare = 0;
            $tarifTransportPerKilometer = 0;
            if ($request->id_kota) {
                $transportBlueprint = MasterTarifTransport::where('id_kota', $request->id_kota)->first();
                if ($transportBlueprint) {
                    $tarifTransportBaseFare = $transportBlueprint->tarif_awal;
                    $tarifTransportPerKilometer = $transportBlueprint->tarif_per_kilometer;
                }
            }

            // 2. Kalkulasi Pembagian Jasa (Nakes vs Platform)
            $nominalTarifLayananJasa = $request->tarif_pasien;
            $persentaseBagianNakes = $feeType === 'persen'
                ? $feeValue
                : ($nominalTarifLayananJasa > 0 ? ($feeValue / $nominalTarifLayananJasa) * 100 : 0);
            $nominalFeeNakesJasa = $feeType === 'nominal'
                ? min($feeValue, $nominalTarifLayananJasa)
                : $nominalTarifLayananJasa * ($persentaseBagianNakes / 100);
            $nominalFeePlatformJasa = $nominalTarifLayananJasa - $nominalFeeNakesJasa;

            // 3. Kalkulasi Komponen Tarif (PPN, admin aplikasi, dan lainnya)
            $komponenQuery = MasterKomponenBiaya::where('is_active', true);
            if ($request->has('komponen_tarif_ids')) {
                $komponenQuery->whereIn('id_komponen', $request->input('komponen_tarif_ids', []));
            }
            $daftarKomponenBiayaAktif = $komponenQuery->get();

            $nominalTotalPpnPajak = 0;
            $persentasePpnPajak = 0;
            $nominalTotalBiayaAdminAplikasi = 0;
            $nominalTotalBiayaLainnya = 0;

            foreach ($daftarKomponenBiayaAktif as $komponenBiaya) {
                $nilaiPotonganDihitung = $komponenBiaya->jenis_nilai === 'persen'
                    ? ($nominalTarifLayananJasa * ($komponenBiaya->nilai / 100))
                    : $komponenBiaya->nilai;

                if ($komponenBiaya->tipe_komponen === 'pajak') {
                    $nominalTotalPpnPajak += $nilaiPotonganDihitung;
                    if ($komponenBiaya->jenis_nilai === 'persen') {
                        $persentasePpnPajak = $komponenBiaya->nilai;
                    }
                } elseif ($komponenBiaya->tipe_komponen === 'admin_aplikasi') {
                    $nominalTotalBiayaAdminAplikasi += $nilaiPotonganDihitung;
                } elseif ($komponenBiaya->tipe_komponen === 'lainnya') {
                    $nominalTotalBiayaLainnya += $nilaiPotonganDihitung;
                }
            }

            // 4. Kalkulasi Ringkasan Akhir
            $subtotalTarifPasien = $nominalTarifLayananJasa 
                + $nominalTotalPpnPajak 
                + $nominalTotalBiayaAdminAplikasi
                + $nominalTotalBiayaLainnya;

            $totalTarifFinalPasien = $subtotalTarifPasien; // Eksklusi transport dinamis

            $masterTarif = MasterTarif::create([
                'nama_template' => $request->nama_template,
                'id_layanan' => $request->id_layanan,
                'id_provinsi' => $request->id_provinsi,
                'id_kota' => $request->id_kota,
                'tarif_pasien' => $nominalTarifLayananJasa,
                'fee_nakes_tipe' => $feeType,
                'fee_nakes_nilai' => $feeValue,

                'transport_base_fare' => $tarifTransportBaseFare,
                'transport_per_km' => $tarifTransportPerKilometer,

                'fee_nakes_nominal' => $nominalFeeNakesJasa,
                'fee_platform_nominal' => $nominalFeePlatformJasa,

                'persen_ppn' => $persentasePpnPajak,
                'total_ppn' => $nominalTotalPpnPajak,
                'total_biaya_admin' => $nominalTotalBiayaAdminAplikasi,
                'total_biaya_lainnya' => $nominalTotalBiayaLainnya,

                'subtotal' => $subtotalTarifPasien,
                'total_tarif_final' => $totalTarifFinalPasien,

                'is_active' => $request->is_active ?? true,
                'synced_at' => Carbon::now(),
            ]);

            DB::commit();
            $masterTarif->layananTermasuk()->sync(array_unique(array_merge(
                [$request->id_layanan],
                $request->input('layanan_ids', [])
            )));
            $masterTarif->komponenTarif()->sync($request->input('komponen_tarif_ids', []));
            $masterTarif->load(['layanan', 'kota.provinsi', 'provinsi', 'layananTermasuk', 'komponenTarif']);

            return response()->json([
                'success' => true,
                'message' => 'Blueprint Tarif berhasil dikalkulasi dan disimpan',
                'data' => $masterTarif
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan blueprint tarif: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tampilkan detail blueprint tarif berdasarkan ID
     */
    public function show($id)
    {
        $masterTarif = MasterTarif::with(['layanan', 'kota.provinsi', 'provinsi', 'layananTermasuk', 'komponenTarif'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail Master Tarif Blueprint',
            'data' => $masterTarif
        ], 200);
    }

    /**
     * Merubah dan re-kalkulasi template Master Tarif Blueprint
     * 
     * @bodyParam nama_template string optional Nama template tarif
     * @bodyParam id_layanan int optional ID layanan
     * @bodyParam id_kota int optional ID kota/kabupaten
     * @bodyParam tarif_pasien numeric optional Tarif dasar layanan
    * @bodyParam fee_nakes_tipe string optional Jenis fee nakes: nominal atau persen
    * @bodyParam fee_nakes_nilai numeric optional Nilai fee sesuai fee_nakes_tipe
     * @bodyParam is_active boolean optional Status keaktifan
     */
    public function update(Request $request, $id)
    {
        $masterTarif = MasterTarif::findOrFail($id);

        $validated = $request->validate([
            'nama_template' => ['sometimes', 'required', 'string', 'max:255'],
            'id_layanan' => ['sometimes', 'required', 'exists:master_layanan,id_layanan'],
            'layanan_ids' => ['sometimes', 'array'],
            'layanan_ids.*' => ['integer', 'exists:master_layanan,id_layanan'],
            'komponen_tarif_ids' => ['sometimes', 'array'],
            'komponen_tarif_ids.*' => ['integer', 'exists:master_komponen_biaya,id_komponen'],
            'id_provinsi' => ['nullable', 'exists:master_provinsi,id_provinsi'],
            'id_kota' => ['nullable', 'exists:master_kota_kabupaten,id_kota'],
            'tarif_pasien' => ['sometimes', 'required', 'numeric', 'min:0'],
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
            }
            // Update data input
            if ($request->has('nama_template')) $masterTarif->nama_template = $request->nama_template;
            if ($request->has('id_layanan')) $masterTarif->id_layanan = $request->id_layanan;
            if ($request->has('id_provinsi')) $masterTarif->id_provinsi = $request->id_provinsi;
            if ($request->has('id_kota')) $masterTarif->id_kota = $request->id_kota;
            if ($request->has('tarif_pasien')) $masterTarif->tarif_pasien = $request->tarif_pasien;
            if ($request->has('fee_nakes_tipe')) $masterTarif->fee_nakes_tipe = $request->fee_nakes_tipe;
            if ($request->has('fee_nakes_nilai')) $masterTarif->fee_nakes_nilai = $request->fee_nakes_nilai;
            if ($request->has('is_active')) $masterTarif->is_active = $request->is_active;

            // Re-kalkulasi Transport
            $tarifTransportBaseFare = 0;
            $tarifTransportPerKilometer = 0;
            if ($masterTarif->id_kota) {
                $transportBlueprint = MasterTarifTransport::where('id_kota', $masterTarif->id_kota)->first();
                if ($transportBlueprint) {
                    $tarifTransportBaseFare = $transportBlueprint->tarif_awal;
                    $tarifTransportPerKilometer = $transportBlueprint->tarif_per_kilometer;
                }
            }

            // Re-kalkulasi Pembagian Jasa
            $nominalTarifLayananJasa = $masterTarif->tarif_pasien;
            $feeType = $masterTarif->fee_nakes_tipe ?: 'persen';
            $feeValue = (float) $masterTarif->fee_nakes_nilai;
            if ($feeType === 'persen' && $feeValue > 100) {
                throw new \InvalidArgumentException('Fee nakes dalam persen tidak boleh lebih dari 100');
            }
            $persentaseBagianNakes = $feeType === 'persen'
                ? $feeValue
                : ($nominalTarifLayananJasa > 0 ? ($feeValue / $nominalTarifLayananJasa) * 100 : 0);
            $nominalFeeNakesJasa = $feeType === 'nominal'
                ? min($feeValue, $nominalTarifLayananJasa)
                : $nominalTarifLayananJasa * ($persentaseBagianNakes / 100);
            $nominalFeePlatformJasa = $nominalTarifLayananJasa - $nominalFeeNakesJasa;

            // Re-kalkulasi Komponen Tarif
            $komponenQuery = MasterKomponenBiaya::where('is_active', true);
            if ($request->has('komponen_tarif_ids')) {
                $komponenQuery->whereIn('id_komponen', $request->input('komponen_tarif_ids', []));
            }
            $daftarKomponenBiayaAktif = $komponenQuery->get();

            $nominalTotalPpnPajak = 0;
            $persentasePpnPajak = 0;
            $nominalTotalBiayaAdminAplikasi = 0;
            $nominalTotalBiayaLainnya = 0;

            foreach ($daftarKomponenBiayaAktif as $komponenBiaya) {
                $nilaiPotonganDihitung = $komponenBiaya->jenis_nilai === 'persen'
                    ? ($nominalTarifLayananJasa * ($komponenBiaya->nilai / 100))
                    : $komponenBiaya->nilai;

                if ($komponenBiaya->tipe_komponen === 'pajak') {
                    $nominalTotalPpnPajak += $nilaiPotonganDihitung;
                    if ($komponenBiaya->jenis_nilai === 'persen') {
                        $persentasePpnPajak = $komponenBiaya->nilai;
                    }
                } elseif ($komponenBiaya->tipe_komponen === 'admin_aplikasi') {
                    $nominalTotalBiayaAdminAplikasi += $nilaiPotonganDihitung;
                } elseif ($komponenBiaya->tipe_komponen === 'lainnya') {
                    $nominalTotalBiayaLainnya += $nilaiPotonganDihitung;
                }
            }

            // Set final properties
            $masterTarif->transport_base_fare = $tarifTransportBaseFare;
            $masterTarif->transport_per_km = $tarifTransportPerKilometer;
            $masterTarif->fee_nakes_nominal = $nominalFeeNakesJasa;
            $masterTarif->fee_platform_nominal = $nominalFeePlatformJasa;
            $masterTarif->fee_nakes_tipe = $feeType;
            $masterTarif->fee_nakes_nilai = $feeValue;

            $masterTarif->persen_ppn = $persentasePpnPajak;
            $masterTarif->total_ppn = $nominalTotalPpnPajak;
            $masterTarif->total_biaya_admin = $nominalTotalBiayaAdminAplikasi;
            $masterTarif->total_biaya_lainnya = $nominalTotalBiayaLainnya;

            $masterTarif->subtotal = $nominalTarifLayananJasa 
                + $nominalTotalPpnPajak 
                + $nominalTotalBiayaAdminAplikasi
                + $nominalTotalBiayaLainnya;

            $masterTarif->total_tarif_final = $masterTarif->subtotal;
            $masterTarif->synced_at = Carbon::now();

            $masterTarif->save();
            DB::commit();
            if ($request->has('layanan_ids') || $request->has('id_layanan')) {
                $masterTarif->layananTermasuk()->sync(array_unique(array_merge(
                    [$masterTarif->id_layanan],
                    $request->input('layanan_ids', [])
                )));
            }
            if ($request->has('komponen_tarif_ids')) {
                $masterTarif->komponenTarif()->sync($request->input('komponen_tarif_ids', []));
            }
            $masterTarif->load(['layanan', 'kota.provinsi', 'provinsi', 'layananTermasuk', 'komponenTarif']);

            return response()->json([
                'success' => true,
                'message' => 'Blueprint Tarif berhasil diupdate dan dikalkulasi ulang',
                'data' => $masterTarif
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate blueprint tarif: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Hapus template blueprint tarif
     */
    public function destroy($id)
    {
        $masterTarif = MasterTarif::findOrFail($id);
        $masterTarif->delete();

        return response()->json([
            'success' => true,
            'message' => 'Blueprint tarif berhasil dihapus'
        ], 200);
    }
}