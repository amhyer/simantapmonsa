<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Gelombang 6: Perangkat Pembelajaran (CP, TP, ATP, Prota, Promes,
 * Modul Ajar/RPP, KKM/KKTP) dan Jurnal Mengajar Harian guru.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('perangkat_pembelajaran')) {
            Schema::create('perangkat_pembelajaran', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('guru_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('rombel_id')->nullable()->constrained('rombel')->nullOnDelete();
                $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
                $table->foreignId('kelas_digital_id')->nullable()->constrained('kelas_digital')->nullOnDelete();
                $table->string('jenis', 30); // cp|tp|atp|prota|promes|modul_ajar|kkm
                $table->string('judul', 220);
                $table->string('mata_pelajaran', 120);
                $table->string('fase', 10)->nullable(); // A|B|C|D|E|F
                $table->string('kelas', 60);
                $table->string('tahun_ajaran', 30)->nullable();
                $table->string('semester', 20)->nullable(); // Ganjil|Genap|Tahunan
                $table->string('alokasi_waktu', 80)->nullable();
                $table->text('ringkasan')->nullable();
                $table->json('konten')->nullable();
                $table->string('lampiran_path', 500)->nullable();
                $table->string('lampiran_nama', 255)->nullable();
                $table->string('lampiran_mime', 120)->nullable();
                $table->unsignedBigInteger('lampiran_ukuran')->nullable();
                $table->string('status', 20)->default('final'); // draf|final
                $table->boolean('dibagikan_ke_walas')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'guru_id', 'jenis'], 'pp_tenant_guru_jenis_idx');
                $table->index(['tenant_id', 'kelas'], 'pp_tenant_kelas_idx');
                $table->index('rombel_id', 'pp_rombel_idx');
            });
        }

        if (! Schema::hasTable('jurnal_mengajar')) {
            Schema::create('jurnal_mengajar', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('guru_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('rombel_id')->nullable()->constrained('rombel')->nullOnDelete();
                $table->foreignId('perangkat_id')->nullable()->constrained('perangkat_pembelajaran')->nullOnDelete();
                $table->foreignId('kelas_digital_id')->nullable()->constrained('kelas_digital')->nullOnDelete();
                $table->date('tanggal');
                $table->string('jam_ke', 30)->nullable();
                $table->string('kelas', 60);
                $table->string('mata_pelajaran', 120);
                $table->unsignedSmallInteger('pertemuan_ke')->nullable();
                $table->string('topik', 220);
                $table->text('kegiatan');
                $table->unsignedSmallInteger('jumlah_hadir')->nullable();
                $table->unsignedSmallInteger('jumlah_tidak_hadir')->nullable();
                $table->text('catatan')->nullable();
                $table->text('tindak_lanjut')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'guru_id', 'tanggal'], 'jm_tenant_guru_tgl_idx');
                $table->index('rombel_id', 'jm_rombel_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('jurnal_mengajar');
        Schema::dropIfExists('perangkat_pembelajaran');
    }
};
