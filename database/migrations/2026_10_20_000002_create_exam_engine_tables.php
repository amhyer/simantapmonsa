<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 (fondasi): skema engine asesmen terpadu.
 *
 * Tabel `cbt_*` dan `tryout_*` TIDAK disentuh pada langkah ini; backfill
 * (`php artisan exam:backfill`) menyalin datanya ke sini. Penghapusan tabel
 * lama hanya dilakukan setelah rute/tampilan dialihkan dan parity terbukti.
 *
 * Pemetaan konsep:
 *   CBT    : question_bank -> session -> result(+answers JSON)
 *   Tryout : package -> subject -> question(option) -> student_answer -> result
 *   Terpadu: engine -> bank -> section -> question/option -> session
 *            -> attempt -> answer / result
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exam_engines')) {
            Schema::create('exam_engines', function (Blueprint $table) {
                $table->id();
                $table->string('kode', 30)->unique();       // cbt | tryout
                $table->string('nama', 100);
                $table->string('jenis', 30)->default('cbt'); // cbt | tryout
                $table->text('deskripsi')->nullable();
                $table->boolean('aktif')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('exam_banks')) {
            Schema::create('exam_banks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('engine_id')->constrained('exam_engines')->cascadeOnDelete();
                $table->foreignId('guru_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('kode')->nullable()->unique();
                $table->string('nama');
                $table->string('mata_pelajaran')->nullable();
                // Asal data untuk audit/backfill: cbt_question_banks:<id> / tryout_packages:<id>
                $table->string('sumber_tabel', 60)->nullable();
                $table->unsignedBigInteger('sumber_id')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['engine_id', 'guru_id']);
                $table->index(['sumber_tabel', 'sumber_id']);
            });
        }

        if (! Schema::hasTable('exam_sections')) {
            // Hanya dipakai engine yang berjenjang (Tryout: paket -> mapel).
            Schema::create('exam_sections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bank_id')->constrained('exam_banks')->cascadeOnDelete();
                $table->string('nama')->nullable();
                $table->foreignId('mata_pelajaran_id')->nullable()->constrained('mata_pelajaran')->nullOnDelete();
                $table->unsignedInteger('urutan')->default(0);
                $table->decimal('bobot', 6, 3)->default(1);
                $table->unsignedInteger('durasi_menit')->nullable();
                $table->decimal('passing_grade', 5, 2)->nullable();
                $table->string('sumber_tabel', 60)->nullable();
                $table->unsignedBigInteger('sumber_id')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['bank_id', 'urutan']);
                $table->index(['sumber_tabel', 'sumber_id']);
            });
        }

        if (! Schema::hasTable('exam_questions')) {
            Schema::create('exam_questions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bank_id')->constrained('exam_banks')->cascadeOnDelete();
                $table->foreignId('section_id')->nullable()->constrained('exam_sections')->nullOnDelete();
                $table->unsignedInteger('urutan')->default(0);
                $table->string('tipe', 30)->default('pilihan_ganda');
                $table->text('pertanyaan');
                $table->string('media_type', 20)->default('text');
                $table->string('media_url')->nullable();
                $table->decimal('poin', 8, 2)->default(1);
                $table->string('tingkat_kesulitan', 20)->nullable();
                $table->text('pembahasan')->nullable();
                // Kunci untuk tipe non-pilihan-ganda (benar_salah/isian_singkat).
                $table->text('kunci_jawaban')->nullable();
                $table->boolean('aktif')->default(true);
                $table->string('sumber_tabel', 60)->nullable();
                $table->unsignedBigInteger('sumber_id')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['bank_id', 'urutan']);
                $table->index(['section_id', 'urutan']);
                $table->index(['sumber_tabel', 'sumber_id']);
            });
        }

        if (! Schema::hasTable('exam_question_options')) {
            Schema::create('exam_question_options', function (Blueprint $table) {
                $table->id();
                $table->foreignId('question_id')->constrained('exam_questions')->cascadeOnDelete();
                $table->string('huruf', 5);
                $table->text('isi_opsi');
                $table->boolean('adalah_benar')->default(false);
                $table->unsignedInteger('urutan')->default(0);
                $table->timestamps();

                $table->unique(['question_id', 'huruf']);
                $table->index('question_id');
            });
        }

        if (! Schema::hasTable('exam_sessions')) {
            Schema::create('exam_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bank_id')->constrained('exam_banks')->cascadeOnDelete();
                $table->foreignId('guru_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
                $table->foreignId('rombel_id')->nullable()->constrained('rombel')->nullOnDelete();
                $table->string('kode')->nullable()->unique();
                $table->string('nama');
                $table->string('kelas')->nullable();
                $table->dateTime('mulai_at')->nullable();
                $table->dateTime('selesai_at')->nullable();
                $table->unsignedInteger('durasi_menit')->nullable();
                $table->string('status', 20)->default('draft');
                // Snapshot soal saat publish (paritas dengan CBT question_snapshot).
                $table->json('soal_snapshot')->nullable();
                $table->json('peserta_ids')->nullable();
                $table->text('instruksi')->nullable();
                $table->string('sumber_tabel', 60)->nullable();
                $table->unsignedBigInteger('sumber_id')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['bank_id', 'status']);
                $table->index(['guru_id', 'status']);
                $table->index(['sumber_tabel', 'sumber_id']);
            });
        }

        if (! Schema::hasTable('exam_attempts')) {
            Schema::create('exam_attempts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('session_id')->constrained('exam_sessions')->cascadeOnDelete();
                $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedInteger('attempt_number')->default(1);
                $table->dateTime('mulai_at');
                $table->dateTime('deadline_at')->nullable();
                $table->dateTime('selesai_at')->nullable();
                $table->string('status', 20)->default('berjalan'); // berjalan | dikumpulkan | dinilai
                $table->decimal('nilai_total', 8, 2)->nullable();
                $table->decimal('nilai_maksimal', 8, 2)->nullable();
                $table->string('sumber_tabel', 60)->nullable();
                $table->unsignedBigInteger('sumber_id')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['session_id', 'siswa_id', 'attempt_number']);
                $table->index(['session_id', 'status']);
                $table->index(['sumber_tabel', 'sumber_id']);
            });
        }

        if (! Schema::hasTable('exam_answers')) {
            Schema::create('exam_answers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
                $table->foreignId('question_id')->constrained('exam_questions')->cascadeOnDelete();
                $table->text('jawaban')->nullable();
                $table->boolean('adalah_benar')->nullable(); // NULL = belum dinilai
                $table->decimal('poin_diperoleh', 8, 2)->default(0);
                $table->dateTime('dijawab_at')->nullable();
                $table->timestamps();

                $table->unique(['attempt_id', 'question_id']);
                $table->index('question_id');
            });
        }

        if (! Schema::hasTable('exam_results')) {
            Schema::create('exam_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
                $table->decimal('nilai_total', 8, 2)->default(0);
                $table->decimal('nilai_maksimal', 8, 2)->default(0);
                $table->decimal('persentase', 6, 2)->default(0);
                $table->unsignedInteger('peringkat')->nullable();
                $table->boolean('lulus')->nullable();
                $table->json('detail_per_section')->nullable();
                $table->json('analisis_kelemahan')->nullable();
                $table->text('rekomendasi')->nullable();
                $table->string('sumber_tabel', 60)->nullable();
                $table->unsignedBigInteger('sumber_id')->nullable();
                $table->timestamps();

                $table->unique('attempt_id');
                $table->index(['sumber_tabel', 'sumber_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_results');
        Schema::dropIfExists('exam_answers');
        Schema::dropIfExists('exam_attempts');
        Schema::dropIfExists('exam_sessions');
        Schema::dropIfExists('exam_question_options');
        Schema::dropIfExists('exam_questions');
        Schema::dropIfExists('exam_sections');
        Schema::dropIfExists('exam_banks');
        Schema::dropIfExists('exam_engines');
    }
};
