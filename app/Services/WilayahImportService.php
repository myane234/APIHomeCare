<?php

namespace App\Services;

use App\Models\WilayahImportSource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class WilayahImportService
{
    public function saveApi(array $urls): WilayahImportSource
    {
        return WilayahImportSource::updateOrCreate(['id' => 1], [
            'source_type' => 'api',
            'base_url' => null,
            'provinces_url' => rtrim($urls['provinces_url'], '/'),
            'regencies_url' => rtrim($urls['regencies_url'], '/'),
            'districts_url' => rtrim($urls['districts_url'], '/'),
            'villages_url' => rtrim($urls['villages_url'], '/'),
            'file_path' => null,
            'file_name' => null,
        ]);
    }

    public function saveFile(UploadedFile $file): WilayahImportSource
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['csv', 'json', 'xlsx'], true)) {
            throw new RuntimeException('Format file harus CSV, Excel (.xlsx), atau JSON.');
        }

        $path = $file->storeAs('wilayah-imports', uniqid('wilayah_', true) . '.' . $extension);

        return WilayahImportSource::updateOrCreate(['id' => 1], [
            'source_type' => 'file',
            'base_url' => null,
            'provinces_url' => null,
            'regencies_url' => null,
            'districts_url' => null,
            'villages_url' => null,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
        ]);
    }

    public function getSource(): ?WilayahImportSource
    {
        return WilayahImportSource::find(1) ?? ($this->defaultApiSource());
    }

    public function load(?callable $progress = null): array
    {
        $source = $this->getSource();
        if (!$source) {
            throw new RuntimeException('Sumber wilayah belum dikonfigurasi.');
        }

        return $source->source_type === 'api'
            ? $this->loadApi($this->apiUrls($source), $progress)
            : $this->loadFile($source->file_path);
    }

    private function loadApi(array $urls, ?callable $progress = null): array
    {
        $provinces = $this->requestJson($urls['provinces']);
        if ($progress) {
            $progress('source_loaded', ['total_provinces' => count($provinces)]);
        }

        foreach ($provinces as &$province) {
            $province['regencies'] = $this->requestJson(str_replace('{id_provinsi}', $province['id'], $urls['regencies']));
            if ($progress) {
                $progress('province_loaded', ['province' => $province, 'cities' => count($province['regencies'])]);
            }

            foreach ($province['regencies'] as &$city) {
                $city['districts'] = $this->requestJson(str_replace('{id_kota}', $city['id'], $urls['districts']));
                foreach ($city['districts'] as &$district) {
                    $district['villages'] = $this->requestJson(str_replace('{id_kecamatan}', $district['id'], $urls['villages']));
                }

                if ($progress) {
                    $progress('city_loaded', ['city' => $city]);
                }
            }
        }

        return $provinces;
    }

    private function defaultApiSource(): ?WilayahImportSource
    {
        $urls = config('services.wilayah');
        if (empty($urls['provinces_url'])) {
            return null;
        }

        return $this->saveApi($urls);
    }

    private function apiUrls(WilayahImportSource $source): array
    {
        if ($source->provinces_url && $source->regencies_url && $source->districts_url && $source->villages_url) {
            return [
                'provinces' => $source->provinces_url,
                'regencies' => $source->regencies_url,
                'districts' => $source->districts_url,
                'villages' => $source->villages_url,
            ];
        }

        $baseUrl = rtrim((string) $source->base_url, '/');
        return [
            'provinces' => $baseUrl . '/provinces.json',
            'regencies' => $baseUrl . '/regencies/{id_provinsi}.json',
            'districts' => $baseUrl . '/districts/{id_kota}.json',
            'villages' => $baseUrl . '/villages/{id_kecamatan}.json',
        ];
    }

    private function loadFile(string $path): array
    {
        if (!Storage::exists($path)) {
            throw new RuntimeException('File sumber wilayah tidak ditemukan.');
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $contents = Storage::get($path);

        if ($extension === 'json') {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            return $this->normalizeJson($data);
        }

        $rows = $extension === 'csv'
            ? $this->readCsv($contents)
            : $this->readXlsx(Storage::path($path));

        return $this->rowsToHierarchy($rows);
    }

    private function requestJson(string $url): array
    {
        $response = Http::acceptJson()->timeout(60)->get($url);
        if ($response->failed() || !is_array($response->json())) {
            throw new RuntimeException('Gagal mengambil data wilayah dari: ' . $url);
        }

        return $response->json();
    }

    private function normalizeJson(array $data): array
    {
        if (isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }
        if (isset($data[0]) && isset($data[0]['regencies'])) {
            return $data;
        }
        return $this->rowsToHierarchy($data);
    }

    private function readCsv(string $contents): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $contents);
        rewind($handle);
        $headers = fgetcsv($handle);
        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            if (count(array_filter($values, fn ($value) => $value !== null && $value !== '')) > 0) {
                $rows[] = array_combine($headers, array_pad($values, count($headers), null));
            }
        }
        fclose($handle);
        return $rows;
    }

    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File Excel tidak dapat dibaca.');
        }
        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $sharedXml = new SimpleXMLElement($xml);
            foreach ($sharedXml->si as $item) {
                $shared[] = (string) ($item->t ?? implode('', array_map('strval', $item->r->t ?? [])));
            }
        }
        $sheet = new SimpleXMLElement($zip->getFromName('xl/worksheets/sheet1.xml'));
        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $values = [];
            foreach ($row->c as $cell) {
                $column = preg_replace('/\d+/', '', (string) $cell['r']);
                $value = (string) ($cell->v ?? '');
                $values[$column] = (string) ($cell['t'] === 's' ? ($shared[(int) $value] ?? '') : $value);
            }
            $rows[] = $values;
        }
        $zip->close();
        $headers = array_values($rows[0] ?? []);
        return array_map(fn (array $row) => array_combine($headers, array_pad(array_values($row), count($headers), null)), array_slice($rows, 1));
    }

    private function rowsToHierarchy(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $provinceId = $this->value($row, ['province_id', 'provinsi_id', 'id_provinsi']);
            $provinceName = $this->value($row, ['province_name', 'provinsi_name', 'nama_provinsi']);
            $cityId = $this->value($row, ['regency_id', 'city_id', 'kota_id', 'id_kota']);
            $cityName = $this->value($row, ['regency_name', 'city_name', 'kota_name', 'nama_kota']);
            $districtId = $this->value($row, ['district_id', 'kecamatan_id', 'id_kecamatan']);
            $districtName = $this->value($row, ['district_name', 'kecamatan_name', 'nama_kecamatan']);
            $villageId = $this->value($row, ['village_id', 'kelurahan_id', 'id_kelurahan']);
            $villageName = $this->value($row, ['village_name', 'kelurahan_name', 'nama_kelurahan']);
            if (!$provinceId || !$provinceName) {
                continue;
            }
            $provinceIndex = $this->findOrAdd($result, 'id', $provinceId, ['id' => $provinceId, 'name' => $provinceName, 'regencies' => []]);
            if (!$cityId || !$cityName) continue;
            $cityIndex = $this->findOrAdd($result[$provinceIndex]['regencies'], 'id', $cityId, ['id' => $cityId, 'province_id' => $provinceId, 'name' => $cityName, 'districts' => []]);
            if (!$districtId || !$districtName) continue;
            $districtIndex = $this->findOrAdd($result[$provinceIndex]['regencies'][$cityIndex]['districts'], 'id', $districtId, ['id' => $districtId, 'regency_id' => $cityId, 'name' => $districtName, 'villages' => []]);
            if ($villageId && $villageName) $result[$provinceIndex]['regencies'][$cityIndex]['districts'][$districtIndex]['villages'][] = ['id' => $villageId, 'district_id' => $districtId, 'name' => $villageName];
        }
        return $result;
    }

    private function value(array $row, array $keys): ?string
    {
        foreach ($keys as $key) if (isset($row[$key]) && trim((string) $row[$key]) !== '') return trim((string) $row[$key]);
        return null;
    }

    private function findOrAdd(array &$items, string $key, string $value, array $item): int
    {
        foreach ($items as $index => $existing) if ((string) ($existing[$key] ?? '') === $value) return $index;
        $items[] = $item;
        return array_key_last($items);
    }
}