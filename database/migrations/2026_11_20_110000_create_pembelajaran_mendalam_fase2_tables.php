<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_kalender_pendidikan', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('tahun_ajaran', 20)->index();
            $table->unsignedTinyInteger('semester'); // 1 (Jul-Des) atau 2 (Jan-Jun)
            $table->unsignedTinyInteger('bulan'); // 1..12
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('jumlah_minggu')->default(4);
            $table->unsignedTinyInteger('minggu_tidak_efektif')->default(0);
            $table->unsignedTinyInteger('hari_efektif')->default(20);
            $table->json('minggu_libur')->nullable(); // contoh: [1, 2]
            $table->string('keterangan', 255)->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'tahun_ajaran', 'semester', 'bulan'], 'pm_kalpend_unique');
        });

        Schema::create('pm_hari_libur', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('tahun_ajaran', 20)->index();
            $table->date('tanggal_mulai')->index();
            $table->date('tanggal_selesai');
            $table->string('nama', 160);
            $table->string('jenis', 40)->default('libur_nasional');
            $table->string('keterangan', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('pm_prota', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('guru_id')->nullable()->index();
            $table->string('tahun_ajaran', 20)->index();
            $table->unsignedTinyInteger('tingkat')->index(); // 1..6
            $table->string('nama_mapel', 120)->index();
            $table->unsignedTinyInteger('semester')->index(); // 1 atau 2
            $table->foreignId('tp_id')->nullable()->constrained('pm_tujuan_pembelajaran')->nullOnDelete();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->string('materi', 255);
            $table->unsignedSmallInteger('alokasi_jp')->default(0);
            $table->string('keterangan', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('pm_prosem', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('guru_id')->nullable()->index();
            $table->string('tahun_ajaran', 20)->index();
            $table->unsignedTinyInteger('tingkat')->index(); // 1..6
            $table->string('nama_mapel', 120)->index();
            $table->unsignedTinyInteger('semester')->index(); // 1 atau 2
            $table->foreignId('prota_id')->nullable()->constrained('pm_prota')->nullOnDelete();
            $table->foreignId('tp_id')->nullable()->constrained('pm_tujuan_pembelajaran')->nullOnDelete();
            $table->string('bab', 120)->nullable();
            $table->string('materi', 255);
            $table->unsignedTinyInteger('bulan'); // 1..12
            $table->unsignedTinyInteger('minggu_ke'); // 1..5
            $table->unsignedSmallInteger('alokasi_jp')->default(0);
            $table->string('keterangan', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_prosem');
        Schema::dropIfExists('pm_prota');
        Schema::dropIfExists('pm_hari_libur');
        Schema::dropIfExists('pm_kalender_pendidikan');
    }
};
