<?php

namespace App\Services\PembelajaranMendalam;

use App\Models\PembelajaranMendalam\AiUsageLog;
use App\Models\SekolahSettings;
use App\Support\SchoolLevel;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiPembelajaranService
{
    public function resolveConfig(?int $tenantId = null): array
    {
        $sekolah = SekolahSettings::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->first();

        $pengaturan = is_array($sekolah?->pengaturan) ? $sekolah->pengaturan : [];

        $ownApiKey = '';
        if (! empty($pengaturan['gemini_api_key_encrypted'])) {
            try {
                $ownApiKey = trim((string) Crypt::decryptString((string) $pengaturan['gemini_api_key_encrypted']));
            } catch (DecryptException) {
                $ownApiKey = '';
            }
        }
        if ($ownApiKey === '') {
            $ownApiKey = trim((string) ($pengaturan['gemini_api_key'] ?? ''));
        }

        $hasOwnApiKey = $ownApiKey !== '';
        $apiKey = $hasOwnApiKey ? $ownApiKey : trim((string) config('services.gemini.api_key', ''));

        $model = trim((string) ($pengaturan['gemini_model'] ?? ''));
        if ($model === '') {
            $model = (string) config('services.gemini.model', 'gemini-2.5-flash');
        }

        $requestedQuota = (int) ($pengaturan['gemini_daily_quota'] ?? config('services.gemini.daily_quota', 60));
        if ($requestedQuota <= 0) {
            $requestedQuota = 60;
        }

        $quotaSummary = app(\App\Services\Tenant\TenantQuotaService::class)->aiQuotaSummary(
            $tenantId,
            $requestedQuota,
            $hasOwnApiKey
        );

        return [
            'api_key' => $apiKey,
            'has_own_api_key' => $hasOwnApiKey,
            'model' => $model,
            'daily_quota' => $quotaSummary['limit'],
            'base_url' => rtrim((string) config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/'),
        ];
    }

    /**
     * @return array{configured: bool, model: string, used: int, limit: int, remaining: int}
     */
    public function quotaStatus(?int $tenantId = null): array
    {
        $cfg = $this->resolveConfig($tenantId);
        $today = now()->toDateString();

        $used = AiUsageLog::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereDate('tanggal', $today)
            ->where('status', 'ok')
            ->count();

        return [
            'configured' => $cfg['api_key'] !== '',
            'model' => $cfg['model'],
            'used' => $used,
            'limit' => $cfg['daily_quota'],
            'remaining' => max(0, $cfg['daily_quota'] - $used),
        ];
    }

    /**
     * @return array<int, array{elemen: string, deskripsi: string}>
     */
    public function susunCp(string $mapel, string $fase, ?string $catatan, ?int $tenantId, int $userId): array
    {
        $jenjang = SchoolLevel::current($tenantId);
        $prompt = "Anda adalah pakar kurikulum {$jenjang} Indonesia (Kurikulum Merdeka & Pembelajaran Mendalam, acuan BSKAP No. 046/H/KR/2025).\n"
            ."Susun Capaian Pembelajaran (CP) untuk mata pelajaran \"{$mapel}\" Fase {$fase} jenjang {$jenjang}.\n"
            .($catatan ? "Catatan khusus guru: {$catatan}\n" : '')
            ."Kembalikan HANYA JSON array valid tanpa teks tambahan dengan format:\n"
            .'[{"elemen": "Nama Elemen", "deskripsi": "Rumusan CP lengkap..."}]';

        $decoded = $this->requestJson($prompt, 'susun_cp', $tenantId, $userId);

        $items = [];
        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }
            $deskripsi = trim((string) ($row['deskripsi'] ?? ''));
            if ($deskripsi === '') {
                continue;
            }
            $items[] = [
                'elemen' => trim((string) ($row['elemen'] ?? 'Umum')),
                'deskripsi' => $deskripsi,
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array{elemen: string, kode_tp: string, deskripsi: string}>
     */
    public function susunTp(
        string $mapel,
        string $fase,
        ?string $elemen,
        int $jumlah,
        ?string $catatan,
        ?string $acuanCp,
        ?int $tenantId,
        int $userId
    ): array {
        $jumlah = max(1, min(15, $jumlah));
        $jenjang = SchoolLevel::current($tenantId);
        $prompt = "Anda adalah pakar Kurikulum Merdeka & Pembelajaran Mendalam jenjang {$jenjang} Indonesia.\n"
            ."Susun {$jumlah} Tujuan Pembelajaran (TP) yang operasional, terukur, dan mencerminkan Pembelajaran Mendalam (Memahami, Mengaplikasi, Merefleksi) "
            ."untuk mata pelajaran \"{$mapel}\" Fase {$fase}.\n"
            .($elemen ? "Fokus elemen: {$elemen}\n" : '')
            .($acuanCp ? "Acuan Capaian Pembelajaran:\n{$acuanCp}\n" : '')
            .($catatan ? "Catatan guru: {$catatan}\n" : '')
            ."Kembalikan HANYA JSON array valid tanpa teks tambahan dengan format:\n"
            .'[{"elemen": "Nama Elemen", "kode_tp": "TP.1", "deskripsi": "Murid mampu ..."}]';

        $decoded = $this->requestJson($prompt, 'susun_tp', $tenantId, $userId);

        $items = [];
        foreach ($decoded as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $deskripsi = trim((string) ($row['deskripsi'] ?? ''));
            if ($deskripsi === '') {
                continue;
            }
            $items[] = [
                'elemen' => trim((string) ($row['elemen'] ?? ($elemen ?: 'Umum'))),
                'kode_tp' => trim((string) ($row['kode_tp'] ?? ('TP.'.($i + 1)))),
                'deskripsi' => $deskripsi,
            ];
        }

        return $items;
    }

    /**
     * @param  array<int, array{id: int, kode_tp: string, deskripsi: string}>  $daftarTp
     * @return array<int, array{id: int, urutan: int, kelas_target: int, semester_target: int, materi_pokok: string, alokasi_jp: int}>
     */
    public function susunAtp(string $mapel, string $fase, array $daftarTp, ?int $tenantId, int $userId): array
    {
        $jenjang = SchoolLevel::current($tenantId);
        $kelasValid = SchoolLevel::kelasUntukFase($fase);
        $defaultKelas = $kelasValid[0] ?? 1;
        $kelasRange = match (strtoupper($fase)) {
            'A' => '1 atau 2',
            'B' => '3 atau 4',
            'C' => '5 atau 6',
            'D' => '7, 8, atau 9',
            'E' => '10',
            'F' => '11 atau 12',
            default => implode(', ', $kelasValid),
        };

        $tpJson = json_encode($daftarTp, JSON_UNESCAPED_UNICODE);
        $prompt = "Anda adalah pakar Kurikulum Merdeka {$jenjang}. Urutkan daftar Tujuan Pembelajaran (TP) berikut menjadi Alur Tujuan Pembelajaran (ATP) yang logis "
            ."untuk mapel \"{$mapel}\" Fase {$fase} (kelas_target hanya boleh {$kelasRange}, semester_target hanya 1 atau 2).\n"
            ."Daftar TP:\n{$tpJson}\n"
            ."Kembalikan HANYA JSON array valid dengan format:\n"
            .'[{"id": 1, "urutan": 1, "kelas_target": '.$defaultKelas.', "semester_target": 1, "materi_pokok": "Topik singkat", "alokasi_jp": 6}]';

        $decoded = $this->requestJson($prompt, 'susun_atp', $tenantId, $userId);

        $items = [];
        foreach ($decoded as $idx => $row) {
            if (! is_array($row) || ! isset($row['id'])) {
                continue;
            }
            $items[] = [
                'id' => (int) $row['id'],
                'urutan' => max(1, (int) ($row['urutan'] ?? ($idx + 1))),
                'kelas_target' => max(1, min(12, (int) ($row['kelas_target'] ?? $defaultKelas))),
                'semester_target' => in_array((int) ($row['semester_target'] ?? 1), [1, 2], true) ? (int) $row['semester_target'] : 1,
                'materi_pokok' => trim((string) ($row['materi_pokok'] ?? '')),
                'alokasi_jp' => max(1, min(60, (int) ($row['alokasi_jp'] ?? 4))),
            ];
        }

        return $items;
    }

    /**
     * @return array<int, string>
     */
    public function susunDaftarIsiBuku(string $mapel, int $tingkat, string $judulBuku, ?int $tenantId, int $userId): array
    {
        $prompt = "Susun daftar isi bab buku paket Kurikulum Merdeka SIBI Kemendikdasmen untuk SD Kelas {$tingkat} "
            ."mata pelajaran \"{$mapel}\" berjudul \"{$judulBuku}\".\n"
            ."Kembalikan HANYA JSON array of strings (6-10 bab), contoh:\n"
            .'["Bab 1: Bilangan Cacah sampai 10", "Bab 2: Penjumlahan dan Pengurangan"]';

        $decoded = $this->requestJson($prompt, 'susun_buku', $tenantId, $userId);

        return array_values(array_filter(
            array_map(fn ($v) => is_string($v) ? trim($v) : '', $decoded),
            fn (string $s) => $s !== ''
        ));
    }

    /**
     * @param  array<int, array{id: int, kode_tp: string, deskripsi: string}>  $daftarTp
     * @return array<int, array{semester: int, tp_id: int|null, materi: string, alokasi_jp: int, keterangan: string}>
     */
    public function susunProta(
        string $mapel,
        int $tingkat,
        int $jpSem1,
        int $jpSem2,
        array $daftarTp,
        ?string $daftarBabBuku,
        ?string $catatan,
        ?int $tenantId,
        int $userId
    ): array {
        $tpJson = json_encode($daftarTp, JSON_UNESCAPED_UNICODE);
        $prompt = "Anda adalah pakar Kurikulum Merdeka SD Indonesia. Susun Program Tahunan (Prota) untuk SD Kelas {$tingkat} mata pelajaran \"{$mapel}\".\n"
            ."Total JP tersedia Semester 1 ≈ {$jpSem1} JP, Semester 2 ≈ {$jpSem2} JP.\n"
            .($daftarTp ? "Daftar TP tersedia:\n{$tpJson}\n" : '')
            .($daftarBabBuku ? "Daftar Bab Buku Paket:\n{$daftarBabBuku}\n" : '')
            .($catatan ? "Catatan guru: {$catatan}\n" : '')
            ."Kembalikan HANYA JSON array valid tanpa teks tambahan dengan format:\n"
            .'[{"semester": 1, "tp_id": null, "materi": "Nama Materi / Bab", "alokasi_jp": 12, "keterangan": "Keterangan singkat"}]';

        $decoded = $this->requestJson($prompt, 'susun_prota', $tenantId, $userId);

        $items = [];
        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }
            $materi = trim((string) ($row['materi'] ?? ''));
            if ($materi === '') {
                continue;
            }
            $items[] = [
                'semester' => in_array((int) ($row['semester'] ?? 1), [1, 2], true) ? (int) $row['semester'] : 1,
                'tp_id' => ! empty($row['tp_id']) ? (int) $row['tp_id'] : null,
                'materi' => $materi,
                'alokasi_jp' => max(1, min(100, (int) ($row['alokasi_jp'] ?? 6))),
                'keterangan' => trim((string) ($row['keterangan'] ?? '')),
            ];
        }

        return $items;
    }

    /**
     * Susun Modul Ajar / Rencana Pembelajaran Mendalam (RPM) lengkap (dengan opsi LKPD & rubrik).
     *
     * @return array<string, mixed>
     */
    public function susunModulAjarRpm(
        string $mapel,
        int $tingkat,
        string $materi,
        ?string $tujuanPembelajaran,
        ?string $modelPembelajaran,
        bool $sertakanLkpd,
        ?string $catatan,
        ?int $tenantId,
        int $userId
    ): array {
        $prompt = "Anda adalah guru SD profesional pakar Pembelajaran Mendalam (Deep Learning) Kurikulum Merdeka Indonesia.\n"
            ."Susun Rencana Pembelajaran Mendalam (RPM / Modul Ajar) untuk SD Kelas {$tingkat}, Mata Pelajaran \"{$mapel}\", Topik/Materi: \"{$materi}\".\n"
            .($tujuanPembelajaran ? "Tujuan Pembelajaran: {$tujuanPembelajaran}\n" : '')
            .($modelPembelajaran ? "Model Pembelajaran utama: {$modelPembelajaran}\n" : '')
            .($catatan ? "Catatan khusus guru: {$catatan}\n" : '')
            .($sertakanLkpd ? "Sertakan LKPD (ringkas tanpa gambar, siap cetak) dan Rubrik Penilaian.\n" : '')
            ."Kembalikan HANYA JSON object valid tanpa teks tambahan dengan struktur:\n"
            .'{"tujuan_pembelajaran":"...","dimensi":["Penalaran Kritis","Kolaborasi"],"kesiapan_murid":"...","karakteristik_materi":"...","lintas_disiplin":"...","model_pembelajaran":"...","pengalaman":{"memahami":"...","mengaplikasi":"...","merefleksi":"..."},"pendahuluan":"...","kegiatan_inti":"...","penutup":"...","asesmen":"Asesmen Awal: ...\nAsesmen Formatif: ...\nAsesmen Sumatif: ...","lkpd":"...","rubrik":"...","media_sumber":"...","refleksi_guru":"..."}';

        return $this->requestJson($prompt, 'susun_rpm', $tenantId, $userId);
    }

    /**
     * Susun Jurnal Mengajar Harian berbasis Pembelajaran Mendalam.
     *
     * @return array<string, mixed>
     */
    public function susunJurnalMengajar(
        string $mapel,
        string $kelas,
        string $topik,
        ?string $kejadianKelas,
        ?int $tenantId,
        int $userId
    ): array {
        $jenjang = SchoolLevel::current($tenantId);
        $prompt = "Anda adalah guru {$jenjang} Indonesia. Susun Jurnal Mengajar Harian berbasis Pembelajaran Mendalam untuk Kelas {$kelas}, Mapel \"{$mapel}\", Materi \"{$topik}\".\n"
            .($kejadianKelas ? "Yang terjadi di kelas hari ini: {$kejadianKelas}\n" : '')
            ."Kembalikan HANYA JSON object valid dengan struktur:\n"
            .'{"praktik_pedagogis":"Problem Based Learning (PBL)","prinsip_pm":["Berkesadaran (Mindful)","Bermakna (Meaningful)","Menggembirakan (Joyful)"],"pengalaman_belajar":["Memahami","Mengaplikasi","Merefleksi"],"kegiatan":"Uraian singkat kegiatan pembelajaran...","asesmen":"Bentuk asesmen formatif...","catatan":"Refleksi guru...","tindak_lanjut":"Rencana tindak lanjut pertemuan berikutnya..."}';

        return $this->requestJson($prompt, 'susun_jurnal', $tenantId, $userId);
    }

    /**
     * Rancang kegiatan Kokurikuler berbasis 8 Dimensi Profil Lulusan.
     *
     * @return array<string, mixed>
     */
    public function susunKokurikuler(
        int $tingkat,
        string $temaAtauIde,
        string $bentukKegiatan,
        ?int $tenantId,
        int $userId
    ): array {
        $jenjang = SchoolLevel::current($tenantId);
        $prompt = "Anda adalah koordinator Kokurikuler {$jenjang} Kurikulum Merdeka Indonesia.\n"
            ."Rancang kegiatan Kokurikuler untuk {$jenjang} Kelas {$tingkat} dengan bentuk kegiatan \"{$bentukKegiatan}\" dan tema/ide: \"{$temaAtauIde}\".\n"
            ."Kembalikan HANYA JSON object valid dengan struktur:\n"
            .'{"tema_judul":"...","dimensi_profil":["Kreativitas","Kolaborasi","Kemandirian"],"tujuan":"...","rangkaian_kegiatan":"Tahap 1: ...\nTahap 2: ...\nTahap 3: ...","mitra":"Orang tua, komite sekolah, ...","refleksi_hasil":"Indikator keberhasilan dan refleksi murid..."}';

        return $this->requestJson($prompt, 'susun_kokurikuler', $tenantId, $userId);
    }

    /**
     * @return array<mixed>
     */
    private function requestJson(string $prompt, string $fitur, ?int $tenantId, int $userId): array
    {
        $quota = $this->quotaStatus($tenantId);
        if (! $quota['configured']) {
            throw new RuntimeException('API Key Google Gemini belum dikonfigurasi. Atur GEMINI_API_KEY di .env atau Pengaturan Sekolah.');
        }
        if ($quota['remaining'] <= 0) {
            throw new RuntimeException("Kuota harian AI ({$quota['limit']} permintaan/hari) sudah habis. Silakan coba lagi besok.");
        }

        $cfg = $this->resolveConfig($tenantId);
        $endpoint = "{$cfg['base_url']}/models/{$cfg['model']}:generateContent";

        $response = Http::timeout(45)
            ->acceptJson()
            ->withHeaders([
                'x-goog-api-key' => $cfg['api_key'],
            ])
            ->post($endpoint, [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'temperature' => 0.4,
                    'responseMimeType' => 'application/json',
                ],
            ]);

        if (! $response->successful()) {
            $this->logUsage($tenantId, $userId, $fitur, $cfg['model'], 'error');
            $msg = $response->json('error.message') ?: ('HTTP '.$response->status());
            throw new RuntimeException("Gagal menghubungi Gemini AI: {$msg}");
        }

        $text = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
        $cleaned = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $text) ?? $text);
        $decoded = json_decode($cleaned, true);

        if (! is_array($decoded)) {
            $this->logUsage($tenantId, $userId, $fitur, $cfg['model'], 'invalid_json');
            throw new RuntimeException('Respons AI tidak dapat dibaca sebagai data terstruktur. Silakan coba lagi.');
        }

        $this->logUsage($tenantId, $userId, $fitur, $cfg['model'], 'ok');

        return $decoded;
    }

    private function logUsage(?int $tenantId, int $userId, string $fitur, string $model, string $status): void
    {
        AiUsageLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'fitur' => $fitur,
            'model' => $model,
            'status' => $status,
            'tanggal' => now()->toDateString(),
        ]);
    }
}
