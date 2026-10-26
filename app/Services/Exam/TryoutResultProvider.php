<?php

namespace App\Services\Exam;

use App\Models\Exam\Attempt as ExamAttempt;
use App\Models\Tryout\Result as TryoutResult;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Pembaca daftar & rincian hasil Tryout untuk admin.
 *
 * Fase 3 langkah 6b: ketika `simantap.exam_engine_readers` aktif, data dibaca
 * dari engine terpadu `exam_*`; jika tidak, dari `tryout_*`. Kedua cabang
 * mengembalikan array dengan bentuk yang sama, sehingga satu view dipakai untuk
 * dua sumber dan parity-nya bisa diuji (`ExamReaderTest`).
 */
class TryoutResultProvider
{
    private function pakaiEngine(): bool
    {
        return (bool) config('simantap.exam_engine_readers', false);
    }

    /**
     * Hasil terfilter + ringkasan kartu statistik halaman daftar.
     *
     * @param  array<string, mixed>  $filter
     * @return array{hasil: LengthAwarePaginator, ringkasan: array<string, int|float>}
     */
    public function daftar(array $filter = [], int $perPage = 30): array
    {
        $hasil = $this->pakaiEngine()
            ? $this->daftarEngine($filter, $perPage)
            : $this->daftarLegacy($filter, $perPage);

        return [
            'hasil' => $hasil,
            'ringkasan' => $this->ringkasan($hasil),
        ];
    }

    /**
     * Rincian satu hasil dari tabel lama.
     *
     * @return array<string, mixed>
     */
    public function rincianDariLegacy(TryoutResult $result): array
    {
        return [
            'siswa_nama' => $result->siswa?->nama_peserta_didik ?? '-',
            'siswa_nis' => $result->siswa?->nis ?? '-',
            'siswa_kelas' => $result->siswa?->kelas ?? '-',
            'sesi_nama' => $result->session?->nama_sesi ?? '-',
            'paket_nama' => $result->session?->package?->nama_paket ?? '-',
            'attempt_number' => (int) ($result->attempt_number ?? 1),
            'waktu_mulai' => $result->waktu_mulai,
            'waktu_selesai' => $result->waktu_selesai,
            'durasi_detik' => $result->durasi_total_detik !== null ? (int) $result->durasi_total_detik : null,
            'lulus' => (bool) $result->lulus,
            'nilai_total' => (float) ($result->nilai_total ?? 0),
            'nilai_maksimal' => (float) ($result->nilai_maksimal ?? 0),
            'persentase' => (float) ($result->persentase ?? 0),
            'peringkat' => $result->peringkat,
            'detail_mapel' => $result->detail_nilai_mapel ?? [],
            'kelemahan' => $result->analisis_kelemahan ?? [],
            'rekomendasi' => (string) ($result->rekomendasi_pembelajaran ?? ''),
        ];
    }

    /**
     * Rincian satu hasil dari engine terpadu.
     *
     * @return array<string, mixed>
     */
    public function rincianDariEngine(ExamAttempt $attempt): array
    {
        $result = $attempt->result;

        return [
            'siswa_nama' => $attempt->siswa?->nama_peserta_didik ?? '-',
            'siswa_nis' => $attempt->siswa?->nis ?? '-',
            'siswa_kelas' => $attempt->siswa?->kelas ?? '-',
            'sesi_nama' => $attempt->session?->nama ?? '-',
            'paket_nama' => $attempt->session?->bank?->nama ?? '-',
            'attempt_number' => (int) ($attempt->attempt_number ?? 1),
            'waktu_mulai' => $attempt->mulai_at,
            'waktu_selesai' => $attempt->selesai_at,
            'durasi_detik' => $attempt->selesai_at && $attempt->mulai_at
                ? (int) $attempt->selesai_at->diffInSeconds($attempt->mulai_at)
                : null,
            'lulus' => (bool) ($result?->lulus ?? false),
            'nilai_total' => (float) ($result?->nilai_total ?? $attempt->nilai_total ?? 0),
            'nilai_maksimal' => (float) ($result?->nilai_maksimal ?? $attempt->nilai_maksimal ?? 0),
            'persentase' => (float) ($result?->persentase ?? 0),
            'peringkat' => $result?->peringkat,
            'detail_mapel' => $result?->detail_per_section ?? [],
            'kelemahan' => $result?->analisis_kelemahan ?? [],
            'rekomendasi' => (string) ($result?->rekomendasi ?? ''),
        ];
    }

    /** @param  array<string, mixed>  $filter */
    private function daftarLegacy(array $filter, int $perPage): LengthAwarePaginator
    {
        $query = TryoutResult::with(['session.package', 'siswa']);

        if (isset($filter['lulus'])) {
            $query->where('lulus', (bool) $filter['lulus']);
        }
        if (! empty($filter['session_id'])) {
            $query->where('tryout_session_id', $filter['session_id']);
        }
        if (! empty($filter['tanggal_from'])) {
            $query->whereDate('waktu_mulai', '>=', $filter['tanggal_from']);
        }
        if (! empty($filter['tanggal_to'])) {
            $query->whereDate('waktu_mulai', '<=', $filter['tanggal_to']);
        }
        if (! empty($filter['search'])) {
            $query->whereHas('siswa', fn ($q) => $q->where('nama_peserta_didik', 'like', '%'.$filter['search'].'%'));
        }

        return $query->latest()
            ->paginate($perPage)
            ->through(fn (TryoutResult $r) => $this->barisLegacy($r));
    }

    /** @param  array<string, mixed>  $filter */
    private function daftarEngine(array $filter, int $perPage): LengthAwarePaginator
    {
        $query = ExamAttempt::with(['session.bank', 'siswa', 'result'])
            ->withCount([
                'answers as benar_count' => fn ($q) => $q->where('adalah_benar', true),
                'answers as salah_count' => fn ($q) => $q->where('adalah_benar', false),
                'answers as kosong_count' => fn ($q) => $q->whereNull('adalah_benar'),
            ])
            ->whereHas('session', fn ($q) => $q
                ->where('sumber_tabel', LegacyExamMapper::SUMBER_TRYOUT_SESI));

        if (isset($filter['lulus'])) {
            $query->whereHas('result', fn ($q) => $q->where('lulus', (bool) $filter['lulus']));
        }
        if (! empty($filter['session_id'])) {
            $query->whereHas('session', fn ($q) => $q
                ->where('sumber_tabel', LegacyExamMapper::SUMBER_TRYOUT_SESI)
                ->where('sumber_id', $filter['session_id']));
        }
        if (! empty($filter['tanggal_from'])) {
            $query->whereDate('mulai_at', '>=', $filter['tanggal_from']);
        }
        if (! empty($filter['tanggal_to'])) {
            $query->whereDate('mulai_at', '<=', $filter['tanggal_to']);
        }
        if (! empty($filter['search'])) {
            $query->whereHas('siswa', fn ($q) => $q->where('nama_peserta_didik', 'like', '%'.$filter['search'].'%'));
        }

        return $query->orderByDesc('mulai_at')->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn (ExamAttempt $a) => $this->barisEngine($a));
    }

    /** @return array<string, mixed> */
    private function barisLegacy(TryoutResult $result): array
    {
        return [
            'detail_url' => route('admin.tryout.results.show', $result),
            'siswa_nama' => $result->siswa?->nama_peserta_didik ?? '-',
            'siswa_kelas' => $result->siswa?->kelas ?? '-',
            'sesi_nama' => $result->session?->nama_sesi ?? '-',
            'paket_nama' => $result->session?->package?->nama_paket ?? '-',
            'skor_total' => (float) $result->skor_total,
            'jumlah_benar' => $result->jumlah_benar,
            'jumlah_salah' => $result->jumlah_salah,
            'jumlah_kosong' => $result->jumlah_kosong,
            'waktu_detik' => $result->waktu_pengerjaan_detik,
            'lulus' => (bool) $result->lulus,
        ];
    }

    /** @return array<string, mixed> */
    private function barisEngine(ExamAttempt $attempt): array
    {
        $result = $attempt->result;

        return [
            'detail_url' => route('admin.tryout.results.engine.show', $attempt),
            'siswa_nama' => $attempt->siswa?->nama_peserta_didik ?? '-',
            'siswa_kelas' => $attempt->siswa?->kelas ?? '-',
            'sesi_nama' => $attempt->session?->nama ?? '-',
            'paket_nama' => $attempt->session?->bank?->nama ?? '-',
            'skor_total' => (float) ($result?->persentase ?? 0),
            'jumlah_benar' => (int) ($attempt->benar_count ?? 0),
            'jumlah_salah' => (int) ($attempt->salah_count ?? 0),
            'jumlah_kosong' => (int) ($attempt->kosong_count ?? 0),
            'waktu_detik' => $attempt->selesai_at && $attempt->mulai_at
                ? (int) $attempt->selesai_at->diffInSeconds($attempt->mulai_at)
                : null,
            'lulus' => (bool) ($result?->lulus ?? false),
        ];
    }

    /**
     * Kartu statistik dihitung dari halaman yang sedang tampil, sama seperti
     * perilaku lama (`$results->avg()` pada paginator).
     *
     * @return array<string, int|float>
     */
    private function ringkasan(LengthAwarePaginator $hasil): array
    {
        $baris = collect($hasil->items());

        return [
            'total' => $hasil->total(),
            'lulus' => $baris->where('lulus', true)->count(),
            'tidak_lulus' => $baris->where('lulus', false)->count(),
            'rata' => (float) ($baris->avg('skor_total') ?? 0),
            'tertinggi' => (float) ($baris->max('skor_total') ?? 0),
            'terendah' => (float) ($baris->min('skor_total') ?? 0),
        ];
    }
}
