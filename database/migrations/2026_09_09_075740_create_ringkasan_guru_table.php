<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ringkasan_guru', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->constrained('users')->onDelete('cascade');
            $table->string('nama_guru', 255);
            $table->string('mata_pelajaran', 100)->nullable();
            $table->string('kelas', 50)->nullable();
            $table->integer('kkm')->default(70);
            $table->string('semester', 10)->nullable();
            $table->string('tahun_pelajaran', 20)->nullable();
            $table->integer('jumlah_siswa')->default(0);
            $table->decimal('rata_rata', 5, 2)->default(0);
            $table->integer('tuntas')->default(0);
            $table->integer('belum_tuntas')->default(0);
            $table->decimal('ketuntasan_persen', 5, 2)->default(0);
            $table->decimal('kehadiran_persen', 5, 2)->default(0);
            $table->decimal('rata_tugas', 5, 2)->default(0);
            $table->decimal('rata_ulangan_harian', 5, 2)->default(0);
            $table->decimal('rata_praktik', 5, 2)->default(0);
            $table->decimal('rata_pts', 5, 2)->default(0);
            $table->decimal('rata_pas', 5, 2)->default(0);
            $table->integer('predikat_a')->default(0);
            $table->integer('predikat_b')->default(0);
            $table->integer('predikat_c')->default(0);
            $table->integer('predikat_d')->default(0);
            $table->integer('belum_dinilai')->default(0);
            $table->integer('hadir')->default(0);
            $table->integer('sakit')->default(0);
            $table->integer('izin')->default(0);
            $table->integer('alpa')->default(0);
            $table->json('dimensi')->nullable();
            $table->json('kaih')->nullable();
            $table->json('kegiatan')->nullable();
            $table->timestamp('pembaruan')->nullable();
            $table->timestamps();
            
            $table->unique('guru_id');
            $table->index('guru_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ringkasan_guru');
    }
};
