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

class SuperAdminMasterTarif extends Controller
{
    /**
     * Tampilkan semua daftar template Master Tarif
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

            // 1. Kalkulasi Transport
            $transportData = ['base_fare' => 0, 'per_km' => 0];
            if ($request->id_kota) {
                $transport = MasterTarifTransport::where('id_kota', $request->id_kota)->first();
                if ($transport) {
                    $transportData['base_fare'] = $transport->tarif_awal;
                    $transportData['per_km'] = $transport->tarif_per_kilometer;
                }
            }

            // 2. Kalkulasi Total BHP (dari default layanan)
            $totalBhp = 0;
            foreach ($layanan->bhpItems as $bhp) {
                // Asumsi `MasterBhp` memiliki `harga_jual` -- ambil dari pivot qty_default
                $totalBhp += ($bhp->harga_jual * $bhp->pivot->qty_default);
            }

            // 3. Kalkulasi Fee Nakes
            $feeNakesNominal = $request->tarif_pasien * ($request->potongan_persen_nakes / 100);

            // 4. Kalkulasi Komponen Biaya (PPN, Admin, Asuransi)
            $komponen = MasterKomponenBiaya::where('is_active', true)->get();

            $totalPpn = 0;
            $totalAdmin = 0;
            $totalAsuransi = 0;

            foreach ($komponen as $komp) {
                $nilaiPotongan = $komp->jenis_nilai === 'persen'
                    ? ($request->tarif_pasien * ($komp->nilai / 100))
                    : $komp->nilai;

                if ($komp->tipe_komponen === 'pajak') {
                    $totalPpn += $nilaiPotongan;
                } elseif ($komp->tipe_komponen === 'admin_aplikasi') {
                    $totalAdmin += $nilaiPotongan;
                } elseif ($komp->tipe_komponen === 'asuransi') {
                    $totalAsuransi += $nilaiPotongan;
                }
            }

            // 5. Total dan Subtotal
            $subtotal = $request->tarif_pasien + $totalBhp + $totalPpn + $totalAdmin + $totalAsuransi;
            $totalTarifFinal = $subtotal; // Tidak termasuk transport

            $masterTarif = MasterTarif::create([
                'nama_template' => $request->nama_template,
                'id_layanan' => $request->id_layanan,
                'id_kota' => $request->id_kota,
                'tarif_pasien' => $request->tarif_pasien,

                'transport_base_fare' => $transportData['base_fare'],
                'transport_per_km' => $transportData['per_km'],

                'total_bhp' => $totalBhp,
                'potongan_persen_nakes' => $request->potongan_persen_nakes,
                'fee_nakes_nominal' => $feeNakesNominal,

                'total_ppn' => $totalPpn,
                'total_biaya_admin' => $totalAdmin,
                'total_asuransi' => $totalAsuransi,

                'subtotal' => $subtotal,
                'total_tarif_final' => $totalTarifFinal,

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
     * Tampilkan detail
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
            // Update initial values requested
            $masterTarif->fill($request->only([
                'nama_template',
                'id_layanan',
                'id_kota',
                'tarif_pasien',
                'potongan_persen_nakes',
                'is_active'
            ]));

            $layanan = MasterLayanan::with('bhpItems')->findOrFail($masterTarif->id_layanan);

            // Re-kalkulasi Transport
            $transportData = ['base_fare' => 0, 'per_km' => 0];
            if ($masterTarif->id_kota) {
                $transport = MasterTarifTransport::where('id_kota', $masterTarif->id_kota)->first();
                if ($transport) {
                    $transportData['base_fare'] = $transport->tarif_awal;
                    $transportData['per_km'] = $transport->tarif_per_kilometer;
                }
            }

            // Re-kalkulasi Total BHP
            $totalBhp = 0;
            foreach ($layanan->bhpItems as $bhp) {
                $totalBhp += ($bhp->harga_jual * $bhp->pivot->qty_default);
            }

            // Re-kalkulasi Fee Nakes
            $feeNakesNominal = $masterTarif->tarif_pasien * ($masterTarif->potongan_persen_nakes / 100);

            // Re-kalkulasi Komponen Biaya
            $komponen = MasterKomponenBiaya::where('is_active', true)->get();
            $totalPpn = 0;
            $totalAdmin = 0;
            $totalAsuransi = 0;

            foreach ($komponen as $komp) {
                $nilaiPotongan = $komp->jenis_nilai === 'persen'
                    ? ($masterTarif->tarif_pasien * ($komp->nilai / 100))
                    : $komp->nilai;

                if ($komp->tipe_komponen === 'pajak') {
                    $totalPpn += $nilaiPotongan;
                } elseif ($komp->tipe_komponen === 'admin_aplikasi') {
                    $totalAdmin += $nilaiPotongan;
                } elseif ($komp->tipe_komponen === 'asuransi') {
                    $totalAsuransi += $nilaiPotongan;
                }
            }

            // Set final kalkulasi
            $masterTarif->transport_base_fare = $transportData['base_fare'];
            $masterTarif->transport_per_km = $transportData['per_km'];
            $masterTarif->total_bhp = $totalBhp;
            $masterTarif->fee_nakes_nominal = $feeNakesNominal;
            $masterTarif->total_ppn = $totalPpn;
            $masterTarif->total_biaya_admin = $totalAdmin;
            $masterTarif->total_asuransi = $totalAsuransi;

            $masterTarif->subtotal = $masterTarif->tarif_pasien + $totalBhp + $totalPpn + $totalAdmin + $totalAsuransi;
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
     * Hapus template
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