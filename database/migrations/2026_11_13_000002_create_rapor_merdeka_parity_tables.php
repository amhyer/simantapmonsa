<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kenaikan_kelas')) {
            Schema::create('kenaikan_kelas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
                $table->string('tahun_ajaran', 9);
                $table->string('semester', 10)->default('Genap');
                $table->boolean('naik_kelas')->default(true);
                $table->string('catatan', 255)->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->unique(['siswa_id', 'tahun_ajaran', 'semester'], 'kenaikan_kelas_siswa_periode_unique');
            });
        }

        if (! Schema::hasTable('siswa_belajar')) {
            Schema::create('siswa_belajar', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
                $table->string('mata_pelajaran', 120);
                $table->boolean('aktif')->default(true);
                $table->timestamps();

                $table->unique(['siswa_id', 'mata_pelajaran'], 'siswa_belajar_siswa_mapel_unique');
            });
        }

        if (! Schema::hasTable('p5_penilaian')) {
            Schema::create('p5_penilaian', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
                $table->string('tema', 150);
                $table->string('dimensi', 150);
                $table->string('predikat', 10)->default('BSH');
                $table->text('catatan')->nullable();
                $table->string('semester', 10)->default('Ganjil');
                $table->string('tahun_ajaran', 9);
                $table->timestamps();

                $table->unique(['siswa_id', 'tema', 'dimensi', 'semester', 'tahun_ajaran'], 'p5_penilaian_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('p5_penilaian');
        Schema::dropIfExists('siswa_belajar');
        Schema::dropIfExists('kenaikan_kelas');
    }
};
