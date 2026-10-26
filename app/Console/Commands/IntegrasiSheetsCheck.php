<?php

namespace App\Console\Commands;

use App\Models\SekolahSettings;
use App\Services\GoogleSheetsService;
use Illuminate\Console\Command;

/**
 * Pemeriksaan integrasi Google Sheets untuk staging/produksi.
 *
 * Tanpa `--live` perintah memeriksa sambungan di pengaturan sekolah dan
 * validitas kredensial service account. Dengan `--live` perintah membaca
 * metadata spreadsheet (read-only) dan memastikan semua tab wajib ada.
 * Perintah ini tidak pernah menulis, menghapus, atau membuat tab.
 */
class IntegrasiSheetsCheck extends Command
{
    protected $signature = 'integrasi:sheets:cek
                            {--live : Baca metadata spreadsheet read-only}
                            {--json : Keluarkan laporan JSON}';

    protected $description = 'Periksa sambungan Google Sheets dan kredensial service account (read-only)';

    public function handle(): int
    {
        $masalah = [];
        $peringatan = [];

        $settings = SekolahSettings::query()->first();
        $googleSheet = $settings?->pengaturan['google_sheet'] ?? null;

        $terhubung = is_array($googleSheet) && ! empty($googleSheet['terhubung']);
        $spreadsheetId = is_array($googleSheet) ? ($googleSheet['spreadsheet_id'] ?? null) : null;
        $spreadsheetUrl = is_array($googleSheet) ? ($googleSheet['spreadsheet_url'] ?? null) : null;

        $data = [
            'terhubung' => $terhubung,
            'spreadsheet_id' => $spreadsheetId,
            'spreadsheet_url' => $spreadsheetUrl,
            'credentials' => null,
            'service_account_email' => null,
            'tab_ditemukan' => [],
            'tab_hilang' => [],
            'live' => false,
        ];

        if (! $settings) {
            $masalah[] = 'Pengaturan sekolah belum ada. Buka Admin › Sekolah terlebih dahulu.';
        } elseif (! $terhubung) {
            $masalah[] = 'Google Sheets belum ditandai terhubung di pengaturan sekolah.';
        }

        if (blank($spreadsheetId)) {
            $masalah[] = 'Spreadsheet ID belum diisi.';
        }

        if (blank($spreadsheetUrl)) {
            $peringatan[] = 'Spreadsheet URL belum diisi (tidak wajib untuk API, tetapi membantu operator).';
        } elseif ($spreadsheetId && ! str_contains((string) $spreadsheetUrl, (string) $spreadsheetId)) {
            $peringatan[] = 'URL spreadsheet tidak memuat Spreadsheet ID yang tersimpan.';
        }

        $kredensial = $this->periksaKredensial();
        $data['credentials'] = $kredensial['sumber'];
        $data['service_account_email'] = $kredensial['email'];

        if (! $kredensial['ok']) {
            $masalah[] = $kredensial['pesan'];
        }

        if ($this->option('live') && $masalah === []) {
            $data['live'] = true;

            try {
                $service = app(GoogleSheetsService::class);
                $spreadsheet = $service->getSheetsService()->spreadsheets->get((string) $spreadsheetId);
                $judul = [];

                foreach ($spreadsheet->getSheets() as $sheet) {
                    $judul[] = (string) $sheet->getProperties()->getTitle();
                }

                $wajib = array_values(config('google.sheet_tabs', []));
                $hilang = array_values(array_diff($wajib, $judul));

                $data['tab_ditemukan'] = $judul;
                $data['tab_hilang'] = $hilang;

                if ($hilang !== []) {
                    $masalah[] = 'Tab wajib belum ada di spreadsheet: ' . implode(', ', $hilang) . '. Jalankan `php artisan sheets:full-sync` untuk membuatnya.';
                }

                if ($judul === []) {
                    $peringatan[] = 'Spreadsheet tidak memiliki tab apa pun.';
                }
            } catch (\Throwable $e) {
                $masalah[] = 'Koneksi live ke Google Sheets gagal: ' . $e->getMessage();
            }
        }

        $data['ok'] = $masalah === [];
        $data['masalah'] = $masalah;
        $data['peringatan'] = $peringatan;

        if ($this->option('json')) {
            $this->line((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $data['ok'] ? self::SUCCESS : self::FAILURE;
        }

        $this->table(['Pemeriksaan', 'Nilai'], [
            ['Terhubung', $terhubung ? 'ya' : 'tidak'],
            ['Spreadsheet ID', $spreadsheetId ?: '—'],
            ['URL', $spreadsheetUrl ?: '—'],
            ['Kredensial', $kredensial['sumber'] ?: '—'],
            ['Service account', $kredensial['email'] ?: '—'],
            ['Live check', $data['live'] ? 'ya' : 'tidak (pakai --live)'],
            ['Tab ditemukan', $data['tab_ditemukan'] === [] ? '—' : (string) count($data['tab_ditemukan'])],
            ['Tab hilang', $data['tab_hilang'] === [] ? '—' : implode(', ', $data['tab_hilang'])],
        ]);

        foreach ($peringatan as $pesan) {
            $this->warn('⚠ ' . $pesan);
        }

        foreach ($masalah as $pesan) {
            $this->error('✗ ' . $pesan);
        }

        if ($data['ok']) {
            $this->info('✅ Konfigurasi Google Sheets siap.' . ($data['live'] ? ' Metadata spreadsheet berhasil dibaca.' : ' Jalankan --live untuk menguji akses spreadsheet.'));

            return self::SUCCESS;
        }

        return self::FAILURE;
    }

    /**
     * @return array{ok: bool, sumber: ?string, email: ?string, pesan: string}
     */
    private function periksaKredensial(): array
    {
        $inline = env('GOOGLE_CREDENTIALS_JSON');

        if (is_string($inline) && trim($inline) !== '') {
            $decoded = json_decode($inline, true);

            if (! is_array($decoded)) {
                return ['ok' => false, 'sumber' => 'GOOGLE_CREDENTIALS_JSON', 'email' => null, 'pesan' => 'GOOGLE_CREDENTIALS_JSON tidak valid.'];
            }

            return $this->validasiIsiKredensial($decoded, 'GOOGLE_CREDENTIALS_JSON');
        }

        $path = config('google.service_account_path');

        if (blank($path)) {
            return ['ok' => false, 'sumber' => null, 'email' => null, 'pesan' => 'Kredensial Google belum diisi (GOOGLE_CREDENTIALS_JSON atau GOOGLE_SERVICE_ACCOUNT_PATH).'];
        }

        $path = (string) $path;

        if (! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) {
            $path = base_path('storage/app/' . $path);
        }

        if (! is_file($path)) {
            return ['ok' => false, 'sumber' => $path, 'email' => null, 'pesan' => 'Berkas service account tidak ditemukan: ' . $path];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return ['ok' => false, 'sumber' => $path, 'email' => null, 'pesan' => 'Berkas service account bukan JSON valid: ' . $path];
        }

        return $this->validasiIsiKredensial($decoded, $path);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array{ok: bool, sumber: string, email: ?string, pesan: string}
     */
    private function validasiIsiKredensial(array $decoded, string $sumber): array
    {
        $email = is_string($decoded['client_email'] ?? null) ? $decoded['client_email'] : null;
        $privateKey = is_string($decoded['private_key'] ?? null) ? $decoded['private_key'] : null;

        if (! $email || ! $privateKey) {
            return ['ok' => false, 'sumber' => $sumber, 'email' => $email, 'pesan' => 'Kredensial tidak memuat client_email/private_key service account.'];
        }

        if (! str_contains($privateKey, 'BEGIN PRIVATE KEY')) {
            return ['ok' => false, 'sumber' => $sumber, 'email' => $email, 'pesan' => 'private_key service account tidak valid.'];
        }

        return ['ok' => true, 'sumber' => $sumber, 'email' => $email, 'pesan' => ''];
    }
}
