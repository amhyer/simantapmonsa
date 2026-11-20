<?php

namespace App\Console\Commands;

use App\Services\MesinPresensi\AttendanceDatabaseReader;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Bridge presensi untuk SIMANTAP yang di-hosting.
 *
 * Dijalankan di PC sekolah (satu jaringan dengan mesin fingerprint):
 *   1. baca log scan langsung dari mesin via TCP 4370;
 *   2. kirim ke SIMANTAP melalui POST /api/presensi/mesin/sinkron.
 *
 * Tidak menyentuh database lokal dan tidak mengubah data apa pun di PC
 * sekolah. Rentang default dimulai sejak sinkron terakhir dikurangi satu
 * hari (maksimal 30 hari); state hanya maju bila seluruh kiriman berhasil,
 * sehingga kegagalan jaringan akan diulang pada jadwal berikutnya.
 */
class PresensiMesinBridgeCommand extends Command
{
    private const MAKS_HARI = 30;

    private const HARI_PER_KIRIM = 7;

    private const MAKS_SCAN_PER_KIRIM = 4000;

    private const JALUR_SINKRON = '/api/presensi/mesin/sinkron';

    private const TIMEOUT_DETIK = 180;

    protected $signature = 'presensi:mesin-bridge
        {--url= : URL SIMANTAP, mis. https://simantap.sekolah.id}
        {--key= : API key dengan ability presensi:import}
        {--dari= : Awal rentang manual (format Y-m-d)}
        {--sampai= : Akhir rentang manual (format Y-m-d)}
        {--dry-run : Baca mesin dan tampilkan rencana kirim tanpa menghubungi server}';

    protected $description = 'Bridge TCP: baca mesin fingerprint di LAN sekolah lalu kirim ke SIMANTAP hosting';

    public function handle(AttendanceDatabaseReader $reader): int
    {
        $sampaiOpsi = trim((string) $this->option('sampai'));
        $dariOpsi = trim((string) $this->option('dari'));

        $sampai = $sampaiOpsi !== ''
            ? $this->parseTanggal($sampaiOpsi)
            : Carbon::today(config('app.timezone'));
        if ($sampai === null) {
            $this->error('Format --sampai harus Y-m-d, mis. 2026-11-17.');

            return self::FAILURE;
        }

        $dari = $dariOpsi !== ''
            ? $this->parseTanggal($dariOpsi)
            : $this->mulaiDefault($sampai);
        if ($dari === null) {
            $this->error('Format --dari harus Y-m-d, mis. 2026-11-01.');

            return self::FAILURE;
        }

        if ($dari->gt($sampai)) {
            $this->error('Rentang --dari tidak boleh setelah --sampai.');

            return self::FAILURE;
        }

        if ((int) $dari->diffInDays($sampai) + 1 > self::MAKS_HARI) {
            $this->error('Rentang maksimal '.self::MAKS_HARI.' hari agar proses tetap cepat.');

            return self::FAILURE;
        }

        // Rentang yang ditulis manual tidak menggeser state otomatis.
        $manual = $dariOpsi !== '' || $sampaiOpsi !== '';
        $dryRun = (bool) $this->option('dry-run');

        $url = $this->urlSinkron();
        $key = trim((string) ($this->option('key') ?: config('presensi.bridge_key')));

        if (! $dryRun && ($url === '' || $key === '')) {
            $this->error('URL/API key belum diatur. Isi PRESENSI_BRIDGE_URL dan PRESENSI_BRIDGE_KEY di .env, atau pakai --url dan --key.');

            return self::FAILURE;
        }

        $this->info('Membaca mesin '.$dari->toDateString().' s/d '.$sampai->toDateString().'...');

        try {
            $mentah = $reader->baca($dari->copy(), $sampai->copy()->addDay());
        } catch (\Throwable $e) {
            report($e);
            $pesan = 'Gagal membaca mesin: '.$e->getMessage();
            $this->catatGagal($manual, $pesan);
            $this->error($pesan);

            return self::FAILURE;
        }

        // Hari presensi dimulai 01:00, jadi ambil [dari 01:00, sampai+1 01:00).
        $batasAwal = $dari->copy()->addHour();
        $batasAkhir = $sampai->copy()->addDay()->addHour();
        $scans = array_values(array_filter(
            $mentah,
            fn (array $scan): bool => $scan['waktu']->gte($batasAwal) && $scan['waktu']->lt($batasAkhir),
        ));

        $paket = $this->paketkan($scans);

        if ($dryRun) {
            $this->info('DRY RUN: '.count($scans).' scan dalam '.count($paket).' permintaan. Tidak ada data dikirim.');

            foreach ($paket as $i => $bagian) {
                $this->line(sprintf(
                    '  #%d %s s/d %s: %d scan',
                    $i + 1,
                    $bagian['dari'],
                    $bagian['sampai'],
                    count($bagian['scans']),
                ));
            }

            return self::SUCCESS;
        }

        if ($paket === []) {
            $this->info('Tidak ada scan pada '.$dari->toDateString().' s/d '.$sampai->toDateString().'.');
            $this->catatSukses($manual, $dari, $sampai, 0);

            return self::SUCCESS;
        }

        $total = [
            'scan' => 0,
            'dibuat' => 0,
            'diperbarui' => 0,
            'tetap' => 0,
            'dilindungi' => 0,
            'nik_tidak_dikenal' => 0,
        ];

        foreach ($paket as $i => $bagian) {
            $payload = array_map(fn (array $scan): array => [
                'nik' => (string) $scan['nik'],
                'waktu' => $scan['waktu']->toDateTimeString(),
                'jenis' => (string) $scan['jenis'],
            ], $bagian['scans']);

            $this->line(sprintf(
                'Mengirim %d/%d: %s s/d %s (%d scan)...',
                $i + 1,
                count($paket),
                $bagian['dari'],
                $bagian['sampai'],
                count($payload),
            ));

            try {
                $response = Http::withHeaders([
                    'X-API-Key' => $key,
                    'Accept' => 'application/json',
                ])->timeout(self::TIMEOUT_DETIK)->post($url, ['scans' => $payload]);
            } catch (ConnectionException $e) {
                $pesan = 'Gagal menghubungi '.$url.': '.$e->getMessage();
                $this->catatGagal($manual, $pesan);
                $this->error($pesan);

                return self::FAILURE;
            }

            if (! $response->successful() || $response->json('success') !== true) {
                $pesan = $this->pesanKesalahan($response);
                $this->catatGagal($manual, $pesan);
                $this->error($pesan);

                return self::FAILURE;
            }

            $hasil = (array) $response->json();
            $total['scan'] += count($payload);
            foreach (['dibuat', 'diperbarui', 'tetap', 'dilindungi', 'nik_tidak_dikenal'] as $kunci) {
                $total[$kunci] += (int) ($hasil[$kunci] ?? 0);
            }

            $this->line(sprintf(
                '  OK: %d dibuat, %d diperbarui, %d tetap, %d dilindungi, %d NIK tidak dikenal.',
                $total['dibuat'],
                $total['diperbarui'],
                $total['tetap'],
                $total['dilindungi'],
                $total['nik_tidak_dikenal'],
            ));
        }

        $this->info(sprintf(
            'Selesai: %d scan terkirim (%d dibuat, %d diperbarui, %d tetap, %d dilindungi, %d NIK tidak dikenal).',
            $total['scan'],
            $total['dibuat'],
            $total['diperbarui'],
            $total['tetap'],
            $total['dilindungi'],
            $total['nik_tidak_dikenal'],
        ));

        $this->catatSukses($manual, $dari, $sampai, $total['scan']);

        return self::SUCCESS;
    }

    /**
     * Rentang awal default: sejak sukses terakhir dikurangi satu hari agar
     * scan yang terlambat masuk tetap tertangkap, dibatasi 30 hari. Tanpa
     * state (jalan pertama) dipakai 7 hari terakhir.
     */
    private function mulaiDefault(Carbon $sampai): Carbon
    {
        $batasMundur = $sampai->copy()->subDays(self::MAKS_HARI - 1);
        $state = $this->bacaState();
        $terakhir = is_array($state) ? ($state['terakhir_sukses'] ?? null) : null;

        if (is_string($terakhir) && trim($terakhir) !== '') {
            try {
                $mulai = Carbon::parse($terakhir, config('app.timezone'))->startOfDay()->subDay();

                return $mulai->lt($batasMundur) ? $batasMundur : $mulai;
            } catch (\Throwable) {
                // State rusak: pakai default di bawah.
            }
        }

        return $sampai->copy()->subDays(self::HARI_PER_KIRIM - 1);
    }

    /**
     * Kelompokkan scan per hari presensi (batas 01:00), lalu bungkus menjadi
     * paket maksimal 7 hari dan 4.000 scan. Satu hari presensi tidak pernah
     * dipecah agar klasifikasi masuk/pulang di server tetap utuh.
     *
     * @param  list<array{nik: string, waktu: Carbon, jenis: string}>  $scans
     * @return list<array{dari: string, sampai: string, scans: list<array{nik: string, waktu: Carbon, jenis: string}>}>
     */
    private function paketkan(array $scans): array
    {
        $perHari = [];

        foreach ($scans as $scan) {
            $tanggal = $scan['waktu']->copy()->subHour()->toDateString();
            $perHari[$tanggal][] = $scan;
        }

        ksort($perHari);

        $paket = [];
        $bagian = ['dari' => null, 'sampai' => null, 'scans' => []];

        $tutup = function () use (&$paket, &$bagian): void {
            if ($bagian['scans'] === []) {
                return;
            }

            $paket[] = $bagian;
            $bagian = ['dari' => null, 'sampai' => null, 'scans' => []];
        };

        foreach ($perHari as $tanggal => $daftar) {
            $jumlahHari = $bagian['dari'] === null
                ? 1
                : (int) Carbon::parse($bagian['dari'])->diffInDays(Carbon::parse($tanggal)) + 1;

            if ($bagian['scans'] !== []
                && ($jumlahHari > self::HARI_PER_KIRIM
                    || count($bagian['scans']) + count($daftar) > self::MAKS_SCAN_PER_KIRIM)) {
                $tutup();
            }

            foreach (array_chunk($daftar, self::MAKS_SCAN_PER_KIRIM) as $potongan) {
                if ($bagian['scans'] !== []
                    && count($bagian['scans']) + count($potongan) > self::MAKS_SCAN_PER_KIRIM) {
                    $tutup();
                }

                $bagian['dari'] ??= $tanggal;
                $bagian['sampai'] = $tanggal;
                $bagian['scans'] = array_merge($bagian['scans'], $potongan);
            }
        }

        $tutup();

        return $paket;
    }

    private function parseTanggal(string $nilai): ?Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $nilai, config('app.timezone'))->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function urlSinkron(): string
    {
        $url = trim((string) ($this->option('url') ?: config('presensi.bridge_url') ?: config('app.url')));
        if ($url === '') {
            return '';
        }

        $url = rtrim($url, '/');

        return str_contains($url, self::JALUR_SINKRON) ? $url : $url.self::JALUR_SINKRON;
    }

    private function pesanKesalahan(Response $response): string
    {
        $status = $response->status();

        if ($status === 401) {
            return 'API key ditolak (401). Periksa PRESENSI_BRIDGE_KEY atau --key.';
        }
        if ($status === 403) {
            return 'API key tidak memiliki ability presensi:import (403). Buat ulang kunci di menu Kunci API Bridge.';
        }

        $pesan = trim((string) ($response->json('message') ?? ''));

        if ($status === 422) {
            $galat = $response->json('errors');
            $detail = is_array($galat) ? ' '.json_encode($galat, JSON_UNESCAPED_UNICODE) : '';

            return 'Payload ditolak server (422).'.($pesan !== '' ? ' '.$pesan : '').$detail;
        }
        if ($pesan !== '') {
            return 'Server membalas '.$status.': '.$pesan;
        }

        $isi = trim((string) $response->body());

        return 'Server membalas '.$status.($isi !== '' ? ': '.mb_substr($isi, 0, 300) : '.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function bacaState(): ?array
    {
        $path = (string) config('presensi.bridge_state_path');
        if ($path === '' || ! is_file($path)) {
            return null;
        }

        $isi = json_decode((string) file_get_contents($path), true);

        return is_array($isi) ? $isi : null;
    }

    private function catatSukses(bool $manual, Carbon $dari, Carbon $sampai, int $jumlahScan): void
    {
        if ($manual) {
            return;
        }

        $this->simpanState([
            'terakhir_sukses' => now()->toDateTimeString(),
            'terakhir_jalan' => now()->toDateTimeString(),
            'pesan_terakhir' => 'ok',
            'rentang_terakhir' => ['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()],
            'jumlah_scan_terakhir' => $jumlahScan,
        ]);
    }

    private function catatGagal(bool $manual, string $pesan): void
    {
        if ($manual) {
            return;
        }

        $this->simpanState([
            'terakhir_jalan' => now()->toDateTimeString(),
            'pesan_terakhir' => 'gagal: '.$pesan,
        ]);
    }

    /**
     * Simpan state tanpa menghapus cap sukses terakhir saat terjadi kegagalan.
     *
     * @param  array<string, mixed>  $data
     */
    private function simpanState(array $data): void
    {
        $path = (string) config('presensi.bridge_state_path');
        if ($path === '') {
            return;
        }

        $folder = dirname($path);
        if ($folder !== '' && ! is_dir($folder)) {
            @mkdir($folder, 0775, true);
        }

        $baru = array_merge($this->bacaState() ?? [], $data);
        $json = json_encode($baru, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            return;
        }

        @file_put_contents($path, $json."\n", LOCK_EX);
    }
}
