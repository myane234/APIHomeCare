<?php

namespace App\Http\Controllers;

use App\Models\TenagaMedis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Users;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * @group Super Admin Nakes Management
 */
class AdminNakesController extends Controller
{
    /**
     * List semua pendaftaran nakes
     */
    /**
     * List semua pendaftaran nakes (Menampilkan semua data kecuali yang approved)
     */
    public function index(Request $request)
    {
        $admin = $request->user();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Silakan login terlebih dahulu.'
            ], 401);
        }

        // Ambil query status jika dikirim
        $status = $request->query('status');

        $query = TenagaMedis::with(['user', 'pasien', 'wilayahLayanan', 'kategoriLayanan'])
            ->orderBy('created_at', 'desc');

        // Jika query status dikirim dan valid, filter berdasarkan status tersebut
        if ($status && in_array(strtolower((string) $status), ['pending', 'pelatihan', 'approved', 'rejected'], true)) {
            $query->where('status', strtolower((string) $status));
        } else {
            // Default: tampilkan semua kecuali status 'approved'
            $query->where('status', '!=', 'approved');
        }

        $nakesRequests = $query->get();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar pendaftaran Nakes.',
            'data' => $nakesRequests
        ], 200);
    }

    
    /**
     * List khusus tenaga medis yang sudah aktif / approved
     */
    public function listActiveNakes(Request $request)
    {
        $admin = $request->user();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Silakan login terlebih dahulu.'
            ], 401);
        }

        $tenagaMedis = TenagaMedis::with(['user', 'pasien', 'wilayahLayanan', 'kategoriLayanan'])
            ->where('status', 'approved')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar Nakes aktif.',
            'data' => $tenagaMedis
        ], 200);
    }

    /**
     * Detail lengkap nakes
     */
    public function show(Request $request, $id)
    {
        $admin = $request->user();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Silakan login terlebih dahulu.'
            ], 401);
        }

        $tenagaMedis = TenagaMedis::with(['user', 'pasien', 'wilayahLayanan', 'kategoriLayanan'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil detail Nakes.',
            'data' => $tenagaMedis
        ], 200);
    }

    /**
     * STEP 1: Ajukan Pendaftaran ke Tahap Pelatihan
     */
    public function setPelatihan(Request $request, $id)
    {
        $admin = $request->user();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Silakan login terlebih dahulu.'
            ], 401);
        }

        $tenagaMedis = TenagaMedis::findOrFail($id);

        if ($tenagaMedis->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pendaftaran berstatus "pending" yang dapat diajukan ke tahap pelatihan.'
            ], 400);
        }

        $tenagaMedis->update([
            'status' => 'pelatihan',
            'admin_notes' => null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pendaftaran Nakes berhasil diajukan ke tahap pelatihan.',
            'data' => $tenagaMedis->fresh(['user', 'pasien', 'wilayahLayanan', 'kategoriLayanan'])
        ], 200);
    }

    /**
     * STEP 2: Approve Pelatihan & Aktifkan Akun
     */
    public function approve(Request $request, $id)
    {
        $admin = $request->user();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Silakan login terlebih dahulu.'
            ], 401);
        }

        $tenagaMedis = TenagaMedis::findOrFail($id);

        if ($tenagaMedis->status !== 'pelatihan') {
            return response()->json([
                'success' => false,
                'message' => 'Pendaftaran Nakes tidak bisa langsung di-approve. Harus diajukan ke tahap pelatihan terlebih dahulu.'
            ], 400);
        }

        $result = DB::transaction(function () use ($tenagaMedis) {
            $tenagaMedis->update([
                'status' => 'approved',
                'admin_notes' => null
            ]);

            $user = $tenagaMedis->user;
            if ($user && !$user->roles()->where('user_roles.nama_role', 'nakes')->exists()) {
                $user->roles()->attach('nakes');
            }

            return $tenagaMedis->fresh(['user', 'pasien', 'wilayahLayanan', 'kategoriLayanan']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Nakes dinyatakan lulus pelatihan dan akun/role Nakes berhasil diaktifkan.',
            'data' => $result
        ], 200);
    }

    /**
     * REJECT: Tolak Pendaftaran
     */
    public function reject(Request $request, $id)
    {
        $admin = $request->user();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Silakan login terlebih dahulu.'
            ], 401);
        }

        $tenagaMedis = TenagaMedis::findOrFail($id);

        if ($tenagaMedis->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Nakes yang sudah di-approve/aktif tidak bisa langsung di-reject.'
            ], 400);
        }

        if ($tenagaMedis->status === 'rejected') {
            return response()->json([
                'success' => false,
                'message' => 'Pendaftaran Nakes ini sudah di-reject sebelumnya.'
            ], 400);
        }

        $validate = $request->validate([
            'admin_notes' => ['required', 'string', 'max:1000']
        ], [
            'admin_notes.required' => 'Alasan penolakan (admin_notes) wajib diisi.'
        ]);

        $tenagaMedis->update([
            'status' => 'rejected',
            'admin_notes' => $validate['admin_notes']
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pendaftaran Nakes berhasil ditolak.',
            'data' => $tenagaMedis->fresh(['user', 'pasien', 'wilayahLayanan', 'kategoriLayanan'])
        ], 200);
    }

    /**
     * Tambah Data Nakes Baru via Admin
     *
     * Endpoint ini digunakan oleh Super Admin untuk mendaftarkan Tenaga Medis (Nakes) baru secara langsung.
     * Admin dapat membuatkan akun user baru atau menghubungkan ke user_id yang sudah ada, 
     * mengunggah berkas lengkap, serta menentukan status awal pendaftaran (pending, pelatihan, approved, rejected).
     * Jika status di-set ke 'approved', role 'nakes' akan otomatis ditambahkan ke user.
     * 
     * @authenticated
     * 
     * @bodyParam user_id int ID user yang sudah terdaftar (opsional jika buat user baru). Example: 5
     * @bodyParam email string Email nakes untuk akun baru (wajib jika user_id tidak diisi). Example: nakes.budi@example.com
     * @bodyParam password string Password akun baru min. 8 karakter (wajib jika user_id tidak diisi). Example: Password123!
     * @bodyParam status string Status pendaftaran nakes (pending|pelatihan|approved|rejected). Example: approved
     * @bodyParam admin_notes string Catatan khusus dari admin. Example: Berkas fisik dan keaslian STR telah diverifikasi.
     * @bodyParam nik string NIK Nakes (16 digit angka). Example: 3171012005900001
     * @bodyParam nama_lengkap string Nama lengkap Nakes beserta gelar. Example: Dr. Budi Santoso, Sp.A
     * @bodyParam nama_panggilan string Nama panggilan. Example: Budi
     * @bodyParam jenis_kelamin string Jenis kelamin (L/P). Example: L
     * @bodyParam tempat_lahir string Tempat lahir. Example: Jakarta
     * @bodyParam tanggal_lahir date Tanggal lahir (YYYY-MM-DD). Example: 1990-05-20
     * @bodyParam agama string Agama Nakes. Example: Islam
     * @bodyParam no_telp string Nomor telepon/WhatsApp. Example: 081234567890
     * @bodyParam id_wilayah_layanan int ID provinsi operasional (exists:master_provinsi). Example: 31
     * @bodyParam alamat_lengkap string Alamat domisili lengkap. Example: Jl. Sudirman No. 123, Jakarta Selatan
     * @bodyParam foto_profile file Foto profil nakes (jpg, jpeg, png, webp, max 5MB).
     * @bodyParam jenis_tenaga_medis string Spesialisasi/kategori medis. Example: Dokter Anak
     * @bodyParam universitas string Nama perguruan tinggi/universitas. Example: Universitas Indonesia
     * @bodyParam program_studi string Program studi / jurusan. Example: Profesi Dokter
     * @bodyParam tahun_lulus int Tahun kelulusan (4 digit). Example: 2015
     * @bodyParam no_str string Nomor Surat Tanda Registrasi. Example: 3111100223344556
     * @bodyParam no_sip string Nomor Surat Izin Praktik. Example: 446/001/SIP-D/2021
     * @bodyParam file_ktp file Berkas KTP (pdf, jpg, jpeg, png, max 5MB).
     * @bodyParam ijazah file Berkas Ijazah (pdf, jpg, jpeg, png, max 5MB).
     * @bodyParam file_skck file Berkas SKCK (pdf, jpg, jpeg, png, max 5MB).
     * @bodyParam file_cv file Berkas CV / Daftar Riwayat Hidup (pdf, doc, docx, jpg, jpeg, png, max 5MB).
     * @bodyParam file_str file Berkas STR (pdf, jpg, jpeg, png, max 5MB).
     * @bodyParam file_sip file Berkas SIP (pdf, jpg, jpeg, png, max 5MB).
     * @bodyParam tempat_kerja string Tempat/instansi kerja saat ini (opsional). Example: RS Cipto Mangunkusumo
     * @bodyParam lama_bekerja string Durasi pengalaman kerja (opsional). Example: 5 Tahun
     * @bodyParam dokumen_tambahan file[] Array berkas pendukung tambahan (opsional, max 10 file, max 5MB/file).
     * 
     * @response 201 scenario="Nakes berhasil didaftarkan" {
     *   "success": true,
     *   "message": "Berhasil menambahkan data Nakes baru oleh admin.",
     *   "data": {
     *     "id": 12,
     *     "user_id": 45,
     *     "nik": "3171012005900001",
     *     "nama_lengkap": "Dr. Budi Santoso, Sp.A",
     *     "nama_panggilan": "Budi",
     *     "jenis_kelamin": "L",
     *     "tempat_lahir": "Jakarta",
     *     "tanggal_lahir": "1990-05-20",
     *     "agama": "Islam",
     *     "no_telp": "081234567890",
     *     "id_wilayah_layanan": 31,
     *     "alamat_lengkap": "Jl. Sudirman No. 123, Jakarta Selatan",
     *     "foto_profile": "nakes_documents/45/profile/abc123profile.jpg",
     *     "jenis_tenaga_medis": "Dokter Anak",
     *     "universitas": "Universitas Indonesia",
     *     "program_studi": "Profesi Dokter",
     *     "tahun_lulus": 2015,
     *     "no_str": "3111100223344556",
     *     "no_sip": "446/001/SIP-D/2021",
     *     "file_ktp": "nakes_documents/45/ktp/ktp_doc.pdf",
     *     "ijazah": "nakes_documents/45/ijazah/ijazah_doc.pdf",
     *     "file_skck": "nakes_documents/45/skck/skck_doc.pdf",
     *     "file_cv": "nakes_documents/45/cv/cv_doc.pdf",
     *     "file_str": "nakes_documents/45/str/str_doc.pdf",
     *     "file_sip": "nakes_documents/45/sip/sip_doc.pdf",
     *     "tempat_kerja": "RS Cipto Mangunkusumo",
     *     "lama_bekerja": "5 Tahun",
     *     "dokumen_tambahan": "[\"nakes_documents\\/45\\/tambahan\\/sertifikat1.pdf\"]",
     *     "status": "approved",
     *     "admin_notes": "Berkas fisik dan keaslian STR telah diverifikasi.",
     *     "created_at": "2026-08-31T21:00:00.000000Z",
     *     "updated_at": "2026-08-31T21:00:00.000000Z",
     *     "user": {
     *       "id": 45,
     *       "name": "Dr. Budi Santoso, Sp.A",
     *       "email": "nakes.budi@example.com",
     *       "created_at": "2026-08-31T21:00:00.000000Z"
     *     }
     *   }
     * }
     * 
     * @response 422 scenario="Gagal validasi input" {
     *   "message": "NIK wajib diisi. (and 5 more errors)",
     *   "errors": {
     *     "nik": ["NIK wajib diisi."],
     *     "email": ["Email sudah terdaftar."],
     *     "file_str": ["Foto STR wajib diunggah."]
     *   }
     * }
     * 
     * @response 401 scenario="Unauthenticated" {
     *   "success": false,
     *   "message": "Akses ditolak. Silakan login terlebih dahulu."
     * }
     */

    public function CreateNakesViaAdmin(Request $request)
{
    $admin = $request->user();

    if (!$admin) {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak. Silakan login terlebih dahulu.'
        ], 401);
    }

    // Validasi data input admin + seluruh field biodata & berkas Nakes
    $validated = $request->validate([
        // Akun User (Pilih user_id yang ada ATAU isi email & password untuk buat akun baru)
        'user_id'              => ['nullable', 'exists:users,id'],
        'email'                => ['required_without:user_id', 'nullable', 'email', 'unique:users,email'],
        'password'             => ['required_without:user_id', 'nullable', 'string', 'min:8'],

        // Status & Catatan Admin
        'status'               => ['required', 'in:pending,pelatihan,approved,rejected'],
        'admin_notes'          => ['nullable', 'string', 'max:1000'],

        // Biodata Pribadi Nakes
        'nik'                  => ['required', 'string', 'regex:/^[0-9]{16}$/'],
        'nama_lengkap'         => ['required', 'string', 'max:255'],
        'nama_panggilan'       => ['required', 'string', 'max:100'],
        'jenis_kelamin'        => ['required', 'in:L,P'],
        'tempat_lahir'         => ['required', 'string', 'max:255'],
        'tanggal_lahir'        => ['required', 'date'],
        'agama'                => ['required', 'string', 'max:50'],
        'no_telp'              => ['required', 'string', 'max:15'],
        'id_wilayah_layanan'   => ['required', 'integer', 'exists:master_provinsi,id_provinsi'],
        'alamat_lengkap'       => ['required', 'string', 'max:1000'],
        'foto_profile'         => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],

        // Jenis Tenaga Medis
        'jenis_tenaga_medis'   => ['required', 'string', 'max:100'],

        // Profesi & Pendidikan
        'universitas'          => ['required', 'string', 'max:255'],
        'program_studi'        => ['required', 'string', 'max:255'],
        'tahun_lulus'          => ['required', 'digits:4', 'integer'],
        'no_str'               => ['required', 'string', 'max:255'],
        'no_sip'               => ['required', 'string', 'max:255'],

        // Berkas Utama
        'file_ktp'             => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        'ijazah'               => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        'file_skck'            => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        'file_cv'              => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:5120'],
        'file_str'             => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        'file_sip'             => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],

        // Nullable / Opsional
        'tempat_kerja'         => ['nullable', 'string', 'max:255'],
        'lama_bekerja'         => ['nullable', 'string', 'max:100'],
        'dokumen_tambahan'     => ['nullable', 'array', 'max:10'],
        'dokumen_tambahan.*'   => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
    ], [
        'nik.required'                => 'NIK wajib diisi.',
        'nik.regex'                   => 'NIK harus tepat 16 digit angka.',
        'nama_lengkap.required'       => 'Nama lengkap wajib diisi.',
        'nama_panggilan.required'     => 'Nama panggilan wajib diisi.',
        'id_wilayah_layanan.required' => 'Wilayah operasional wajib dipilih.',
        'id_wilayah_layanan.exists'   => 'Wilayah operasional tidak valid.',
        'jenis_tenaga_medis.required' => 'Kategori/jenis tenaga medis wajib dipilih.',
        'no_str.required'             => 'Nomor STR wajib diisi.',
        'no_sip.required'             => 'Nomor SIP wajib diisi.',
        'file_ktp.required'           => 'Foto KTP wajib diunggah.',
        'ijazah.required'             => 'Foto Ijazah wajib diunggah.',
        'file_skck.required'          => 'Foto SKCK wajib diunggah.',
        'file_cv.required'            => 'File CV wajib diunggah.',
        'file_str.required'           => 'Foto STR wajib diunggah.',
        'file_sip.required'           => 'Foto SIP wajib diunggah.',
        'dokumen_tambahan.max'        => 'Dokumen tambahan maksimal 10 file.',
        'dokumen_tambahan.*.mimes'    => 'Dokumen tambahan harus berupa PDF, gambar, atau Word.',
    ]);

    $tenagaMedis = DB::transaction(function () use ($request, $validated) {
        // 1. Penanganan Akun User
        $userId = $validated['user_id'] ?? null;

        if (!$userId) {
            $user = Users::create([
                'name'     => $validated['nama_lengkap'],
                'email'    => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);
            $userId = $user->id;
        } else {
            $user = Users::find($userId);
        }

      
        $uploadFolder = 'nakes_documents/' . $userId;

        $fotoProfilePath = $request->hasFile('foto_profile')
            ? $request->file('foto_profile')->store($uploadFolder . '/profile', 'public')
            : null;

        $fileKtpPath  = $request->file('file_ktp')->store($uploadFolder . '/ktp', 'public');
        $ijazahPath   = $request->file('ijazah')->store($uploadFolder . '/ijazah', 'public');
        $fileSkckPath = $request->file('file_skck')->store($uploadFolder . '/skck', 'public');
        $fileCvPath   = $request->file('file_cv')->store($uploadFolder . '/cv', 'public');
        $fileStrPath  = $request->file('file_str')->store($uploadFolder . '/str', 'public');
        $fileSipPath  = $request->file('file_sip')->store($uploadFolder . '/sip', 'public');

        $dokumenTambahanPaths = [];
        if ($request->hasFile('dokumen_tambahan')) {
            foreach ($request->file('dokumen_tambahan') as $file) {
                $dokumenTambahanPaths[] = $file->store($uploadFolder . '/tambahan', 'public');
            }
        }

    
        $nakes = TenagaMedis::create([
            'user_id'            => $userId,
            'nik'                => $validated['nik'],
            'nama_lengkap'       => $validated['nama_lengkap'],
            'nama_panggilan'     => $validated['nama_panggilan'],
            'jenis_kelamin'      => $validated['jenis_kelamin'],
            'tempat_lahir'       => $validated['tempat_lahir'],
            'tanggal_lahir'      => $validated['tanggal_lahir'],
            'agama'              => $validated['agama'],
            'no_telp'            => $validated['no_telp'],
            'id_wilayah_layanan' => $validated['id_wilayah_layanan'],
            'alamat_lengkap'     => $validated['alamat_lengkap'],
            'foto_profile'       => $fotoProfilePath,

            'jenis_tenaga_medis' => $validated['jenis_tenaga_medis'],

            'universitas'        => $validated['universitas'],
            'program_studi'      => $validated['program_studi'],
            'tahun_lulus'        => $validated['tahun_lulus'],
            'no_str'             => $validated['no_str'],
            'no_sip'             => $validated['no_sip'],

            'file_ktp'           => $fileKtpPath,
            'ijazah'             => $ijazahPath,
            'file_skck'          => $fileSkckPath,
            'file_cv'            => $fileCvPath,
            'file_str'           => $fileStrPath,
            'file_sip'           => $fileSipPath,

            'tempat_kerja'       => $validated['tempat_kerja'] ?? null,
            'lama_bekerja'       => $validated['lama_bekerja'] ?? null,
            'dokumen_tambahan'   => !empty($dokumenTambahanPaths) ? json_encode($dokumenTambahanPaths) : null,

   
            'status'             => strtolower($validated['status']),
            'admin_notes'        => $validated['admin_notes'] ?? null,
        ]);

     
        if ($nakes->status === 'approved' && $user) {
            if (!$user->roles()->where('user_roles.nama_role', 'nakes')->exists()) {
                $user->roles()->attach('nakes');
            }
        }

        return $nakes->fresh(['user']);
    });

    return response()->json([
        'success' => true,
        'message' => 'Berhasil menambahkan data Nakes baru oleh admin.',
        'data'    => $tenagaMedis
    ], 201);
}
}
