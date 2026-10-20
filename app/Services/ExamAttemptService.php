<?php

namespace App\Services;

use App\Models\Exam\Attempt;
use App\Models\Exam\Question;
use App\Models\Exam\Result;
use App\Models\Exam\Session as ExamSession;
use App\Models\Siswa;
use Illuminate\Support\Facades\DB;

/**
 * Fase 3: satu-satunya pengelola percobaan ujian pada engine terpadu.
 *
 * Mencakup siklus yang diminta kriteria Fase 3: attempt dibuat → dijawab →
 * dilanjutkan (resume) → dikumpulkan sekali → dinilai → hasil tersimpan.
 *
 * Semua penilaian dilakukan di server lewat `ExamGradingService`; kunci
 * jawaban tidak pernah diterima dari klien.
 */
class ExamAttemptService
{
    public function __construct(
        private ExamGradingService $grader,
    ) {}

    /**
     * Ambil percobaan yang masih berjalan, atau buat nomor percobaan berikutnya.
     */
    public function mulai(ExamSession $session, Siswa $siswa, ?int $userId, int $durasiMenit = 0): Attempt
    {
        return DB::transaction(function () use ($session, $siswa, $userId, $durasiMenit) {
            $berjalan = Attempt::where('session_id', $session->id)
                ->where('siswa_id', $siswa->id)
                ->whereNull('selesai_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($berjalan) {
                return $berjalan;
            }

            $nomor = (int) Attempt::where('session_id', $session->id)
                ->where('siswa_id', $siswa->id)
                ->max('attempt_number') + 1;

            $mulai = now();

            return Attempt::create([
                'session_id' => $session->id,
                'siswa_id' => $siswa->id,
                'user_id' => $userId,
                'attempt_number' => $nomor,
                'mulai_at' => $mulai,
                'deadline_at' => $durasiMenit > 0 ? (clone $mulai)->addMinutes($durasiMenit) : null,
                'status' => Attempt::STATUS_BERJALAN,
            ]);
        });
    }

    /**
     * Simpan satu jawaban. Mengembalikan false bila soal bukan milik sesi.
     */
    public function simpanJawaban(Attempt $attempt, int $questionId, ?string $jawaban): bool
    {
        $question = Question::with('options')
            ->whereKey($questionId)
            ->where('bank_id', $attempt->session->bank_id)
            ->first();

        if (! $question) {
            return false;
        }

        $this->grader->simpanJawaban($attempt, $question, $jawaban);

        return true;
    }

    /**
     * Nilai seluruh jawaban percobaan ini lalu simpan hasilnya. Idempoten:
     * memanggil dua kali menghasilkan hasil sama dan tidak menggandakan baris.
     */
    public function selesaikan(Attempt $attempt, ?float $passingGrade = null): Result
    {
        return DB::transaction(function () use ($attempt, $passingGrade) {
            $attempt->refresh();
            $questions = Question::with(['options', 'section'])
                ->where('bank_id', $attempt->session->bank_id)
                ->orderBy('urutan')
                ->orderBy('id')
                ->get();

            $jawaban = $attempt->answers()->pluck('jawaban', 'question_id');

            $total = 0.0;
            $maksimal = 0.0;
            $benar = 0;
            $detail = [];

            foreach ($questions as $question) {
                $isi = $jawaban[$question->id] ?? null;
                [$isBenar, $poin] = ExamGradingService::nilai($question, $isi);

                $total += $poin;
                $maksimal += (float) $question->poin;
                if ($isBenar) {
                    $benar++;
                }

                $sectionId = (int) ($question->section_id ?? 0);
                $detail[$sectionId] ??= [
                    'nama_mapel' => $question->section?->nama ?? 'Umum',
                    'mata_pelajaran_id' => $question->section?->mata_pelajaran_id,
                    'skor' => 0.0,
                    'maksimal' => 0.0,
                    'bobot' => (float) ($question->section->bobot ?? 1),
                ];
                $detail[$sectionId]['skor'] += $poin;
                $detail[$sectionId]['maksimal'] += (float) $question->poin;
            }

            foreach ($detail as &$baris) {
                $baris['persentase'] = $baris['maksimal'] > 0
                    ? round(100 * $baris['skor'] / $baris['maksimal'], 2)
                    : 0.0;
            }
            unset($baris);

            // Total berbobot (parity Tryout: skor mapel x bobot).
            $totalBerbobot = 0.0;
            $maksimalBerbobot = 0.0;
            foreach ($detail as $baris) {
                $totalBerbobot += $baris['skor'] * $baris['bobot'];
                $maksimalBerbobot += $baris['maksimal'] * $baris['bobot'];
            }

            $persentase = $maksimalBerbobot > 0
                ? round(100 * $totalBerbobot / $maksimalBerbobot, 2)
                : 0.0;

            $peringkat = Result::whereHas('attempt', fn ($q) => $q
                ->where('session_id', $attempt->session_id)
                ->whereNotNull('selesai_at'))
                ->where('nilai_total', '>', $totalBerbobot)
                ->count() + 1;

            $attempt->forceFill([
                'selesai_at' => now(),
                'status' => Attempt::STATUS_DINILAI,
                'nilai_total' => round($totalBerbobot, 2),
                'nilai_maksimal' => round($maksimalBerbobot, 2),
            ])->save();

            $kelemahan = collect($detail)
                ->where('persentase', '<', 60)
                ->sortBy('persentase')
                ->take(3)
                ->values()
                ->all();

            return Result::updateOrCreate(
                ['attempt_id' => $attempt->id],
                [
                    'nilai_total' => round($totalBerbobot, 2),
                    'nilai_maksimal' => round($maksimalBerbobot, 2),
                    'persentase' => $persentase,
                    'peringkat' => $peringkat,
                    'lulus' => $passingGrade === null ? null : $persentase >= $passingGrade,
                    'detail_per_section' => array_values($detail),
                    'analisis_kelemahan' => $kelemahan,
                ]
            );
        }, 3);
    }
}
