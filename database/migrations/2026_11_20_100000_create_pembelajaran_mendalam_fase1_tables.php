<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_alokasi_waktu', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('mata_pelajaran_id')->nullable()->index();
            $table->string('nama_mapel', 120);
            $table->unsignedTinyInteger('tingkat'); // 1..6
            $table->unsignedSmallInteger('jp_intra')->default(0);
            $table->unsignedSmallInteger('jp_kokurikuler')->default(0);
            $table->string('tahun_ajaran', 20)->default('2026/2027');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'nama_mapel', 'tingkat', 'tahun_ajaran'], 'pm_alokasi_unique');
        });

        Schema::create('pm_capaian_pembelajaran', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('guru_id')->nullable()->index();
            $table->unsignedBigInteger('mata_pelajaran_id')->nullable()->index();
            $table->string('nama_mapel', 120)->index();
            $table->string('fase', 4)->index(); // A, B, C, D, E, F
            $table->string('elemen', 150)->nullable();
            $table->text('deskripsi');
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->string('sumber', 40)->default('manual'); // manual, ai, bskap_2025, import
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pm_tujuan_pembelajaran', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('guru_id')->nullable()->index();
            $table->foreignId('cp_id')->nullable()->constrained('pm_capaian_pembelajaran')->nullOnDelete();
            $table->unsignedBigInteger('mata_pelajaran_id')->nullable()->index();
            $table->string('nama_mapel', 120)->index();
            $table->string('fase', 4)->index();
            $table->string('elemen', 150)->nullable();
            $table->string('kode_tp', 40)->nullable();
            $table->text('deskripsi');
            $table->unsignedSmallInteger('urutan')->default(1);
            // Kolom ATP (Alur Tujuan Pembelajaran)
            $table->unsignedTinyInteger('kelas_target')->nullable()->index(); // 1..6
            $table->unsignedTinyInteger('semester_target')->nullable()->index(); // 1 atau 2
            $table->string('materi_pokok', 255)->nullable();
            $table->unsignedSmallInteger('alokasi_jp')->default(0);
            $table->json('dimensi_profil')->nullable();
            $table->string('sumber', 40)->default('manual');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pm_buku_paket', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('guru_id')->nullable()->index();
            $table->unsignedBigInteger('mata_pelajaran_id')->nullable()->index();
            $table->string('nama_mapel', 120);
            $table->unsignedTinyInteger('tingkat'); // 1..6
            $table->string('judul', 255);
            $table->string('penulis', 200)->nullable();
            $table->string('tautan_sibi', 500)->nullable();
            $table->text('daftar_isi')->nullable(); // satu bab per baris
            $table->timestamps();

            $table->unique(['tenant_id', 'nama_mapel', 'tingkat'], 'pm_buku_paket_unique');
        });

        Schema::create('pm_kktp_interval', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('mata_pelajaran_id')->nullable()->index();
            $table->string('nama_mapel', 120);
            $table->unsignedTinyInteger('tingkat'); // 1..6
            $table->unsignedTinyInteger('nilai_kktp')->default(75);
            $table->unsignedTinyInteger('batas_bawah')->default(60);
            $table->unsignedTinyInteger('batas_atas')->default(88);
            $table->string('ket_baru_berkembang', 255)->default('Belum mencapai ketuntasan, remedial di seluruh bagian');
            $table->string('ket_layak', 255)->default('Belum mencapai ketuntasan, remedial di bagian yang diperlukan');
            $table->string('ket_cakap', 255)->default('Sudah mencapai ketuntasan, tidak perlu remedial');
            $table->string('ket_mahir', 255)->default('Sudah mencapai ketuntasan, perlu pengayaan atau tantangan lebih');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'nama_mapel', 'tingkat'], 'pm_kktp_interval_unique');
        });

        Schema::create('pm_kktp_item', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('guru_id')->nullable()->index();
            $table->foreignId('tp_id')->constrained('pm_tujuan_pembelajaran')->cascadeOnDelete();
            $table->string('pendekatan', 30)->default('deskripsi'); // deskripsi, rubrik, interval
            $table->text('kriteria')->nullable(); // satu kriteria per baris
            $table->text('rubrik')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'tp_id'], 'pm_kktp_item_unique');
        });

        Schema::create('pm_ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('fitur', 50);
            $table->string('model', 80)->nullable();
            $table->string('status', 20)->default('ok');
            $table->date('tanggal')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_ai_usage_logs');
        Schema::dropIfExists('pm_kktp_item');
        Schema::dropIfExists('pm_kktp_interval');
        Schema::dropIfExists('pm_buku_paket');
        Schema::dropIfExists('pm_tujuan_pembelajaran');
        Schema::dropIfExists('pm_capaian_pembelajaran');
        Schema::dropIfExists('pm_alokasi_waktu');
    }
};
