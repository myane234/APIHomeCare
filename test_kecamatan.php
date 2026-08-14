<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Test 1: cek nama_regency muncul
$kec = \App\Models\Kecamatan::with(['kotaKabupaten'])->first();
echo "=== Test Kecamatan ===\n";
echo "id_kecamatan : " . $kec->id_kecamatan . "\n";
echo "regency_id   : " . $kec->regency_id . "\n";
echo "nama_kecamatan: " . $kec->nama_kecamatan . "\n";
echo "nama_regency : " . ($kec->nama_regency ?? 'NULL') . "\n";
echo "kotaKabupaten: " . ($kec->kotaKabupaten ? $kec->kotaKabupaten->nama_kota : 'NULL') . "\n";
echo "\n";

// Test 2: cek paginate 3 records
$page = \App\Models\Kecamatan::with(['kotaKabupaten'])->paginate(3);
echo "=== Paginate Test (page 1, per_page 3) ===\n";
foreach ($page->items() as $item) {
    echo "- " . $item->nama_kecamatan . " | regency: " . $item->regency_id . " | nama_regency: " . ($item->nama_regency ?? 'NULL') . "\n";
}
echo "total: " . $page->total() . " | last_page: " . $page->lastPage() . "\n";
