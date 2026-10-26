<?php

namespace App\Services\Exam;

use App\Models\CbtQuestion;
use App\Models\CbtQuestionBank;
use App\Models\CbtTestSession;
use App\Models\Exam\Bank;
use App\Models\Exam\Engine;
use App\Models\Exam\Question;
use App\Models\Exam\QuestionOption;
use App\Models\Exam\Section;
use App\Models\Exam\Session as ExamSession;
use App\Models\Tryout\Package;
use App\Models\Tryout\Session as TryoutSession;
use App\Models\Tryout\Subject;
use App\Models\Tryout\TryoutQuestion;

/**
 * Satu-satunya tempat memetakan data lama (cbt_*, tryout_*) ke engine terpadu
 * (exam_*). Dipakai oleh `exam:backfill` DAN oleh alur ujian langsung, supaya
 * keduanya tidak pernah berbeda aturan.
 *
 * Semua operasi idempoten (kunci alami: sumber_tabel + sumber_id).
 */
class LegacyExamMapper
{
    public const SUMBER_CBT_BANK = 'cbt_question_banks';

    public const SUMBER_CBT_SOAL = 'cbt_questions';

    public const SUMBER_CBT_SESI = 'cbt_test_sessions';

    public const SUMBER_TRYOUT_PAKET = 'tryout_packages';

    public const SUMBER_TRYOUT_MAPEL = 'tryout_subjects';

    public const SUMBER_TRYOUT_SOAL = 'tryout_questions';

    public const SUMBER_TRYOUT_SESI = 'tryout_sessions';

    public function engine(string $kode, string $nama): Engine
    {
        return Engine::updateOrCreate(
            ['kode' => $kode],
            ['nama' => $nama, 'jenis' => $kode, 'aktif' => true]
        );
    }

    // ---------------------------------------------------------------- CBT

    public function bankCbt(CbtQuestionBank $cbtBank): Bank
    {
        $bank = Bank::updateOrCreate(
            ['sumber_tabel' => self::SUMBER_CBT_BANK, 'sumber_id' => $cbtBank->id],
            ['engine_id' => $this->engine(Engine::CBT, 'CBT (pilihan ganda)')->id]
                + $this->atributBankCbt($cbtBank)
        );

        foreach ($cbtBank->questions()->orderBy('id')->get() as $cbtQuestion) {
            $this->soalCbt($bank, $cbtQuestion);
        }

        return $bank;
    }

    /**
     * Atribut bank CBT tanpa efek samping. Dipakai bersama oleh pembuat dan
     * pemeriksa rekonsiliasi agar keduanya tidak pernah berbeda aturan.
     *
     * @return array<string, mixed>
     */
    public function atributBankCbt(CbtQuestionBank $cbtBank): array
    {
        return [
            'guru_id' => $cbtBank->guru_id,
            'nama' => $cbtBank->title,
            'mata_pelajaran' => $cbtBank->subject,
        ];
    }

    public function soalCbt(Bank $bank, CbtQuestion $cbtQuestion): Question
    {
        $question = Question::updateOrCreate(
            ['sumber_tabel' => self::SUMBER_CBT_SOAL, 'sumber_id' => $cbtQuestion->id],
            ['bank_id' => $bank->id] + $this->atributSoalCbt($cbtQuestion)
        );

        foreach ($this->atributOpsiCbt($cbtQuestion) as $huruf => $atribut) {
            QuestionOption::updateOrCreate(
                ['question_id' => $question->id, 'huruf' => (string) $huruf],
                $atribut
            );
        }

        // Opsi yang dihapus di bank tidak boleh tertinggal di engine.
        $hurufAktif = array_map('strval', array_keys($this->atributOpsiCbt($cbtQuestion)));
        QuestionOption::where('question_id', $question->id)->whereNotIn('huruf', $hurufAktif)->delete();

        return $question;
    }

    /** @return array<string, mixed> */
    public function atributSoalCbt(CbtQuestion $cbtQuestion): array
    {
        return [
            'tipe' => 'pilihan_ganda',
            'pertanyaan' => $cbtQuestion->prompt,
            'poin' => 1,
            'aktif' => true,
        ];
    }

    /**
     * @return array<string, array{isi_opsi: string, adalah_benar: bool, urutan: int}>
     */
    public function atributOpsiCbt(CbtQuestion $cbtQuestion): array
    {
        $hasil = [];

        foreach ((array) $cbtQuestion->options as $huruf => $isi) {
            $hasil[(string) $huruf] = [
                'isi_opsi' => (string) $isi,
                'adalah_benar' => (string) $huruf === (string) $cbtQuestion->correct_answer,
                'urutan' => is_numeric($huruf)
                    ? (int) $huruf
                    : ord(mb_strtoupper((string) $huruf)) - 64,
            ];
        }

        return $hasil;
    }

    public function sesiCbt(Bank $bank, CbtTestSession $cbtSession): ExamSession
    {
        return ExamSession::updateOrCreate(
            ['sumber_tabel' => self::SUMBER_CBT_SESI, 'sumber_id' => $cbtSession->id],
            ['bank_id' => $bank->id] + $this->atributSesiCbt($cbtSession)
        );
    }

    /** @return array<string, mixed> */
    public function atributSesiCbt(CbtTestSession $cbtSession): array
    {
        return [
            'guru_id' => $cbtSession->guru_id,
            'nama' => $cbtSession->title,
            'kelas' => $cbtSession->kelas,
            'mulai_at' => $cbtSession->opens_at,
            'selesai_at' => $cbtSession->closes_at,
            'durasi_menit' => $cbtSession->duration_minutes,
            'status' => $cbtSession->status,
            'soal_snapshot' => $cbtSession->question_snapshot,
            'peserta_ids' => $cbtSession->participant_ids,
        ];
    }

    /**
     * Cermin penghapusan: hapus padanan engine saat baris lama dihapus.
     * Engine memakai soft delete, jadi riwayat tidak hilang permanen.
     */
    public function lupakanBankCbt(int $cbtBankId): void
    {
        Bank::where('sumber_tabel', self::SUMBER_CBT_BANK)->where('sumber_id', $cbtBankId)->delete();
    }

    public function lupakanSoalCbt(int $cbtQuestionId): void
    {
        Question::where('sumber_tabel', self::SUMBER_CBT_SOAL)->where('sumber_id', $cbtQuestionId)->delete();
    }

    public function lupakanSesiCbt(int $cbtSessionId): void
    {
        ExamSession::where('sumber_tabel', self::SUMBER_CBT_SESI)->where('sumber_id', $cbtSessionId)->delete();
    }

    public function lupakanPaketTryout(int $packageId): void
    {
        Bank::where('sumber_tabel', self::SUMBER_TRYOUT_PAKET)->where('sumber_id', $packageId)->delete();
    }

    public function lupakanSesiTryout(int $tryoutSessionId): void
    {
        ExamSession::where('sumber_tabel', self::SUMBER_TRYOUT_SESI)->where('sumber_id', $tryoutSessionId)->delete();
    }

    // -------------------------------------------------------------- Tryout

    public function bankTryout(Package $package): Bank
    {
        return Bank::updateOrCreate(
            ['sumber_tabel' => self::SUMBER_TRYOUT_PAKET, 'sumber_id' => $package->id],
            ['engine_id' => $this->engine(Engine::TRYOUT, 'Tryout')->id]
                + $this->atributBankTryout($package)
        );
    }

    /** @return array<string, mixed> */
    public function atributBankTryout(Package $package): array
    {
        return [
            'kode' => $package->kode_paket,
            'nama' => $package->nama_paket,
            'meta' => [
                'category_id' => $package->category_id,
                'jenjang' => $package->jenjang,
                'tahun_paket' => $package->tahun_paket,
                'durasi_total_menit' => $package->durasi_total_menit,
                'passing_grade' => $package->passing_grade,
                'deskripsi' => $package->deskripsi,
                'jumlah_mapel' => $package->jumlah_mapel,
            ],
        ];
    }

    public function sectionTryout(Bank $bank, Subject $subject): Section
    {
        return Section::updateOrCreate(
            ['sumber_tabel' => self::SUMBER_TRYOUT_MAPEL, 'sumber_id' => $subject->id],
            ['bank_id' => $bank->id] + $this->atributSectionTryout($subject)
        );
    }

    /** @return array<string, mixed> */
    public function atributSectionTryout(Subject $subject): array
    {
        return [
            'nama' => $subject->mataPelajaran?->nama,
            'mata_pelajaran_id' => $subject->mata_pelajaran_id,
            'urutan' => $subject->urutan,
            'bobot' => $subject->bobot,
            'durasi_menit' => $subject->durasi_menit,
            'passing_grade' => $subject->passing_grade_mapel,
        ];
    }

    public function soalTryout(Bank $bank, Section $section, TryoutQuestion $tryoutQuestion): Question
    {
        $question = Question::updateOrCreate(
            ['sumber_tabel' => self::SUMBER_TRYOUT_SOAL, 'sumber_id' => $tryoutQuestion->id],
            ['bank_id' => $bank->id, 'section_id' => $section->id]
                + $this->atributSoalTryout($tryoutQuestion)
        );

        $hurufAktif = [];
        foreach ($this->atributOpsiTryout($tryoutQuestion) as $huruf => $atribut) {
            $hurufAktif[] = (string) $huruf;
            QuestionOption::updateOrCreate(
                ['question_id' => $question->id, 'huruf' => (string) $huruf],
                $atribut
            );
        }

        // Opsi yang dihapus di bank tidak boleh tertinggal di engine.
        QuestionOption::where('question_id', $question->id)->whereNotIn('huruf', $hurufAktif)->delete();

        return $question;
    }

    /** @return array<string, mixed> */
    public function atributSoalTryout(TryoutQuestion $tryoutQuestion): array
    {
        return [
            'urutan' => $tryoutQuestion->urutan,
            'tipe' => $tryoutQuestion->tipe_soal,
            'pertanyaan' => $tryoutQuestion->pertanyaan,
            'media_type' => $tryoutQuestion->media_type ?? 'text',
            'media_url' => $tryoutQuestion->media_url,
            'poin' => $tryoutQuestion->poin,
            'tingkat_kesulitan' => $tryoutQuestion->tingkat_kesulitan,
            'pembahasan' => $tryoutQuestion->pembahasan,
            'aktif' => (bool) $tryoutQuestion->aktif,
        ];
    }

    /**
     * @return array<string, array{isi_opsi: string, adalah_benar: bool, urutan: int}>
     */
    public function atributOpsiTryout(TryoutQuestion $tryoutQuestion): array
    {
        $hasil = [];

        foreach ($tryoutQuestion->options as $option) {
            $hasil[(string) $option->huruf] = [
                'isi_opsi' => $option->isi_opsi,
                'adalah_benar' => (bool) $option->adalah_benar,
                'urutan' => $option->urutan,
            ];
        }

        return $hasil;
    }

    /**
     * Pastikan bank, section, dan soal Tryout ada di engine terpadu.
     * Dipakai CRUD paket/mapel/soal agar data baru langsung terlihat pembaca.
     */
    public function paketTryout(Package $package): Bank
    {
        $bank = $this->bankTryout($package);

        foreach ($package->subjects()->with('mataPelajaran')->get() as $subject) {
            $section = $this->sectionTryout($bank, $subject);
            foreach ($subject->questions as $tryoutQuestion) {
                $this->soalTryout($bank, $section, $tryoutQuestion);
            }
        }

        return $bank;
    }

    /**
     * Pastikan bank, section, soal, dan sesi Tryout ada di engine terpadu.
     * Dipakai alur ujian langsung sehingga sesi yang dibuat SETELAH backfill
     * tetap punya padanan.
     */
    public function pastikanSesiTryout(TryoutSession $tryoutSession): ExamSession
    {
        $bank = $this->paketTryout($tryoutSession->package);

        return ExamSession::updateOrCreate(
            ['sumber_tabel' => self::SUMBER_TRYOUT_SESI, 'sumber_id' => $tryoutSession->id],
            ['bank_id' => $bank->id] + $this->atributSesiTryout($tryoutSession)
        );
    }

    /** @return array<string, mixed> */
    public function atributSesiTryout(TryoutSession $tryoutSession): array
    {
        return [
            'guru_id' => $tryoutSession->guru_id,
            'semester_id' => $tryoutSession->semester_id,
            'rombel_id' => $tryoutSession->rombel_id,
            'kode' => $tryoutSession->kode_sesi,
            'nama' => $tryoutSession->nama_sesi,
            'kelas' => $tryoutSession->kelas,
            'mulai_at' => $tryoutSession->tanggal_mulai,
            'selesai_at' => $tryoutSession->tanggal_selesai,
            'durasi_menit' => $tryoutSession->durasi_menit,
            'status' => $tryoutSession->status,
            'instruksi' => $tryoutSession->instruksi,
        ];
    }

    /**
     * Padanan soal engine terpadu untuk id soal Tryout (id => Question).
     *
     * @return array<int, Question>
     */
    public function petaSoalTryout(): array
    {
        return Question::where('sumber_tabel', self::SUMBER_TRYOUT_SOAL)
            ->with('options')
            ->get()
            ->keyBy(fn (Question $q) => (int) $q->sumber_id)
            ->all();
    }
}
