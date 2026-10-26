<?php

namespace App\Services\Exam;

use App\Models\CbtTestSession;
use App\Models\Exam\Session as ExamSession;
use App\Models\Siswa;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Pembaca daftar ujian CBT untuk siswa.
 *
 * Fase 3 langkah 6d: dengan `simantap.exam_engine_readers` aktif, daftar dibaca
 * dari `exam_sessions` (`sumber_tabel = cbt_test_sessions`); jika tidak, dari
 * tabel lama. Kedua cabang menghasilkan paginator array dengan bentuk sama.
 * `id` tetap id tabel lama (`sumber_id`) karena rute `cbt.student.start` masih
 * terikat model lama sampai langkah 6e.
 */
class CbtStudentReader
{
    private function pakaiEngine(): bool
    {
        return (bool) config('simantap.exam_engine_readers', false);
    }

    /** @return LengthAwarePaginator */
    public function sesiSiswa(Siswa $siswa, int $perPage = 20): LengthAwarePaginator
    {
        return $this->pakaiEngine()
            ? $this->sesiEngine($siswa, $perPage)
            : $this->sesiLegacy($siswa, $perPage);
    }

    private function sesiLegacy(Siswa $siswa, int $perPage): LengthAwarePaginator
    {
        return CbtTestSession::where('guru_id', $siswa->guru_id)
            ->whereIn('status', ['published', 'running', 'completed'])
            ->whereJsonContains('participant_ids', (int) $siswa->id)
            ->latest()
            ->paginate($perPage)
            ->through(fn (CbtTestSession $s) => $this->baris(
                id: (int) $s->id,
                title: (string) $s->title,
                opensAt: $s->opens_at,
                closesAt: $s->closes_at,
                duration: (int) $s->duration_minutes,
            ));
    }

    private function sesiEngine(Siswa $siswa, int $perPage): LengthAwarePaginator
    {
        return ExamSession::where('sumber_tabel', LegacyExamMapper::SUMBER_CBT_SESI)
            ->where('guru_id', $siswa->guru_id)
            ->whereIn('status', ['published', 'running', 'completed'])
            ->whereJsonContains('peserta_ids', (int) $siswa->id)
            ->latest()
            ->paginate($perPage)
            ->through(fn (ExamSession $s) => $this->baris(
                id: (int) $s->sumber_id,
                title: (string) $s->nama,
                opensAt: $s->mulai_at,
                closesAt: $s->selesai_at,
                duration: (int) $s->durasi_menit,
            ));
    }

    /** @return array<string, mixed> */
    private function baris(int $id, string $title, mixed $opensAt, mixed $closesAt, int $duration): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'jadwal' => $opensAt?->format('d/m/Y H:i').' — '.$closesAt?->format('d/m/Y H:i'),
            'duration_minutes' => $duration,
            'start_url' => route('cbt.student.start', $id),
        ];
    }
}
