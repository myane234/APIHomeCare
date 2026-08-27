<?php

namespace Database\Seeders;

use App\Models\Legality;
use Illuminate\Database\Seeder;

class LegalitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pasienContent = <<<EOT
### Syarat & Ketentuan Layanan Pasien - Smart Home Care

Selamat datang di Smart Home Care. Mohon untuk membaca Syarat & Ketentuan Layanan ini secara seksama sebelum Anda menggunakan platform kami untuk memesan layanan kesehatan.

1. **Definisi & Penggunaan Layanan**
   - Layanan ini merupakan platform penghubung antara Pasien dengan Tenaga Medis profesional.
   - Pasien diwajibkan memberikan informasi data diri, alamat, dan riwayat kesehatan secara jujur dan akurat.

2. **Prosedur Pemesanan & Pembayaran**
   - Pemesanan layanan home care dilakukan melalui aplikasi atau website resmi.
   - Pembayaran wajib dilakukan sesuai dengan kategori dan metode pembayaran yang telah dipilih sebelum kunjungan tenaga medis.
   - Keterlambatan atau pembatalan sepihak tunduk pada kebijakan pembatalan yang berlaku.

3. **Batasan Tanggung Jawab**
   - Smart Home Care berupaya memastikan kualitas tenaga medis terbaik, namun keputusan tindakan medis spesifik tetap berada di bawah persetujuan pasien dan etika profesi tenaga medis terkait.
   - Dalam kondisi darurat medis (*emergency*), pasien disarankan untuk langsung menghubungi Instalasi Gawat Darurat (IGD) rumah sakit terdekat.

Dengan melanjutkan registrasi atau pemesanan, Anda dinyatakan setuju pada semua poin dalam Syarat & Ketentuan ini.
EOT;

        $nakesContent = <<<EOT
### Syarat & Ketentuan Mitra Tenaga Medis - Smart Home Care

Terima kasih atas bergabungnya Anda sebagai mitra Tenaga Medis (Nakes) di Smart Home Care. Berikut adalah aturan yang wajib Anda sepakati:

1. **Legalitas dan Kualifikasi**
   - Anda menyatakan memiliki Surat Tanda Registrasi (STR) dan Surat Izin Praktik (SIP) yang sah, aktif, dan diakui hukum.
   - Dokumen kualifikasi (ijazah, sertifikat pelatihan) yang diunggah harus asli dan terverifikasi.

2. **Etika & Standar Pelayanan Kunjungan**
   - Melaksanakan pelayanan sesuai dengan SOP klinis yang berlaku dan menjunjung tinggi keselamatan pasien.
   - Berpakaian sopan, datang tepat waktu sesuai jadwal booking, dan berkomunikasi secara profesional dengan pasien.
   - Dilarang keras melakukan transaksi langsung (bypass sistem) dengan pasien di luar platform Smart Home Care.

3. **Bagi Hasil & Pembayaran**
   - Hak bagi hasil / payout akan ditransfer ke rekening bank terdaftar setelah pelayanan dinyatakan selesai oleh pasien dan melalui proses audit sistem.

Melanggar syarat & ketentuan ini dapat mengakibatkan penonaktifan akun kemitraan Anda secara permanen.
EOT;

        $privacyContent = <<<EOT
### Kebijakan Privasi - Smart Home Care

Kebijakan Privasi ini menjelaskan bagaimana kami mengumpulkan, menggunakan, dan melindungi informasi pribadi Anda saat menggunakan platform Smart Home Care.

1. **Informasi yang Kami Kumpulkan**
   - Data Pribadi: Nama, alamat, nomor telepon, alamat email, foto profil, NIK, dan dokumen medis/profesi (untuk tenaga medis).
   - Data Lokasi: Untuk mempertemukan pasien dengan tenaga medis terdekat secara real-time.

2. **Penggunaan Informasi**
   - Memproses pemesanan layanan kesehatan home care.
   - Melakukan verifikasi identitas pasien dan kualifikasi mitra tenaga medis.
   - Peningkatan layanan dan komunikasi terkait transaksi.

3. **Keamanan Data**
   - Kami berkomitmen menjaga keamanan informasi pribadi Anda dan menggunakan enkripsi standar untuk melindunginya.

Dengan menggunakan platform kami, Anda menyetujui Kebijakan Privasi ini.
EOT;

        Legality::updateOrCreate(
            ['key' => 'syarat-ketentuan-pasien'],
            [
                'title' => 'Syarat & Ketentuan Pasien',
                'content' => $pasienContent,
                'is_active' => true,
            ]
        );

        Legality::updateOrCreate(
            ['key' => 'syarat-ketentuan-nakes'],
            [
                'title' => 'Syarat & Ketentuan Tenaga Medis',
                'content' => $nakesContent,
                'is_active' => true,
            ]
        );

        Legality::updateOrCreate(
            ['key' => 'kebijakan-privasi'],
            [
                'title' => 'Kebijakan Privasi',
                'content' => $privacyContent,
                'is_active' => true,
            ]
        );
    }
}
