<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenagaMedis extends Model
{
    use HasFactory;

    protected $table = 'tenaga_medis';
    protected $primaryKey = 'id_tenaga_medis';

    protected $fillable = [
        'id_user',
        'id_pasien',
        'id_wilayah_layanan',

        // Data Diri
        'nik',
        'nama_lengkap',
        'nama_panggilan',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'agama',
        'no_telp',
        'alamat_lengkap',
        'foto_profile',

        // Pendidikan & Legalitas
        'jenis_tenaga_medis',
        'universitas',
        'program_studi',
        'tahun_lulus',
        'no_str',
        'no_sip',

        // File Berkas Utama
        'file_ktp',
        'ijazah',
        'file_skck',
        'file_cv',
        'file_str',
        'file_sip',

        // Nullable Fields
        'tempat_kerja',
        'lama_bekerja',
        'dokumen_tambahan',

        // Status
        'status',
        'admin_notes',

        // Kelengkapan Data Setelah Approved
        'pas_foto',
        'no_npwp',
        'foto_npwp',
        'id_bank',
        'nama_pemilik_rekening',
        'no_rekening',
        'file_pakta_integritas',
        'is_data_complete',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'dokumen_tambahan' => 'array',
        'tanggal_lahir'    => 'date',
        'is_data_complete' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(Users::class, 'id_user', 'id_user');
    }

    public function pasien()
    {
        return $this->belongsTo(Pasien::class, 'id_pasien', 'id_pasien');
    }

    public function wilayahLayanan()
{
    return $this->belongsTo(WilayahLayanan::class, 'id_wilayah_layanan', 'id_provinsi');
}

    public function kategoriLayanan()
    {
        return $this->belongsToMany(
            KategoriLayanan::class,
            'kategori_layanan_tenaga_medis',
            'id_tenaga_medis',
            'id_kategori_layanan'
        );
    }

    public function bank()
    {
        return $this->belongsTo(MasterBank::class, 'id_bank', 'id_bank');
    }
}