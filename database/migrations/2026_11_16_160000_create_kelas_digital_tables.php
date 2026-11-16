<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul E-Learning / Kelas Digital (Gelombang 5).
 *
 * Evolusi terstruktur dari `materi` yang memakai ulang `kuis` dan `nilai`
 * eksisting, dengan isolasi multi-tenant (`tenant_id`), `uuid`, dan `SoftDeletes`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kelas_digital')) {
            Schema::create('kelas_digital', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('guru_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('rombel_id')->nullable()->constrained('rombel')->nullOnDelete();
                $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
                $table->string('nama_kelas', 180);
                $table->string('mata_pelajaran', 120);
                $table->string('kelas', 60);
                $table->string('kode_kelas', 24)->nullable();
                $table->text('deskripsi')->nullable();
                $table->boolean('aktif')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'guru_id'], 'kd_tenant_guru_idx');
                $table->index(['tenant_id', 'kelas'], 'kd_tenant_kelas_idx');
                $table->index('rombel_id', 'kd_rombel_idx');
            });
        }

        if (! Schema::hasTable('kelas_digital_modul')) {
            Schema::create('kelas_digital_modul', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('kelas_digital_id')->constrained('kelas_digital')->cascadeOnDelete();
                $table->string('judul', 200);
                $table->text('deskripsi')->nullable();
                $table->unsignedInteger('urutan')->default(1);
                $table->text('tujuan_pembelajaran')->nullable();
                $table->json('pm')->nullable();
                $table->json('dimensi')->nullable();
                $table->json('pengalaman')->nullable();
                $table->boolean('dipublikasikan')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['kelas_digital_id', 'urutan'], 'kd_modul_kelas_urutan_idx');
                $table->index('tenant_id', 'kd_modul_tenant_idx');
            });
        }

        if (! Schema::hasTable('kelas_digital_pertemuan')) {
            Schema::create('kelas_digital_pertemuan', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('modul_id')->constrained('kelas_digital_modul')->cascadeOnDelete();
                $table->foreignId('materi_id')->nullable()->constrained('materi')->nullOnDelete();
                $table->foreignId('kuis_id')->nullable()->constrained('kuis')->nullOnDelete();
                $table->string('judul', 200);
                $table->text('deskripsi')->nullable();
                $table->date('tanggal')->nullable();
                $table->unsignedInteger('urutan')->default(1);
                $table->boolean('dipublikasikan')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['modul_id', 'urutan'], 'kd_pertemuan_modul_urutan_idx');
                $table->index('tenant_id', 'kd_pertemuan_tenant_idx');
            });
        }

        if (! Schema::hasTable('kelas_digital_materi_item')) {
            Schema::create('kelas_digital_materi_item', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('pertemuan_id')->constrained('kelas_digital_pertemuan')->cascadeOnDelete();
                $table->string('tipe', 20); // teks|video|file|link
                $table->string('judul', 200);
                $table->text('konten')->nullable();
                $table->string('tautan', 1000)->nullable();
                $table->string('file_path', 500)->nullable();
                $table->string('file_nama_asli', 255)->nullable();
                $table->string('file_mime', 120)->nullable();
                $table->unsignedBigInteger('file_ukuran')->nullable();
                $table->unsignedInteger('urutan')->default(1);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['pertemuan_id', 'urutan'], 'kd_item_pertemuan_urutan_idx');
                $table->index('tenant_id', 'kd_item_tenant_idx');
            });
        }

        if (! Schema::hasTable('kelas_digital_tugas')) {
            Schema::create('kelas_digital_tugas', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('pertemuan_id')->constrained('kelas_digital_pertemuan')->cascadeOnDelete();
                $table->foreignId('kelas_digital_id')->constrained('kelas_digital')->cascadeOnDelete();
                $table->foreignId('guru_id')->constrained('users')->cascadeOnDelete();
                $table->string('judul', 200);
                $table->text('instruksi');
                $table->string('tipe_pengumpulan', 20)->default('keduanya'); // teks|file|keduanya
                $table->timestamp('batas_waktu')->nullable();
                $table->unsignedSmallInteger('nilai_maksimal')->default(100);
                $table->string('lampiran_path', 500)->nullable();
                $table->string('lampiran_nama', 255)->nullable();
                $table->string('lampiran_mime', 120)->nullable();
                $table->unsignedBigInteger('lampiran_ukuran')->nullable();
                $table->boolean('sinkron_ke_nilai')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['kelas_digital_id', 'pertemuan_id'], 'kd_tugas_kelas_pertemuan_idx');
                $table->index(['tenant_id', 'guru_id'], 'kd_tugas_tenant_guru_idx');
            });
        }

        if (! Schema::hasTable('kelas_digital_pengumpulan')) {
            Schema::create('kelas_digital_pengumpulan', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('tugas_id')->constrained('kelas_digital_tugas')->cascadeOnDelete();
                $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
                $table->text('jawaban_teks')->nullable();
                $table->string('file_path', 500)->nullable();
                $table->string('file_nama_asli', 255)->nullable();
                $table->string('file_mime', 120)->nullable();
                $table->unsignedBigInteger('file_ukuran')->nullable();
                $table->string('status', 20)->default('dikumpulkan'); // dikumpulkan|terlambat|dinilai|revisi
                $table->timestamp('dikumpulkan_pada')->nullable();
                $table->decimal('nilai', 5, 2)->nullable();
                $table->text('catatan_guru')->nullable();
                $table->foreignId('dinilai_oleh')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('dinilai_pada')->nullable();
                $table->foreignId('nilai_id')->nullable()->constrained('nilai')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tugas_id', 'siswa_id'], 'kd_pengumpulan_tugas_siswa_unique');
                $table->index(['tenant_id', 'siswa_id'], 'kd_pengumpulan_tenant_siswa_idx');
            });
        }

        if (! Schema::hasTable('kelas_digital_progress')) {
            Schema::create('kelas_digital_progress', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('pertemuan_id')->constrained('kelas_digital_pertemuan')->cascadeOnDelete();
                $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
                $table->boolean('selesai')->default(true);
                $table->timestamp('selesai_pada')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['pertemuan_id', 'siswa_id'], 'kd_progress_pertemuan_siswa_unique');
                $table->index(['tenant_id', 'siswa_id'], 'kd_progress_tenant_siswa_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas_digital_progress');
        Schema::dropIfExists('kelas_digital_pengumpulan');
        Schema::dropIfExists('kelas_digital_tugas');
        Schema::dropIfExists('kelas_digital_materi_item');
        Schema::dropIfExists('kelas_digital_pertemuan');
        Schema::dropIfExists('kelas_digital_modul');
        Schema::dropIfExists('kelas_digital');
    }
};
