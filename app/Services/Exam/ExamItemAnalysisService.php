<?php

namespace App\Services\Exam;

use App\Models\CbtTestSession;
use App\Models\Exam\Attempt;
use App\Models\Exam\Question;
use App\Models\Exam\Session as ExamSession;

/**
 * Analisis butir soal untuk sesi CBT.
 *
 * Sumber utama adalah engine `exam_*`. Sesi lama yang belum punya padanan
 * engine tetap dapat dianalisis dari snapshot dan hasil `cbt_*`; respons selalu
 * menyebut sumber datanya agar operator tidak salah menafsirkan.
 *
 * Rumus yang dipakai:
 * - P (tingkat kesukaran) = jumlah benar / jumlah peserta.
 * - D (daya beda) = P kelompok atas 27% - P kelompok bawah 27%.
 * - Distraktor = frekuensi setiap opsi selain kunci, termasuk jawaban kosong.
 *
 * Analisis bersifat read-only dan tidak mengubah nilai.
 */
class ExamItemAnalysisService
{
    public function untukSesiCbt(CbtTestSession $cbtSession): array
    {
        $examSession = ExamSession::query()
            ->where('sumber_tabel', LegacyExamMapper::SUMBER_CBT_SESI)
            ->where('sumber_id', $cbtSession->id)
            ->first();

        if ($examSession) {
            $hasil = $this->analisisEngine($examSession);

            // Sesi yang baru dipetakan tetapi belum punya attempt engine tetap
            // dianalisis dari data lama supaya halaman tidak kosong.
            if ($hasil['jumlah_peserta'] > 0) {
                return $hasil;
            }
        }

        return $this->analisisLegacyCbt($cbtSession);
    }

    public function analisisEngine(ExamSession $session): array
    {
        $questions = $session->bank()
            ->firstOrFail()
            ->questions()
            ->with('options')
            ->orderBy('urutan')
            ->orderBy('id')
            ->get()
            ->map(function (Question $question): array {
                $opsi = $question->options
                    ->mapWithKeys(fn ($option): array => [
                        $this->normalisasiHuruf($option->huruf) => (string) $option->isi_opsi,
                    ])
                    ->all();

                $kunci = $this->normalisasiJawaban($question->kunci_jawaban);
                if ($kunci === null) {
                    $benar = $question->options->firstWhere('adalah_benar', true);
                    $kunci = $benar ? $this->normalisasiHuruf($benar->huruf) : null;
                }

                return [
                    'id' => $question->id,
                    'teks' => (string) $question->pertanyaan,
                    'tipe' => (string) $question->tipe,
                    'opsi' => $opsi,
                    'kunci' => $kunci,
                ];
            })
            ->values()
            ->all();

        $semua = $session->attempts()
            ->with(['answers', 'result'])
            ->orderBy('id')
            ->get();

        $selesai = $semua->filter(fn (Attempt $attempt): bool => $attempt->selesai_at !== null
            || in_array($attempt->status, [Attempt::STATUS_DIKUMPULKAN, Attempt::STATUS_DINILAI], true));

        $dipakai = $selesai->isNotEmpty() ? $selesai : $semua;

        $attempts = $dipakai->map(function (Attempt $attempt) use ($questions): array {
            $jawaban = [];
            $benar = [];

            foreach ($attempt->answers as $answer) {
                $id = (int) $answer->question_id;
                $jawaban[$id] = $this->normalisasiJawaban($answer->jawaban);

                if ($answer->adalah_benar !== null) {
                    $benar[$id] = (bool) $answer->adalah_benar;
                }
            }

            return [
                'id' => $attempt->id,
                'nilai' => (float) ($attempt->result?->nilai_total ?? $attempt->nilai_total ?? 0),
                'jawaban' => $jawaban,
                'benar' => $benar,
            ];
        })->values()->all();

        return $this->hitung(
            $questions,
            $attempts,
            'engine',
            $selesai->isNotEmpty() ? 'selesai' : 'sebagian',
        );
    }

    public function analisisLegacyCbt(CbtTestSession $session): array
    {
        $snapshot = is_array($session->question_snapshot) ? $session->question_snapshot : [];

        if ($snapshot === []) {
            $snapshot = $session->bank()
                ->first()
                ?->questions()
                ->orderBy('id')
                ->get()
                ->map(fn ($question): array => [
                    'id' => $question->id,
                    'prompt' => $question->prompt,
                    'options' => $question->options,
                    'correct_answer' => $question->correct_answer,
                ])
                ->all() ?? [];
        }

        $questions = collect($snapshot)->map(function (array $question): array {
            $opsi = [];
            foreach ((array) ($question['options'] ?? []) as $huruf => $isi) {
                $opsi[$this->normalisasiHuruf($huruf)] = (string) $isi;
            }

            return [
                'id' => (int) $question['id'],
                'teks' => (string) ($question['prompt'] ?? ''),
                'tipe' => 'pilihan_ganda',
                'opsi' => $opsi,
                'kunci' => $this->normalisasiJawaban($question['correct_answer'] ?? null),
            ];
        })->values()->all();

        $results = $session->results()->orderBy('id')->get();
        $selesai = $results->filter(fn ($result): bool => $result->submitted_at !== null);
        $dipakai = $selesai->isNotEmpty() ? $selesai : $results;

        $attempts = $dipakai->map(function ($result): array {
            $jawaban = [];
            foreach ((array) ($result->answers ?? []) as $id => $jawabanSiswa) {
                $jawaban[(int) $id] = $this->normalisasiJawaban($jawabanSiswa);
            }

            return [
                'id' => $result->id,
                'nilai' => (float) ($result->score ?? $result->correct_count ?? 0),
                'jawaban' => $jawaban,
                'benar' => [],
            ];
        })->values()->all();

        return $this->hitung(
            $questions,
            $attempts,
            'legacy',
            $selesai->isNotEmpty() ? 'selesai' : 'sebagian',
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $questions
     * @param  array<int, array<string, mixed>>  $attempts
     */
    private function hitung(array $questions, array $attempts, string $sumber, string $statusData): array
    {
        $jumlahPeserta = count($attempts);
        $urut = $attempts;
        usort($urut, fn (array $a, array $b): int => [$b['nilai'], $a['id']] <=> [$a['nilai'], $b['id']]);

        $ukuranKelompok = $jumlahPeserta >= 4 ? max(1, (int) round($jumlahPeserta * 0.27)) : 0;
        $atas = $ukuranKelompok > 0 ? array_slice($urut, 0, $ukuranKelompok) : [];
        $bawah = $ukuranKelompok > 0 ? array_slice($urut, -$ukuranKelompok) : [];
        $idAtas = array_flip(array_column($atas, 'id'));
        $idBawah = array_flip(array_column($bawah, 'id'));

        $baris = [];
        $nilaiP = [];
        $nilaiD = [];

        foreach ($questions as $index => $question) {
            $idSoal = (int) $question['id'];
            $kunci = $question['kunci'];
            $opsi = $question['opsi'] ?? [];
            $frekuensi = array_fill_keys(array_keys($opsi), 0);
            $kosong = 0;
            $benar = 0;
            $benarAtas = 0;
            $benarBawah = 0;

            foreach ($attempts as $attempt) {
                $jawaban = $attempt['jawaban'][$idSoal] ?? null;
                $isBenar = $attempt['benar'][$idSoal]
                    ?? ($kunci !== null && $jawaban !== null && $jawaban === $kunci);

                if ($isBenar) {
                    $benar++;
                    if (isset($idAtas[$attempt['id']])) {
                        $benarAtas++;
                    }
                    if (isset($idBawah[$attempt['id']])) {
                        $benarBawah++;
                    }
                }

                if ($jawaban === null) {
                    $kosong++;
                } elseif (array_key_exists($jawaban, $frekuensi)) {
                    $frekuensi[$jawaban]++;
                }
            }

            $p = $jumlahPeserta > 0 ? round($benar / $jumlahPeserta, 3) : null;
            $d = $ukuranKelompok > 0
                ? round(($benarAtas / $ukuranKelompok) - ($benarBawah / $ukuranKelompok), 3)
                : null;

            if ($p !== null) {
                $nilaiP[] = $p;
            }
            if ($d !== null) {
                $nilaiD[] = $d;
            }

            $distraktor = [];
            foreach ($frekuensi as $huruf => $jumlah) {
                if ($kunci !== null && (string) $huruf === (string) $kunci) {
                    continue;
                }
                $distraktor[(string) $huruf] = $jumlah;
            }

            $catatan = [];
            if ($kunci === null) {
                $catatan[] = 'Kunci jawaban tidak ditemukan';
            }
            if ($p !== null && $p < 0.30) {
                $catatan[] = 'Tingkat kesukaran tinggi (sulit)';
            } elseif ($p !== null && $p > 0.80) {
                $catatan[] = 'Terlalu mudah';
            }
            if ($d !== null && $d < 0.20) {
                $catatan[] = 'Daya beda lemah';
            }
            if ($jumlahPeserta > 0 && $kosong > 0) {
                $catatan[] = "{$kosong} jawaban kosong";
            }
            if ($distraktor !== [] && in_array(0, $distraktor, true)) {
                $kosongDistraktor = array_keys($distraktor, 0, true);
                $catatan[] = 'Distraktor tidak dipilih: '.implode(', ', $kosongDistraktor);
            }

            $baris[] = [
                'nomor' => $index + 1,
                'id' => $idSoal,
                'teks' => $question['teks'] ?? '',
                'tipe' => $question['tipe'] ?? 'pilihan_ganda',
                'kunci' => $kunci,
                'jumlah_peserta' => $jumlahPeserta,
                'jumlah_benar' => $benar,
                'p' => $p,
                'd' => $d,
                'kategori_p' => $this->kategoriP($p),
                'kategori_d' => $this->kategoriD($d),
                'distraktor' => $distraktor,
                'kosong' => $kosong,
                'catatan' => $catatan,
            ];
        }

        return [
            'sumber' => $sumber,
            'status_data' => $statusData,
            'jumlah_peserta' => $jumlahPeserta,
            'jumlah_soal' => count($questions),
            'rata_p' => $nilaiP !== [] ? round(array_sum($nilaiP) / count($nilaiP), 3) : null,
            'rata_d' => $nilaiD !== [] ? round(array_sum($nilaiD) / count($nilaiD), 3) : null,
            'butir' => $baris,
        ];
    }

    private function kategoriP(?float $p): string
    {
        if ($p === null) {
            return 'Belum ada data';
        }

        return $p < 0.30 ? 'Sulit' : ($p > 0.80 ? 'Mudah' : 'Sedang');
    }

    private function kategoriD(?float $d): string
    {
        if ($d === null) {
            return 'Belum cukup data';
        }

        return $d < 0.20 ? 'Lemah' : ($d < 0.30 ? 'Cukup' : 'Baik');
    }

    private function normalisasiHuruf(mixed $huruf): string
    {
        return mb_strtoupper(trim((string) $huruf));
    }

    private function normalisasiJawaban(mixed $jawaban): ?string
    {
        if ($jawaban === null) {
            return null;
        }

        $teks = mb_strtoupper(trim((string) $jawaban));

        return $teks === '' ? null : $teks;
    }
}
