<?php

namespace App\Services;

use App\Models\Siswa;
use App\Models\NilaiErapot;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ErapotKemdikbudService
{
    protected $baseUrl;
    protected $apiKey;
    protected $npsn;

    public function __construct()
    {
        $this->baseUrl = config('services.erapor.url', 'https://erapor.kemdikbud.go.id/api');
        $this->apiKey = config('services.erapor.key');
        $this->npsn = config('services.erapor.npsn');
    }

    public function testConnection(): array
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->timeout(10)->get("{$this->baseUrl}/ping");

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Terhubung ke e-Rapor Kemdikbud', 'data' => $response->json()];
            }

            return ['success' => false, 'message' => 'Response: ' . $response->status()];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function syncSiswa(?array $siswaIds = null): array
    {
        $query = Siswa::where('aktif', true);
        if ($siswaIds) {
            $query->whereIn('id', $siswaIds);
        }

        $siswa = $query->get();
        $berhasil = 0;
        $gagal = 0;
        $errors = [];

        foreach ($siswa as $s) {
            try {
                $payload = [
                    'nisn' => $s->nisn,
                    'nik' => $s->nik,
                    'nama' => $s->nama_peserta_didik,
                    'jenis_kelamin' => $s->jenis_kelamin,
                    'tempat_lahir' => $s->tempat_lahir,
                    'tanggal_lahir' => $s->tanggal_lahir?->format('Y-m-d'),
                    'agama' => $s->agama,
                    'kelas' => $s->kelas,
                    'npsn' => $this->npsn,
                ];

                $response = Http::withHeaders([
                    'X-API-Key' => $this->apiKey,
                    'Accept' => 'application/json',
                ])->timeout(30)->post("{$this->baseUrl}/siswa", $payload);

                if ($response->successful()) {
                    $berhasil++;
                } else {
                    $gagal++;
                    $errors[] = "Siswa {$s->nama_peserta_didik}: " . $response->body();
                }
            } catch (\Exception $e) {
                $gagal++;
                $errors[] = "Siswa {$s->nama_peserta_didik}: " . $e->getMessage();
            }
        }

        return ['success' => true, 'berhasil' => $berhasil, 'gagal' => $gagal, 'errors' => $errors];
    }

    public function syncNilai(?array $nilaiIds = null): array
    {
        $query = NilaiErapot::with('siswa');
        if ($nilaiIds) {
            $query->whereIn('id', $nilaiIds);
        }

        $nilai = $query->get();
        $berhasil = 0;
        $gagal = 0;
        $errors = [];

        foreach ($nilai as $n) {
            if (!$n->siswa || !$n->siswa->nisn) continue;

            try {
                $payload = [
                    'nisn' => $n->siswa->nisn,
                    'mata_pelajaran' => $n->mata_pelajaran,
                    'kelas' => $n->kelas,
                    'semester' => $n->semester,
                    'tahun_ajaran' => $n->tahun_ajaran,
                    'nilai_formatif' => $n->nilai_formatif,
                    'nilai_sumatif' => $n->nilai_sumatif,
                    'nilai_sumatif_akhir' => $n->nilai_sumatif_akhir,
                    'nilai_akhir' => $n->nilai_akhir,
                    'predikat' => $n->predikat,
                    'deskripsi' => $n->deskripsi_capaian,
                    'npsn' => $this->npsn,
                ];

                $response = Http::withHeaders([
                    'X-API-Key' => $this->apiKey,
                    'Accept' => 'application/json',
                ])->timeout(30)->post("{$this->baseUrl}/nilai", $payload);

                if ($response->successful()) {
                    $n->update(['terkirim_erapor' => true, 'erapor_synced_at' => now()]);
                    $berhasil++;
                } else {
                    $gagal++;
                    $errors[] = "Nilai {$n->siswa->nama_peserta_didik} - {$n->mata_pelajaran}: " . $response->body();
                }
            } catch (\Exception $e) {
                $gagal++;
                $errors[] = "Nilai {$n->siswa->nama_peserta_didik}: " . $e->getMessage();
            }
        }

        return ['success' => true, 'berhasil' => $berhasil, 'gagal' => $gagal, 'errors' => $errors];
    }

    public function importFromErapot(string $kelas, string $semester, string $tahunAjaran): array
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->timeout(60)->get("{$this->baseUrl}/nilai", [
                'npsn' => $this->npsn,
                'kelas' => $kelas,
                'semester' => $semester,
                'tahun_ajaran' => $tahunAjaran,
            ]);

            if (!$response->successful()) {
                return ['success' => false, 'message' => 'Gagal mengambil data: ' . $response->body()];
            }

            $data = $response->json();
            $berhasil = 0;

            foreach ($data['data'] ?? [] as $item) {
                $siswa = Siswa::where('nisn', $item['nisn'])->first();
                if (!$siswa) continue;

                NilaiErapot::updateOrCreate(
                    [
                        'siswa_id' => $siswa->id,
                        'mata_pelajaran' => $item['mata_pelajaran'],
                        'semester' => $semester,
                        'tahun_ajaran' => $tahunAjaran,
                    ],
                    [
                        'guru_id' => auth()->id(),
                        'kelas' => $kelas,
                        'nilai_formatif' => $item['nilai_formatif'] ?? 0,
                        'nilai_sumatif' => $item['nilai_sumatif'] ?? 0,
                        'nilai_sumatif_akhir' => $item['nilai_sumatif_akhir'] ?? 0,
                        'nilai_akhir' => $item['nilai_akhir'] ?? 0,
                        'predikat' => $item['predikat'] ?? null,
                        'deskripsi_capaian' => $item['deskripsi'] ?? null,
                        'erapor_synced_at' => now(),
                    ]
                );
                $berhasil++;
            }

            return ['success' => true, 'message' => "Berhasil import {$berhasil} nilai dari e-Rapor", 'berhasil' => $berhasil];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
