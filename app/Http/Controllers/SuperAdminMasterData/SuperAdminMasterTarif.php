<?php

namespace App\Http\Controllers\SuperAdminMasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterTarif;
use App\Models\MasterKomponenBiaya;
use App\Models\MasterTarifTransport;
use App\Models\MasterLayanan;
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
     *          "total_bhp": 25000.00,
     *          "potongan_persen_nakes": 80,
     *          "fee_nakes_nominal": 120000.00,
     *          "fee_platform_nominal": 30000.00,
     *          "persen_ppn": 11.00,
     *          "total_ppn": 16500.00,
     *          "total_biaya_admin": 5000.00,
     *          "total_asuransi": 2000.00,
     *          "subtotal": 198500.00,
     *          "total_tarif_final": 198500.00,
     *          "is_active": true
     *      }
     *  ]
     * }
     */
    public function index()
    {
        $masterTarifs = MasterTarif::with(['layanan', 'kota'])->get();

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
     * @bodyParam potongan_persen_nakes int required Persentase hak nakes dari tarif_pasien (0-100)
     * @bodyParam is_active boolean optional Status keaktifan template tarif ini
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_template' => ['required', 'string', 'max:255'],
            'id_layanan' => ['required', 'exists:master_layanan,id_layanan'],
            'id_kota' => ['nullable', 'exists:master_kota_kabupaten,id_kota'],
            'tarif_pasien' => ['required', 'numeric', 'min:0'],
            'potongan_persen_nakes' => ['required', 'integer', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        DB::beginTransaction();
        try {
            $layanan = MasterLayanan::with('bhpItems')->findOrFail($request->id_layanan);

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

            // 2. Kalkulasi Total BHP (Bahan Habis Pakai)
            $totalBiayaBahanHabisPakai = 0;
            foreach ($layanan->bhpItems as $bhpItem) {
                $totalBiayaBahanHabisPakai += ($bhpItem->harga_jual * $bhpItem->pivot->qty_default);
            }

            // 3. Kalkulasi Pembagian Jasa (Nakes vs Platform)
            $nominalTarifLayananJasa = $request->tarif_pasien;
            $persentaseBagianNakes = $request->potongan_persen_nakes;
            $persentaseBagianPlatform = 100 - $persentaseBagianNakes;

            $nominalFeeNakesJasa = $nominalTarifLayananJasa * ($persentaseBagianNakes / 100);
            $nominalFeePlatformJasa = $nominalTarifLayananJasa * ($persentaseBagianPlatform / 100);

            // 4. Kalkulasi Komponen Biaya Eksternal (PPN, Admin Aplikasi, Asuransi)
            $daftarKomponenBiayaAktif = MasterKomponenBiaya::where('is_active', true)->get();

            $nominalTotalPpnPajak = 0;
            $persentasePpnPajak = 0;
            $nominalTotalBiayaAdminAplikasi = 0;
            $nominalTotalBiayaAsuransiNakes = 0;

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
                } elseif ($komponenBiaya->tipe_komponen === 'asuransi') {
                    $nominalTotalBiayaAsuransiNakes += $nilaiPotonganDihitung;
                }
            }

            // 5. Kalkulasi Ringkasan Akhir
            $subtotalTarifPasien = $nominalTarifLayananJasa 
                + $totalBiayaBahanHabisPakai 
                + $nominalTotalPpnPajak 
                + $nominalTotalBiayaAdminAplikasi 
                + $nominalTotalBiayaAsuransiNakes;

            $totalTarifFinalPasien = $subtotalTarifPasien; // Eksklusi transport dinamis

            $masterTarif = MasterTarif::create([
                'nama_template' => $request->nama_template,
                'id_layanan' => $request->id_layanan,
                'id_kota' => $request->id_kota,
                'tarif_pasien' => $nominalTarifLayananJasa,

                'transport_base_fare' => $tarifTransportBaseFare,
                'transport_per_km' => $tarifTransportPerKilometer,

                'total_bhp' => $totalBiayaBahanHabisPakai,
                'potongan_persen_nakes' => $persentaseBagianNakes,
                'fee_nakes_nominal' => $nominalFeeNakesJasa,
                'fee_platform_nominal' => $nominalFeePlatformJasa,

                'persen_ppn' => $persentasePpnPajak,
                'total_ppn' => $nominalTotalPpnPajak,
                'total_biaya_admin' => $nominalTotalBiayaAdminAplikasi,
                'total_asuransi' => $nominalTotalBiayaAsuransiNakes,

                'subtotal' => $subtotalTarifPasien,
                'total_tarif_final' => $totalTarifFinalPasien,

                'is_active' => $request->is_active ?? true,
                'synced_at' => Carbon::now(),
            ]);

            DB::commit();
            $masterTarif->load(['layanan', 'kota']);

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
        $masterTarif = MasterTarif::with(['layanan', 'kota'])->findOrFail($id);

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
     * @bodyParam potongan_persen_nakes int optional Persentase hak nakes
     * @bodyParam is_active boolean optional Status keaktifan
     */
    public function update(Request $request, $id)
    {
        $masterTarif = MasterTarif::findOrFail($id);

        $validated = $request->validate([
            'nama_template' => ['sometimes', 'required', 'string', 'max:255'],
            'id_layanan' => ['sometimes', 'required', 'exists:master_layanan,id_layanan'],
            'id_kota' => ['nullable', 'exists:master_kota_kabupaten,id_kota'],
            'tarif_pasien' => ['sometimes', 'required', 'numeric', 'min:0'],
            'potongan_persen_nakes' => ['sometimes', 'required', 'integer', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        DB::beginTransaction();
        try {
            // Update data input
            if ($request->has('nama_template')) $masterTarif->nama_template = $request->nama_template;
            if ($request->has('id_layanan')) $masterTarif->id_layanan = $request->id_layanan;
            if ($request->has('id_kota')) $masterTarif->id_kota = $request->id_kota;
            if ($request->has('tarif_pasien')) $masterTarif->tarif_pasien = $request->tarif_pasien;
            if ($request->has('potongan_persen_nakes')) $masterTarif->potongan_persen_nakes = $request->potongan_persen_nakes;
            if ($request->has('is_active')) $masterTarif->is_active = $request->is_active;

            $layanan = MasterLayanan::with('bhpItems')->findOrFail($masterTarif->id_layanan);

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

            // Re-kalkulasi BHP
            $totalBiayaBahanHabisPakai = 0;
            foreach ($layanan->bhpItems as $bhpItem) {
                $totalBiayaBahanHabisPakai += ($bhpItem->harga_jual * $bhpItem->pivot->qty_default);
            }

            // Re-kalkulasi Pembagian Jasa
            $nominalTarifLayananJasa = $masterTarif->tarif_pasien;
            $persentaseBagianNakes = $masterTarif->potongan_persen_nakes;
            $persentaseBagianPlatform = 100 - $persentaseBagianNakes;

            $nominalFeeNakesJasa = $nominalTarifLayananJasa * ($persentaseBagianNakes / 100);
            $nominalFeePlatformJasa = $nominalTarifLayananJasa * ($persentaseBagianPlatform / 100);

            // Re-kalkulasi Komponen Biaya Eksternal
            $daftarKomponenBiayaAktif = MasterKomponenBiaya::where('is_active', true)->get();

            $nominalTotalPpnPajak = 0;
            $persentasePpnPajak = 0;
            $nominalTotalBiayaAdminAplikasi = 0;
            $nominalTotalBiayaAsuransiNakes = 0;

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
                } elseif ($komponenBiaya->tipe_komponen === 'asuransi') {
                    $nominalTotalBiayaAsuransiNakes += $nilaiPotonganDihitung;
                }
            }

            // Set final properties
            $masterTarif->transport_base_fare = $tarifTransportBaseFare;
            $masterTarif->transport_per_km = $tarifTransportPerKilometer;
            $masterTarif->total_bhp = $totalBiayaBahanHabisPakai;
            $masterTarif->fee_nakes_nominal = $nominalFeeNakesJasa;
            $masterTarif->fee_platform_nominal = $nominalFeePlatformJasa;

            $masterTarif->persen_ppn = $persentasePpnPajak;
            $masterTarif->total_ppn = $nominalTotalPpnPajak;
            $masterTarif->total_biaya_admin = $nominalTotalBiayaAdminAplikasi;
            $masterTarif->total_asuransi = $nominalTotalBiayaAsuransiNakes;

            $masterTarif->subtotal = $nominalTarifLayananJasa 
                + $totalBiayaBahanHabisPakai 
                + $nominalTotalPpnPajak 
                + $nominalTotalBiayaAdminAplikasi 
                + $nominalTotalBiayaAsuransiNakes;

            $masterTarif->total_tarif_final = $masterTarif->subtotal;
            $masterTarif->synced_at = Carbon::now();

            $masterTarif->save();
            DB::commit();
            $masterTarif->load(['layanan', 'kota']);

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