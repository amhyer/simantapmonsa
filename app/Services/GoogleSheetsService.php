<?php

namespace App\Services;

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;
use Google\Service\Sheets\BatchUpdateValuesRequest;
use Illuminate\Support\Facades\Log;

class GoogleSheetsService
{
    protected $client;
    protected $service;

    public function __construct()
    {
        $this->client = $this->getClient();
        $this->service = new Sheets($this->client);
    }

    protected function getClient(): Client
    {
        $client = new Client();
        $client->setApplicationName('SIMANTAP');
        $client->setScopes([Sheets::SPREADSHEETS]);
        $client->setAccessType('offline');

        $authConfig = config('google.service_account_path');
        if (!str_starts_with($authConfig, '/') && !str_starts_with($authConfig, 'C:') && !str_starts_with($authConfig, 'D:')) {
            $authConfig = base_path('storage/app/' . $authConfig);
        }
        $client->setAuthConfig($authConfig);

        $cacertPath = base_path('storage/app/cacert.pem');
        if (file_exists($cacertPath)) {
            $client->setHttpClient(new \GuzzleHttp\Client([
                'verify' => $cacertPath,
            ]));
        }

        return $client;
    }

    public function getSheetsService(): Sheets
    {
        return $this->service;
    }

    /**
     * Get or create a tab in the spreadsheet
     */
    public function getOrCreateTab(string $spreadsheetId, string $tabName): void
    {
        $spreadsheet = $this->service->spreadsheets->get($spreadsheetId);
        $sheets = $spreadsheet->getSheets();

        foreach ($sheets as $sheet) {
            if ($sheet->getProperties()->getTitle() === $tabName) {
                return; // Tab already exists
            }
        }

        try {
            $request = new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest([
                'requests' => [
                    [
                        'addSheet' => [
                            'properties' => [
                                'title' => $tabName,
                            ],
                        ],
                    ],
                ],
            ]);

            $this->service->spreadsheets->batchUpdate($spreadsheetId, $request);
        } catch (\Exception $e) {
            // Sheet might already exist, ignore
            Log::warning("Failed to create tab {$tabName}: " . $e->getMessage());
        }
    }

    /**
     * Ensure headers exist in the tab
     */
    public function ensureHeaders(string $spreadsheetId, string $tabName, array $headers): void
    {
        $range = "'{$tabName}'!1:1";
        $response = $this->service->spreadsheets_values->get($spreadsheetId, $range);
        $values = $response->getValues();

        if (empty($values) || empty($values[0])) {
            $this->writeRow($spreadsheetId, "'{$tabName}'!A1", [$headers]);
        }
    }

    /**
     * Write a single row at a specific range
     */
    public function writeRow(string $spreadsheetId, string $range, array $values): void
    {
        $body = new ValueRange(['values' => $values]);
        $this->service->spreadsheets_values->update(
            $spreadsheetId,
            $range,
            $body,
            ['valueInputOption' => 'RAW']
        );
    }

    /**
     * Sync data to a specific sheet tab (append)
     */
    public function syncRow(string $spreadsheetId, string $tabName, array $row): void
    {
        $body = new ValueRange(['values' => [$row]]);
        $this->service->spreadsheets_values->append(
            $spreadsheetId,
            "'{$tabName}'!A:Z",
            $body,
            ['valueInputOption' => 'RAW', 'insertDataOption' => 'INSERT_ROWS']
        );
    }

    /**
     * Batch sync multiple rows
     */
    public function syncBatch(string $spreadsheetId, string $tabName, array $rows): void
    {
        if (empty($rows)) return;

        $body = new BatchUpdateValuesRequest([
            'valueInputOption' => 'RAW',
            'data' => [
                [
                    'range' => "'{$tabName}'!A:Z",
                    'values' => $rows,
                ],
            ],
        ]);

        $this->service->spreadsheets_values->batchUpdate(
            $spreadsheetId,
            $body
        );
    }

    /**
     * Clear all data in a sheet tab (keep headers)
     */
    public function clearSheet(string $spreadsheetId, string $tabName): void
    {
        $spreadsheet = $this->service->spreadsheets->get($spreadsheetId);
        foreach ($spreadsheet->getSheets() as $sheet) {
            if ($sheet->getProperties()->getTitle() === $tabName) {
                $sheetId = $sheet->getProperties()->getSheetId();
                if ($sheetId === null) continue;

                $request = new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest([
                    'requests' => [
                        [
                            'deleteDimension' => [
                                'range' => [
                                    'sheetId' => $sheetId,
                                    'dimension' => 'ROWS',
                                    'startIndex' => 1,
                                    'endIndex' => max(2, $sheet->getProperties()->getGridProperties()->getRowCount()),
                                ],
                            ],
                        ],
                    ],
                ]);

                try {
                    $this->service->spreadsheets->batchUpdate($spreadsheetId, $request);
                } catch (\Exception $e) {
                    // Sheet might be empty, ignore
                }
                return;
            }
        }
    }

    /**
     * Build a row for a specific type - EXACTLY matching GAS column order
     * Each table maps to KOL_xxx from the GAS script
     */
    public function buildRow(string $type, $model): array
    {
        return match ($type) {
            // PENGGUNA: ID, Nama Lengkap, Nama Pengguna, Kata Sandi, Peran,
            //           Terhubung Dengan, Kelas / Mata Pelajaran, Aktif, Terakhir Masuk
            'pengguna' => [
                $model->uuid ?? $model->id ?? '',
                $model->nama_lengkap ?? '',
                $model->nama_pengguna ?? '',
                '',
                $model->peran ?? '',
                is_array($model->terhubung_dengan) ? implode(', ', $model->terhubung_dengan) : ($model->terhubung_dengan ?? ''),
                $model->kelas_mata_pelajaran ?? '',
                $model->aktif ? 'Ya' : 'Tidak',
                $model->terakhir_masuk ?? '',
            ],

            // SISWA: ID, Guru ID, Nama Guru, NIS, Nama Peserta Didik, Kelas,
            //        Jenis Kelamin, Nama Orang Tua, Aktif, Rekaman (JSON)
            'siswa' => [
                $model->uuid ?? '',
                $model->guru_id ?? '',
                $model->nama_guru ?? ($model->guru->nama_lengkap ?? ''),
                $model->nis ?? '',
                $model->nama_peserta_didik ?? '',
                $model->kelas ?? '',
                $model->jenis_kelamin ?? '',
                $model->nama_orang_tua ?? '',
                $model->aktif ? 'Ya' : 'Tidak',
                $this->jsonAman($model->rekaman),
            ],

            // NILAI: ID, Guru ID, Nama Guru, Siswa ID, NIS, Nama Peserta Didik,
            //        Tanggal, Jenis, Judul Penilaian, Mata Pelajaran, Nilai, Rekaman (JSON)
            'nilai' => [
                $model->uuid ?? '',
                $model->guru_id ?? '',
                $model->nama_guru ?? ($model->guru->nama_lengkap ?? ''),
                $model->siswa_id ?? '',
                $model->siswa->nis ?? '',
                $model->siswa->nama_peserta_didik ?? ($model->nama_siswa ?? ''),
                $model->tanggal ?? '',
                $model->jenis ?? '',
                $model->judul_penilaian ?? '',
                $model->mata_pelajaran ?? '',
                $model->nilai ?? 0,
                $this->jsonAman($model->rekaman),
            ],

            // KEHADIRAN: ID, Guru ID, Nama Guru, Siswa ID, NIS, Nama Peserta Didik,
            //            Tanggal, Kode, Status, Keterangan, Rekaman (JSON)
            'kehadiran' => [
                $model->uuid ?? '',
                $model->guru_id ?? '',
                $model->nama_guru ?? ($model->guru->nama_lengkap ?? ''),
                $model->siswa_id ?? '',
                $model->siswa->nis ?? '',
                $model->siswa->nama_peserta_didik ?? '',
                $model->tanggal ?? '',
                $model->kode ?? '',
                $model->status ?? '',
                $model->keterangan ?? '',
                $this->jsonAman($model->rekaman),
            ],

            // MATERI: ID, Guru ID, Nama Guru, Tanggal, Judul, Mata Pelajaran, Kelas,
            //         Tujuan Pembelajaran, Materi Pokok, Kegiatan, Tautan Sumber, Rekaman (JSON)
            'materi' => [
                $model->uuid ?? '',
                $model->guru_id ?? '',
                $model->nama_guru ?? ($model->guru->nama_lengkap ?? ''),
                $model->tanggal ?? '',
                $model->judul ?? '',
                $model->mata_pelajaran ?? '',
                $model->kelas ?? '',
                $model->tujuan_pembelajaran ?? '',
                $model->materi_pokok ?? '',
                $model->kegiatan ?? '',
                $model->tautan_sumber ?? '',
                $this->jsonAman($model->rekaman),
            ],

            // KUIS: ID, Guru ID, Nama Guru, Tanggal, Judul, Mata Pelajaran, Kelas,
            //       Mode, Sumber Soal, Tautan Soal, Materi ID, KKM,
            //       Batas Waktu (menit), Aktif, Jumlah Soal, Rekaman (JSON)
            'kuis' => [
                $model->uuid ?? '',
                $model->guru_id ?? '',
                $model->nama_guru ?? ($model->guru->nama_lengkap ?? ''),
                $model->tanggal ?? '',
                $model->judul ?? '',
                $model->mata_pelajaran ?? '',
                $model->kelas ?? '',
                $model->mode === 'tka' ? 'Latihan TKA' : 'Kuis harian',
                ($model->sumber ?? '') === 'luar' ? 'Tautan luar' : 'Disusun di aplikasi',
                ($model->sumber ?? '') === 'luar' ? ($model->tautan_soal ?? '') : '',
                $model->materi_id ?? '',
                $model->kkm ?? 70,
                $model->batas_waktu ?? 0,
                $model->aktif ? 'Ya' : 'Tidak',
                $model->jumlah_soal ?? 0,
                $this->jsonAman($model->rekaman),
            ],

            // HASIL KUIS: ID, Guru ID, Nama Guru, Kuis ID, Judul Kuis, Siswa ID, NIS,
            //             Nama Peserta Didik, Waktu, Benar, Total, Skor, Tuntas,
            //             Durasi (detik), Sumber Soal, Diisi Oleh, Catatan Siswa, Rekaman (JSON)
            'hasil_kuis' => [
                $model->uuid ?? '',
                $model->guru_id ?? '',
                $model->nama_guru ?? ($model->guru->nama_lengkap ?? ''),
                $model->kuis_id ?? '',
                $model->kuis->judul ?? ($model->judul_kuis ?? ''),
                $model->siswa_id ?? '',
                $model->siswa->nis ?? '',
                $model->siswa->nama_peserta_didik ?? ($model->nama_siswa ?? ''),
                $model->waktu ?? '',
                $model->benar ?? '',
                $model->total ?? '',
                $model->skor ?? 0,
                $model->tuntas ? 'Ya' : 'Belum',
                $model->durasi ?? 0,
                $model->sumber_soal ?? '',
                $model->diisi_oleh ?? '',
                $model->catatan_siswa ?? '',
                $this->jsonAman($model->rekaman),
            ],

            // CATATAN: ID, Guru ID, Nama Guru, Siswa ID, Nama Peserta Didik,
            //          Tanggal, Jenis, Catatan, Rekaman (JSON)
            'catatan' => [
                $model->uuid ?? '',
                $model->guru_id ?? '',
                $model->nama_guru ?? ($model->guru->nama_lengkap ?? ''),
                $model->siswa_id ?? '',
                $model->siswa->nama_peserta_didik ?? ($model->nama_siswa ?? ''),
                $model->tanggal ?? '',
                $model->jenis ?? '',
                $model->catatan ?? '',
                $this->jsonAman($model->rekaman),
            ],

            // DIMENSI: ID, Guru ID, Nama Guru, Siswa ID, Nama Peserta Didik,
            //          No Dimensi, Dimensi, Skor, Predikat, Catatan, Rekaman (JSON)
            'dimensi' => [
                $model->uuid ?? '',
                $model->guru_id ?? '',
                $model->nama_guru ?? ($model->guru->nama_lengkap ?? ''),
                $model->siswa_id ?? '',
                $model->siswa->nama_peserta_didik ?? ($model->nama_siswa ?? ''),
                $model->no_dimensi ?? '',
                $model->dimensi ?? '',
                $model->skor ?? 0,
                $model->predikat ?? '',
                $model->catatan ?? '',
                $this->jsonAman($model->rekaman),
            ],

            // 7 KEBIASAAN: ID, Siswa ID, Tanggal, Bangun Pagi, Beribadah, Berolahraga,
            //              Makan Sehat dan Bergizi, Gemar Belajar, Bermasyarakat, Tidur Cepat,
            //              Catatan Orang Tua, Diisi Oleh, Waktu Simpan
            'kebiasaan' => [
                $model->uuid ?? '',
                $model->siswa_id ?? '',
                $model->tanggal ?? '',
                $model->bangun_pagi ?? 0,
                $model->beribadah ?? 0,
                $model->berolahraga ?? 0,
                $model->makan_sehat ?? 0,
                $model->gemar_belajar ?? 0,
                $model->bermasyarakat ?? 0,
                $model->tidur_cepat ?? 0,
                $model->catatan_orang_tua ?? '',
                $model->diisi_oleh ?? '',
                $model->waktu_simpan ?? '',
            ],

            // PENGATURAN GURU: Guru ID, Nama Guru, Mata Pelajaran, Kelas, KKM, Semester,
            //                   Tahun Pelajaran, Fase, Pembaruan, Pengaturan (JSON), Sistem (JSON)
            'pengaturan' => [
                $model->guru_id ?? '',
                $model->nama_guru ?? ($model->guru->nama_lengkap ?? ''),
                $model->mata_pelajaran ?? '',
                $model->kelas ?? '',
                $model->kkm ?? 70,
                $model->semester ?? '',
                $model->tahun_pelajaran ?? '',
                $model->fase ?? '',
                $model->pembaruan ?? '',
                $this->jsonAman($model->pengaturan),
                $this->jsonAman($model->sistem),
            ],

            // SEKOLAH: Bagian, Kunci, Nilai
            // This is a key-value store, each row is one setting
            'sekolah' => [
                $model['bagian'] ?? '',
                $model['kunci'] ?? '',
                $model['nilai'] ?? '',
            ],

            // RINGKASAN GURU: Guru ID, Nama Guru, Mata Pelajaran, Kelas, KKM, Semester,
            //                 Tahun Pelajaran, Jumlah Siswa, Rata-rata, Tuntas, Belum Tuntas,
            //                 ... (many more columns)
            'ringkasan' => $this->buildRingkasanRow($model),

            // AKTIVITAS: Waktu, Nama Pengguna, Peran, Kegiatan, Rincian, Jumlah Data
            'aktivitas' => [
                $model->created_at ?? '',
                $model->nama_guru ?? '',
                $model->jenis ?? '',
                $model->judul ?? '',
                $model->deskripsi ?? '',
                '',
            ],

            default => [],
        };
    }

    /**
     * Build RINGKASAN GURU row matching GAS KOL_RINGKAS exactly
     */
    protected function buildRingkasanRow($model): array
    {
        $dimensi = $model->dimensi ?? [];
        $kaih = $model->kaih ?? [];
        $kaihPer = is_array($kaih['per'] ?? null) ? $kaih['per'] : [];
        $kegiatan = $model->kegiatan ?? [];

        return [
            $model->guru_id ?? '',
            $model->nama_guru ?? ($model->guru->nama_lengkap ?? ''),
            $model->mata_pelajaran ?? '',
            $model->kelas ?? '',
            $model->kkm ?? 70,
            $model->semester ?? '',
            $model->tahun_pelajaran ?? '',
            $model->jumlah_siswa ?? 0,
            $model->rata_rata ?? 0,
            $model->tuntas ?? 0,
            $model->belum_tuntas ?? 0,
            $model->ketuntasan_persen ?? 0,
            $model->kehadiran_persen ?? 0,
            // Rata per komponen
            $model->rata_tugas ?? 0,
            $model->rata_ulangan_harian ?? 0,
            $model->rata_praktik ?? 0,
            $model->rata_pts ?? 0,
            $model->rata_pas ?? 0,
            // Predikat
            $model->predikat_a ?? 0,
            $model->predikat_b ?? 0,
            $model->predikat_c ?? 0,
            $model->predikat_d ?? 0,
            $model->belum_dinilai ?? 0,
            // Kehadiran
            $model->hadir ?? 0,
            $model->sakit ?? 0,
            $model->izin ?? 0,
            $model->alpa ?? 0,
            // Dimensi (8)
            $dimensi[0] ?? 0, $dimensi[1] ?? 0, $dimensi[2] ?? 0, $dimensi[3] ?? 0,
            $dimensi[4] ?? 0, $dimensi[5] ?? 0, $dimensi[6] ?? 0, $dimensi[7] ?? 0,
            // KAIH per item (7)
            $kaihPer[0] ?? 0, $kaihPer[1] ?? 0, $kaihPer[2] ?? 0,
            $kaihPer[3] ?? 0, $kaihPer[4] ?? 0, $kaihPer[5] ?? 0, $kaihPer[6] ?? 0,
            // KAIH summary
            $kaih['total'] ?? 0,
            $kaih['predikat'] ?? '',
            $kaih['hari'] ?? 0,
            $kaih['keluarga'] ?? 0,
            // Kegiatan
            $kegiatan['materi'] ?? 0,
            $kegiatan['kuis'] ?? 0,
            $kegiatan['kuisAktif'] ?? 0,
            $kegiatan['kuisLuar'] ?? 0,
            $kegiatan['hasilKuis'] ?? 0,
            $kegiatan['catatan'] ?? 0,
            $kegiatan['penilaian'] ?? 0,
            // Pembaruan
            $model->pembaruan ?? '',
        ];
    }

    /**
     * Safe JSON encode for array data
     */
    protected function jsonAman($data): string
    {
        if (empty($data)) return '';
        $json = json_encode($data);
        return strlen($json) > 45000 ? '' : $json;
    }
}
