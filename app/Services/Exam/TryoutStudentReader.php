<?php

namespace App\Services\Exam;

use App\Models\Exam\Attempt as ExamAttempt;
use App\Models\Exam\Session as ExamSession;
use App\Models\Siswa;
use App\Models\Tryout\Result as TryoutResult;
use App\Models\Tryout\Session as TryoutSession;

/**
 * Pembaca halaman utama Tryout siswa (sesi tersedia + riwayat hasil).
 *
 * Fase 3 langkah 6d: ketika `simantap.exam_engine_readers` aktif, data dibaca
 * dari engine terpadu `exam_*`; jika tidak, dari `tryout_*`. Kedua cabang
 * mengembalikan array dengan bentuk yang sama sehingga satu view dipakai untuk
 * dua sumber dan parity-nya bisa diuji (`TryoutStudentReaderTest`).
 *
 * Catatan: `id` pada baris sesi/hasil sengaja tetap **id tabel lama** karena
 * rute `tryout.student.start` / `tryout.student.result` masih terikat model
 * lama sampai langkah 6e. Nilai itu diambil dari `sumber_id` engine, jadi
 * pembaca sudah tidak menyentuh tabel lama.
 */
class TryoutStudentReader
{
    private function pakaiEngine(): bool
    {
        return (bool) config('simantap.exam_engine_readers', false);
    }

    /**
     * Sesi yang boleh diikuti siswa saat ini.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sesiTersedia(Siswa $siswa): array
    {
        $baris = $this->pakaiEngine()
            ? $this->sesiEngine($siswa)
            : $this->sesiLegacy($siswa);

        usort($baris, fn (array $a, array $b) => $a['id'] <=> $b['id']);

        return $baris;
    }

    /**
     * Riwayat percobaan siswa (termasuk yang belum selesai).
     *
     * @return array<int, array<string, mixed>>
     */
    public function hasilSaya(Siswa $siswa): array
    {
        return $this->pakaiEngine()
            ? $this->hasilEngine($siswa)
            : $this->hasilLegacy($siswa);
    }

    // ------------------------------------------------------------- legacy

    /** @return array<int, array<string, mixed>> */
    private function sesiLegacy(Siswa $siswa): array
    {
        return TryoutSession::where('status', 'ongoing')
            ->where('tanggal_mulai', '<=', now())
            ->where('tanggal_selesai', '>=', now())
            ->where(function ($q) use ($siswa) {
                $q->whereNull('kelas')
                    ->orWhere('kelas', $siswa->kelas)
                    ->orWhereHas('rombel', fn ($q) => $q->where('id', $siswa->rombel_id));
            })
            ->with(['package'])
            ->get()
            ->map(fn (TryoutSession $s) => $this->barisSesi(
                id: (int) $s->id,
                nama: (string) $s->nama_sesi,
                kode: (string) $s->kode_sesi,
                paket: $s->package?->nama_paket,
                deskripsi: $s->package?->deskripsi,
                jumlahMapel: (int) ($s->package?->jumlah_mapel ?? 0),
                durasi: (int) ($s->package?->durasi_total_menit ?? 0),
                passing: (float) ($s->package?->passing_grade ?? 0),
                deadline: $s->tanggal_selesai,
            ))
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function hasilLegacy(Siswa $siswa): array
    {
        return TryoutResult::where('siswa_id', $siswa->id)
            ->with(['session.package'])
            ->latest()
            ->get()
            ->map(fn (TryoutResult $r) => $this->barisHasil(
                id: (int) $r->id,
                paket: $r->session?->package?->nama_paket,
                sesi: $r->session?->nama_sesi,
                mulai: $r->waktu_mulai,
                persentase: (float) ($r->persentase ?? 0),
                lulus: (bool) $r->lulus,
                peringkat: $r->peringkat,
            ))
            ->all();
    }

    // ------------------------------------------------------------- engine

    /** @return array<int, array<string, mixed>> */
    private function sesiEngine(Siswa $siswa): array
    {
        return ExamSession::where('sumber_tabel', LegacyExamMapper::SUMBER_TRYOUT_SESI)
            ->where('status', 'ongoing')
            ->where('mulai_at', '<=', now())
            ->where('selesai_at', '>=', now())
            ->where(function ($q) use ($siswa) {
                $q->whereNull('kelas')
                    ->orWhere('kelas', $siswa->kelas);

                if ($siswa->rombel_id) {
                    $q->orWhere('rombel_id', $siswa->rombel_id);
                }
            })
            ->with(['bank'])
            ->get()
            ->map(function (ExamSession $s) {
                $meta = $s->bank?->meta ?? [];

                return $this->barisSesi(
                    id: (int) $s->sumber_id,
                    nama: (string) $s->nama,
                    kode: (string) $s->kode,
                    paket: $s->bank?->nama,
                    deskripsi: $meta['deskripsi'] ?? null,
                    jumlahMapel: (int) ($meta['jumlah_mapel'] ?? 0),
                    durasi: (int) ($meta['durasi_total_menit'] ?? $s->durasi_menit),
                    passing: (float) ($meta['passing_grade'] ?? 0),
                    deadline: $s->selesai_at,
                );
            })
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function hasilEngine(Siswa $siswa): array
    {
        return ExamAttempt::where('siswa_id', $siswa->id)
            ->whereHas('session', fn ($q) => $q->where('sumber_tabel', LegacyExamMapper::SUMBER_TRYOUT_SESI))
            ->with(['session.bank', 'result'])
            ->orderByDesc('mulai_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ExamAttempt $a) => $this->barisHasil(
                id: (int) $a->sumber_id,
                paket: $a->session?->bank?->nama,
                sesi: $a->session?->nama,
                mulai: $a->mulai_at,
                persentase: (float) ($a->result?->persentase ?? 0),
                lulus: (bool) ($a->result?->lulus ?? false),
                peringkat: $a->result?->peringkat,
            ))
            ->all();
    }

    // -------------------------------------------------------------- bentuk

    /** @return array<string, mixed> */
    private function barisSesi(
        int $id,
        string $nama,
        string $kode,
        ?string $paket,
        ?string $deskripsi,
        int $jumlahMapel,
        int $durasi,
        float $passing,
        mixed $deadline,
    ): array {
        return [
            'id' => $id,
            'nama_sesi' => $nama,
            'kode_sesi' => $kode,
            'paket_nama' => $paket ?? '-',
            'deskripsi' => $deskripsi,
            'jumlah_mapel' => $jumlahMapel,
            'durasi_menit' => $durasi,
            'passing_grade' => $passing,
            'deadline' => $deadline?->format('d M Y H:i'),
            'mulai_url' => route('tryout.student.start', $id),
        ];
    }

    /** @return array<string, mixed> */
    private function barisHasil(
        int $id,
        ?string $paket,
        ?string $sesi,
        mixed $mulai,
        float $persentase,
        bool $lulus,
        mixed $peringkat,
    ): array {
        return [
            'id' => $id,
            'paket_nama' => $paket ?? '-',
            'sesi_nama' => $sesi ?? '-',
            'tanggal' => $mulai?->format('d M Y H:i'),
            'persentase' => $persentase,
            'lulus' => $lulus,
            'peringkat' => $peringkat,
            'detail_url' => $id > 0 ? route('tryout.student.result', $id) : null,
        ];
    }
}
