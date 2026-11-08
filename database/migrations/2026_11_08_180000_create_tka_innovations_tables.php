<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tka_adaptive_simulations')) {
            Schema::create('tka_adaptive_simulations', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
                $table->string('paket_kode', 40);
                $table->string('paket_judul', 180);
                $table->string('mode_simulasi', 30)->default('adaptif');
                $table->string('tingkat_awal', 20)->default('sedang');
                $table->string('tingkat_akhir', 20)->nullable();
                $table->unsignedSmallInteger('target_soal')->default(6);
                $table->unsignedSmallInteger('total_soal')->default(0);
                $table->unsignedSmallInteger('benar')->default(0);
                $table->decimal('skor_akhir', 5, 2)->default(0);
                $table->string('level_capaian', 50)->nullable();
                $table->unsignedInteger('durasi_detik')->default(0);
                $table->json('jejak_adaptif')->nullable();
                $table->json('skor_indikator')->nullable();
                $table->string('status', 20)->default('berlangsung');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('dibaca_ortu_at')->nullable();
                $table->foreignId('dibaca_oleh_ortu_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('catatan_ortu')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'siswa_id', 'status']);
                $table->index(['siswa_id', 'submitted_at']);
            });
        }

        if (! Schema::hasTable('tka_learning_recommendations')) {
            Schema::create('tka_learning_recommendations', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
                $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
                $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
                $table->string('zona_kemampuan', 30)->default('rata_rata');
                $table->decimal('skor_kognitif', 5, 2)->default(0);
                $table->decimal('rata_rata_pembanding', 5, 2)->default(0);
                $table->json('indikator_lemah')->nullable();
                $table->json('indikator_kuat')->nullable();
                $table->json('paket_tindak_lanjut')->nullable();
                $table->string('status_tindak_lanjut', 25)->default('direkomendasikan');
                $table->text('catatan_guru')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'siswa_id']);
                $table->index(['tenant_id', 'zona_kemampuan']);
            });
        }

        if (! Schema::hasTable('tka_erapor_sync_logs')) {
            Schema::create('tka_erapor_sync_logs', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
                $table->foreignId('guru_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
                $table->foreignId('assessment_id')->nullable()->constrained('tka_student_assessments')->nullOnDelete();
                $table->foreignId('nilai_id')->nullable()->constrained('nilai')->nullOnDelete();
                $table->foreignId('nilai_erapor_id')->nullable()->constrained('nilai_erapor')->nullOnDelete();
                $table->string('mata_pelajaran', 120);
                $table->string('komponen_target', 30)->default('sumatif');
                $table->decimal('skor_tka_konversi', 5, 2)->default(0);
                $table->decimal('nilai_akhir_erapor', 5, 2)->nullable();
                $table->json('ringkasan_sumber')->nullable();
                $table->timestamp('synced_at');
                $table->timestamps();

                $table->index(['tenant_id', 'siswa_id', 'mata_pelajaran']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tka_erapor_sync_logs');
        Schema::dropIfExists('tka_learning_recommendations');
        Schema::dropIfExists('tka_adaptive_simulations');
    }
};
