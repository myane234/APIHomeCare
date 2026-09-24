<?php

namespace App\Http\Controllers;

use App\Models\ContentManagement;
use App\Models\GlobalConfig;
use App\Models\PesanKontak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * @group CMS Hubungi Kami / Contact Us
 *
 * Endpoint CMS untuk Pengelolaan Halaman Hubungi Kami & Pesan Masuk
 */
class HubungiKamiController extends Controller
{
    /**
     * Public API: Mengambil data konten halaman Hubungi Kami
     */
    /**
     * Public API: Mengambil data konten halaman Hubungi Kami
     */
    public function getContentPublic(Request $request)
    {
        $content = ContentManagement::firstOrCreate([]);
        $global = GlobalConfig::first();

        // Tarik data profil pengguna jika sedang login
        $user = auth('sanctum')->user() ?? $request->user();
        $userProfile = null;

        if ($user) {
            $pasien = $user->pasien;
            $nakes  = $user->tenagaMedis;
            $nama   = $pasien?->nama_lengkap ?? $nakes?->nama_lengkap ?? $user->name ?? null;
            $noWa   = $pasien?->no_hp ?? $nakes?->no_telp ?? null;

            $userProfile = [
                'id_user' => $user->id_user,
                'nama'    => $nama,
                'email'   => $user->email,
                'no_wa'   => $noWa,
                'no_hp'   => $noWa,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'hubungi_banner'          => $content->hubungi_banner_url,
                'hubungi_banner_text'     => $content->hubungi_banner_text ?? 'Hubungi Kami',
                'hubungi_heading'         => $content->hubungi_heading ?? 'Ada Pertanyaan? Kami Siap Membantu Anda',
                'hubungi_description'     => $content->hubungi_description ?? 'Silakan tinggalkan pesan atau hubungi tim customer service kami untuk informasi lebih lanjut mengenai layanan Home Care.',
                'hubungi_phone'           => $content->hubungi_phone ?: ($global->phone_number ?? null),
                'hubungi_email'           => $content->hubungi_email ?: ($global->email ?? null),
                'hubungi_whatsapp'        => $content->hubungi_whatsapp ?: ($global->whatsapp_number ?? null),
                'hubungi_address'         => $content->hubungi_address ?: ($global->address ?? null),
                'hubungi_maps_link'       => $content->hubungi_maps_link,
                'hubungi_jam_operasional' => $content->hubungi_jam_operasional ?? 'Senin - Minggu: 08:00 - 20:00 WIB',
                'user_profile'            => $userProfile,
            ]
        ], 200);
    }

    /**
     * Protected API: Mengambil informasi profile user login untuk auto-fill form Hubungi Kami
     */
    public function userInfo(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $pasien = $user->pasien;
        $nakes  = $user->tenagaMedis;

        $nama = $pasien?->nama_lengkap ?? $nakes?->nama_lengkap ?? $user->name ?? null;
        $noWa = $pasien?->no_hp ?? $nakes?->no_telp ?? null;

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil informasi profil user untuk Hubungi Kami',
            'data' => [
                'id_user' => $user->id_user,
                'nama'    => $nama,
                'email'   => $user->email,
                'no_wa'   => $noWa,
                'no_hp'   => $noWa,
            ],
        ], 200);
    }

    /**
     * Public / Authenticated API: Pengiriman pesan/inquiry dari form Hubungi Kami
     */
    public function kirimPesan(Request $request)
    {
        $user = auth('sanctum')->user() ?? $request->user();

        // Jika user terautentikasi (login), otomatis tarik data dari profil jika input kosong
        if ($user) {
            $pasien = $user->pasien;
            $nakes  = $user->tenagaMedis;
            $namaProfile = $pasien?->nama_lengkap ?? $nakes?->nama_lengkap ?? $user->name;
            $noWaProfile = $pasien?->no_hp ?? $nakes?->no_telp;

            if (!$request->filled('nama') && $namaProfile) {
                $request->merge(['nama' => $namaProfile]);
            }
            if (!$request->filled('email') && $user->email) {
                $request->merge(['email' => $user->email]);
            }
            if (!$request->filled('no_hp') && !$request->filled('no_wa') && $noWaProfile) {
                $request->merge(['no_hp' => $noWaProfile, 'no_wa' => $noWaProfile]);
            }
        }

        $validated = $request->validate([
            'nama'   => 'required|string|max:255',
            'email'  => 'required|email|max:255',
            'no_hp'  => 'nullable|string|max:25',
            'no_wa'  => 'nullable|string|max:25',
            'subjek' => 'nullable|string|max:255',
            'pesan'  => 'required|string',
        ], [
            'nama.required'  => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email'    => 'Format email tidak valid.',
            'pesan.required' => 'Pesan wajib diisi.',
        ]);

        // Validasi email: cek disposable/palsu (blacklist domain + MX record DNS)
        $emailError = $this->validateEmailReliability($validated['email']);
        if ($emailError) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => [
                    'email' => [$emailError],
                ],
            ], 422);
        }

        // Jika no_wa dikirim tetapi no_hp kosong, gunakan no_wa sebagai nilai no_hp
        if (empty($validated['no_hp']) && !empty($validated['no_wa'])) {
            $validated['no_hp'] = $validated['no_wa'];
        }
        unset($validated['no_wa']);

        $validated['status'] = 'belum_dibaca';

        $pesan = PesanKontak::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pesan Anda berhasil terkirim. Tim kami akan segera menghubungi Anda.',
            'data'    => $pesan,
        ], 201);
    }

    /**
     * Validasi keandalan email:
     * Layer 1 — Cek domain terhadap blacklist layanan email disposable/sementara.
     * Layer 2 — Cek keberadaan MX record DNS pada domain email.
     *
     * @param  string $email
     * @return string|null  Pesan error jika email tidak valid, null jika valid.
     */
    private function validateEmailReliability(string $email): ?string
    {
        // Layer 1: Blacklist domain email disposable / sementara
        $disposableDomains = [
            'mailinator.com', 'guerrillamail.com', 'guerrillamail.net', 'guerrillamail.org',
            'guerrillamail.biz', 'guerrillamail.de', 'guerrillamail.info',
            'tempmail.com', 'temp-mail.org', 'temp-mail.io', 'throwam.com',
            'yopmail.com', 'yopmail.fr', 'cool.fr.nf', 'jetable.fr.nf',
            'nospam.ze.tc', 'nomail.xl.cx', 'mega.zik.dj', 'speed.1s.fr',
            'courriel.fr.nf', 'moncourrier.fr.nf', 'monemail.fr.nf', 'monmail.fr.nf',
            'sharklasers.com', 'guerrillamailblock.com', 'grr.la', 'ozziesok.com',
            'spam4.me', 'trashmail.com', 'trashmail.at', 'trashmail.io',
            'trashmail.me', 'trashmail.net', 'dispostable.com', 'maildrop.cc',
            'mailnull.com', 'spamgourmet.com', 'spamgourmet.net', 'spamgourmet.org',
            'binkmail.com', 'bob.email', 'dropmail.me', 'emlpro.com',
            'emltmp.com', 'fakeinbox.com', 'filzmail.com', 'fleckens.hu',
            'getonemail.com', 'girlsundertheinfluence.com', 'haltospam.com',
            'ieatspam.eu', 'ieatspam.info', 'inoutmail.de', 'inoutmail.eu',
            'inoutmail.info', 'inoutmail.net', 'jetable.com', 'jetable.fr.nf',
            'kasmail.com', 'killmail.com', 'klassmaster.com', 'klzlk.com',
            'kurzepost.de', 'lol.ovpn.to', 'lookugly.com', 'mailexpire.com',
            'mailme.lv', 'mailnew.com', 'mailscrap.com', 'mailsiphon.com',
            'mailzilla.com', 'mbx.cc', 'mt2009.com', 'mt2014.com',
            'notsharingmy.info', 'objectmail.com', 'obobbo.com', 'odaymail.com',
            'onewaymail.com', 'pookmail.com', 'privacy.net', 'proxymail.eu',
            'rcpt.at', 'recode.me', 'recursor.net', 'regbypass.com',
            'safetymail.info', 'safetypost.de', 'selfdestructingmail.com',
            'sendspamhere.com', 'shieldedmail.com', 'skeefmail.com', 'slopsbox.com',
            'smellfear.com', 'snakemail.com', 'sneakemail.com', 'sofimail.com',
            'sogetthis.com', 'spam.la', 'spamavert.com', 'spambox.us',
            'spamcannon.com', 'spamcannon.net', 'spamcero.com', 'spamcon.org',
            'spamday.com', 'spamex.com', 'spamfree24.org', 'spamgoes.in',
            'spamherelots.com', 'spamhereplease.com', 'spamhole.com', 'spamify.com',
            'spaminator.de', 'spamkill.info', 'spaml.com', 'spaml.de',
            'spammotel.com', 'spamobox.com', 'spamoff.de', 'spamslicer.com',
            'spamstack.net', 'spamthis.co.uk', 'spamthisplease.com', 'spamtroll.net',
            'supergreatmail.com', 'suremail.info', 'sweetxxx.de', 'tafmail.com',
            'temporaryemail.net', 'temporaryemail.us', 'temporaryforwarding.com',
            'temporaryinbox.com', 'tempr.email', 'thankyou2010.com', 'thisisnotmyrealemail.com',
            'throwam.com', 'tilien.com', 'tmailinator.com', 'toomail.biz',
            'topranklist.de', 'tradermail.info', 'trash-mail.at', 'trash-mail.com',
            'trash-mail.de', 'trash-mail.ga', 'trash-mail.io', 'trash-mail.net',
            'trash2009.com', 'trashdevil.com', 'trashdevil.de', 'trashemail.de',
            'trashimail.de', 'trashmail.app', 'trashmail.fr', 'trashmail.io',
            'trashmail.org', 'trashmail.xyz', 'trbvm.com', 'trkur.com',
            'turual.com', 'twinmail.de', 'tyldd.com', 'uggsrock.com',
            'umail.net', 'upliftnow.com', 'uplipht.com', 'uroid.com',
            'veryrealemail.com', 'vidchart.com', 'viditag.com', 'vomoto.com',
            'vpn.st', 'vsimcard.com', 'walkmail.net', 'walkmail.ru',
            'wetrainbayarea.com', 'wetrainbayarea.org', 'whyspam.me',
            'willhackforfood.biz', 'wuzupmail.net', 'www.e4ward.com', 'www.mailinator.com',
            'xagloo.com', 'xemaps.com', 'xents.com', 'xmaily.com',
            'xoxy.net', 'xyzfree.net', 'yapped.net', 'yeah.net',
            'yogamaven.com', 'yopmail.net', 'yopmail.pp.ua', 'youmails.net',
            'yourspamgoeshere.com', 'zehnminutenmail.de', 'zippymail.info',
            'zoemail.com', 'zoemail.net', 'zoemail.org', 'zomg.info',
        ];

        $domain = strtolower(substr(strrchr($email, '@'), 1));

        if (in_array($domain, $disposableDomains)) {
            return 'Email yang dimasukkan menggunakan layanan email sementara. Silakan gunakan email aktif Anda (contoh: Gmail, Yahoo, Outlook).';
        }

        // Layer 2: Cek MX Record DNS — pastikan domain memiliki mail server yang valid
        if (!checkdnsrr($domain, 'MX')) {
            return 'Email yang dimasukkan tidak valid karena domain-nya tidak memiliki server email aktif. Silakan periksa kembali email Anda.';
        }

        return null;
    }

    /**
     * Admin API: Mengambil data setting CMS Hubungi Kami
     */
    public function getSettingsAdmin()
    {
        $content = ContentManagement::firstOrCreate([]);

        return response()->json([
            'success' => true,
            'data' => [
                'hubungi_banner'          => $content->hubungi_banner_url,
                'hubungi_banner_text'     => $content->hubungi_banner_text,
                'hubungi_heading'         => $content->hubungi_heading,
                'hubungi_description'     => $content->hubungi_description,
                'hubungi_phone'           => $content->hubungi_phone,
                'hubungi_email'           => $content->hubungi_email,
                'hubungi_whatsapp'        => $content->hubungi_whatsapp,
                'hubungi_address'         => $content->hubungi_address,
                'hubungi_maps_link'       => $content->hubungi_maps_link,
                'hubungi_jam_operasional' => $content->hubungi_jam_operasional,
            ]
        ], 200);
    }

    /**
     * Admin API: Memperbarui setting CMS Hubungi Kami
     */
    public function updateSettingsAdmin(Request $request)
    {
        $content = ContentManagement::firstOrCreate([]);

        $validated = $request->validate([
            'hubungi_banner'          => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'hubungi_banner_text'     => 'nullable|string|max:255',
            'hubungi_heading'         => 'nullable|string|max:255',
            'hubungi_description'     => 'nullable|string',
            'hubungi_phone'           => 'nullable|string|max:20',
            'hubungi_email'           => 'nullable|email|max:255',
            'hubungi_whatsapp'        => 'nullable|string|max:20',
            'hubungi_address'         => 'nullable|string',
            'hubungi_maps_link'       => 'nullable|string',
            'hubungi_jam_operasional' => 'nullable|string',
        ]);

        if ($request->hasFile('hubungi_banner')) {
            if ($content->hubungi_banner) {
                Storage::disk('public')->delete($content->hubungi_banner);
            }
            $path = $request->file('hubungi_banner')->store('content', 'public');
            $validated['hubungi_banner'] = $path;
        }

        $content->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan halaman Hubungi Kami berhasil diperbarui.',
            'data' => [
                'hubungi_banner'          => $content->hubungi_banner_url,
                'hubungi_banner_text'     => $content->hubungi_banner_text,
                'hubungi_heading'         => $content->hubungi_heading,
                'hubungi_description'     => $content->hubungi_description,
                'hubungi_phone'           => $content->hubungi_phone,
                'hubungi_email'           => $content->hubungi_email,
                'hubungi_whatsapp'        => $content->hubungi_whatsapp,
                'hubungi_address'         => $content->hubungi_address,
                'hubungi_maps_link'       => $content->hubungi_maps_link,
                'hubungi_jam_operasional' => $content->hubungi_jam_operasional,
            ]
        ], 200);
    }

    /**
     * Admin API: Daftar Pesan Masuk dari Pengunjung
     */
    public function indexPesan(Request $request)
    {
        $query = PesanKontak::query();

        // Filter Status: belum_dibaca, sudah_dibaca, dibalas
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->filled('search') || $request->filled('q')) {
            $searchTerm = $request->input('search', $request->input('q'));
            $query->where(function ($q) use ($searchTerm) {
                $q->where('nama', 'like', '%' . $searchTerm . '%')
                  ->orWhere('email', 'like', '%' . $searchTerm . '%')
                  ->orWhere('no_hp', 'like', '%' . $searchTerm . '%')
                  ->orWhere('subjek', 'like', '%' . $searchTerm . '%')
                  ->orWhere('pesan', 'like', '%' . $searchTerm . '%');
            });
        }

        $query->orderBy('created_at', 'desc');

        $perPage = $request->input('per_page', 10);
        if ($perPage === 'all') {
            $data = $query->get();
        } else {
            $perPage = (int) $perPage > 0 ? (int) $perPage : 10;
            $data = $query->paginate($perPage);
        }

        $totalBelumDibaca = PesanKontak::where('status', 'belum_dibaca')->count();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar pesan masuk',
            'unread_count' => $totalBelumDibaca,
            'data' => $data,
        ], 200);
    }

    /**
     * Admin API: Detail Pesan Masuk (Auto update status menjadi 'sudah_dibaca')
     */
    public function showPesan($id)
    {
        $pesan = PesanKontak::find($id);

        if (!$pesan) {
            return response()->json([
                'success' => false,
                'message' => 'Pesan tidak ditemukan.',
            ], 404);
        }

        if ($pesan->status === 'belum_dibaca') {
            $pesan->status = 'sudah_dibaca';
            $pesan->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil detail pesan',
            'data'    => $pesan,
        ], 200);
    }

    /**
     * Admin API: Update Status / Catatan Pesan
     */
    public function updatePesanStatus(Request $request, $id)
    {
        $pesan = PesanKontak::find($id);

        if (!$pesan) {
            return response()->json([
                'success' => false,
                'message' => 'Pesan tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'status'        => 'required|in:belum_dibaca,sudah_dibaca,dibalas',
            'catatan_admin' => 'nullable|string',
        ]);

        $pesan->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Status pesan berhasil diperbarui.',
            'data'    => $pesan,
        ], 200);
    }

    /**
     * Admin API: Hapus Pesan
     */
    public function destroyPesan($id)
    {
        $pesan = PesanKontak::find($id);

        if (!$pesan) {
            return response()->json([
                'success' => false,
                'message' => 'Pesan tidak ditemukan.',
            ], 404);
        }

        $pesan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pesan berhasil dihapus.',
        ], 200);
    }
}
