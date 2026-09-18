<?php

namespace App\Console\Commands;

use App\Models\{
    User, Siswa, Nilai, Kehadiran, Materi, Kuis, HasilKuis,
    Catatan, Dimensi, Kebiasaan, PengaturanGuru, Semester,
    RingkasanGuru, Aktivitas, OrangTuaSiswa, SekolahSettings
};
use App\Services\GoogleSheetsService;
use Illuminate\Console\Command;

class FullSyncSheet extends Command
{
    protected $signature = 'sheets:full-sync';
    protected $description = 'Full sync semua data SIMANTAP ke Google Sheets (14 tabel sesuai GAS v4.2)';

    public function handle()
    {
        $settings = SekolahSettings::first();
        $googleSheet = $settings->pengaturan['google_sheet'] ?? null;

        if (!$googleSheet || empty($googleSheet['terhubung']) || empty($googleSheet['spreadsheet_id'])) {
            $this->error('Google Sheets belum terhubung.');
            return 1;
        }

        $spreadsheetId = $googleSheet['spreadsheet_id'];
        $service = app(GoogleSheetsService::class);

        $this->info('Memulai full sync ke Google Sheets (14 tabel)...');
        $this->newLine();

        $totalRecords = 0;

        // 1. PENGGUNA
        $this->syncTable($service, $spreadsheetId, 'pengguna', fn() => User::all());
        $totalRecords += User::count();

        // 2. SISWA
        $this->syncTable($service, $spreadsheetId, 'siswa', fn() => Siswa::with('guru')->get());
        $totalRecords += Siswa::count();

        // 3. NILAI
        $this->syncTable($service, $spreadsheetId, 'nilai', fn() => Nilai::with('siswa', 'guru')->get());
        $totalRecords += Nilai::count();

        // 4. KEHADIRAN
        $this->syncTable($service, $spreadsheetId, 'kehadiran', fn() => Kehadiran::with('siswa', 'guru')->get());
        $totalRecords += Kehadiran::count();

        // 5. MATERI
        $this->syncTable($service, $spreadsheetId, 'materi', fn() => Materi::with('guru')->get());
        $totalRecords += Materi::count();

        // 6. KUIS
        $this->syncTable($service, $spreadsheetId, 'kuis', fn() => Kuis::with('guru')->get());
        $totalRecords += Kuis::count();

        // 7. HASIL KUIS
        $this->syncTable($service, $spreadsheetId, 'hasil_kuis', fn() => HasilKuis::with('siswa', 'kuis', 'guru')->get());
        $totalRecords += HasilKuis::count();

        // 8. CATATAN
        $this->syncTable($service, $spreadsheetId, 'catatan', fn() => Catatan::with('siswa', 'guru')->get());
        $totalRecords += Catatan::count();

        // 9. DIMENSI
        $this->syncTable($service, $spreadsheetId, 'dimensi', fn() => Dimensi::with('siswa', 'guru')->get());
        $totalRecords += Dimensi::count();

        // 10. 7 KEBIASAAN
        $this->syncTable($service, $spreadsheetId, 'kebiasaan', fn() => Kebiasaan::with('siswa')->get());
        $totalRecords += Kebiasaan::count();

        // 11. PENGATURAN GURU
        $this->syncTable($service, $spreadsheetId, 'pengaturan', fn() => PengaturanGuru::with('guru')->get());
        $totalRecords += PengaturanGuru::count();

        // 12. SEKOLAH (key-value store)
        $this->syncSekolah($service, $spreadsheetId);

        // 13. RINGKASAN GURU
        $this->syncTable($service, $spreadsheetId, 'ringkasan', fn() => RingkasanGuru::with('guru')->get());
        $totalRecords += RingkasanGuru::count();

        // 14. AKTIVITAS
        $this->syncTable($service, $spreadsheetId, 'aktivitas', fn() => Aktivitas::with('guru')->get());
        $totalRecords += Aktivitas::count();

        $this->newLine();
        $this->info("Full sync selesai! Total: {$totalRecords} data ke 14 sheet.");
        return 0;
    }

    protected function syncTable(GoogleSheetsService $service, string $spreadsheetId, string $type, callable $query): void
    {
        $tabName = config("google.sheet_tabs.{$type}");
        $headers = config("google.headers.{$type}");
        $data = $query();

        $this->info("Syncing {$tabName} ({$data->count()} records)...");

        $service->getOrCreateTab($spreadsheetId, $tabName);
        $service->clearSheet($spreadsheetId, $tabName);
        $service->ensureHeaders($spreadsheetId, $tabName, $headers);

        $rows = $data->map(fn($model) => $service->buildRow($type, $model))->toArray();

        if (!empty($rows)) {
            $chunks = array_chunk($rows, 100);
            foreach ($chunks as $chunk) {
                $service->syncBatch($spreadsheetId, $tabName, $chunk);
            }
        }

        $this->info("  ✓ {$tabName}: {$data->count()} data ter-sync");
    }

    protected function syncSekolah(GoogleSheetsService $service, string $spreadsheetId): void
    {
        $tabName = config('google.sheet_tabs.sekolah');
        $headers = config('google.headers.sekolah');

        $this->info("Syncing {$tabName}...");

        $service->getOrCreateTab($spreadsheetId, $tabName);
        $service->clearSheet($spreadsheetId, $tabName);
        $service->ensureHeaders($spreadsheetId, $tabName, $headers);

        $settings = SekolahSettings::first();
        $rows = [];

        // Convert Laravel SekolahSettings to GAS SEKOLAH key-value format
        $fields = [
            'nama_sekolah' => 'Nama Sekolah',
            'npsn' => 'NPSN',
            'alamat' => 'Alamat',
            'kecamatan' => 'Kecamatan',
            'kota' => 'Kota/Kabupaten',
            'provinsi' => 'Provinsi',
            'kode_pos' => 'Kode Pos',
            'telepon' => 'Telepon',
            'email' => 'Email',
            'website' => 'Website',
            'kepala_sekolah' => 'Kepala Sekolah',
            'status' => 'Status',
            'jenis_sekolah' => 'Jenis Sekolah',
            'jenjang' => 'Jenjang',
            'akreditasi' => 'Akreditasi',
        ];

        foreach ($fields as $dbField => $label) {
            $rows[] = ['Pengaturan', $label, $settings->$dbField ?? ''];
        }

        // Add pengaturan array fields
        $pengaturan = $settings->pengaturan ?? [];
        foreach ($pengaturan as $key => $value) {
            if (in_array($key, ['google_sheet'])) continue; // Skip internal
            $rows[] = ['Pengaturan', $key, is_array($value) ? json_encode($value) : $value];
        }

        if (!empty($rows)) {
            $service->syncBatch($spreadsheetId, $tabName, $rows);
        }

        $this->info("  ✓ {$tabName}: " . count($rows) . " data ter-sync");
    }
}
