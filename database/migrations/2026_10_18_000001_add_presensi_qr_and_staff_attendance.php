<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            if (!Schema::hasColumn('siswa', 'qr_token_hash')) {
                // Nilai QR yang tercetak tidak disimpan. Hanya HMAC-nya yang tersimpan.
                $table->string('qr_token_hash', 64)->nullable()->unique();
            }
        });

        Schema::table('kehadiran', function (Blueprint $table) {
            if (!Schema::hasColumn('kehadiran', 'waktu_masuk')) {
                $table->timestamp('waktu_masuk')->nullable();
            }
            if (!Schema::hasColumn('kehadiran', 'jadwal_pelajaran_id')) {
                $table->foreignId('jadwal_pelajaran_id')->nullable()
                    ->constrained('jadwal_pelajaran')->nullOnDelete();
            }
            if (!Schema::hasColumn('kehadiran', 'dicatat_oleh')) {
                $table->foreignId('dicatat_oleh')->nullable()
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('kehadiran', 'metode')) {
                $table->string('metode', 16)->default('manual');
            }
        });

        Schema::create('kehadiran_mapel', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('jadwal_pelajaran_id')->constrained('jadwal_pelajaran')->restrictOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('guru_id')->constrained('users')->restrictOnDelete();
            $table->date('tanggal');
            $table->string('mata_pelajaran', 150);
            $table->string('kelas', 80)->nullable();
            $table->enum('status', ['H', 'S', 'I', 'A'])->default('H');
            $table->timestamp('waktu_hadir')->nullable();
            $table->string('metode', 16)->default('manual');
            $table->string('keterangan', 255)->nullable();
            $table->timestamps();

            $table->unique(['jadwal_pelajaran_id', 'tanggal', 'siswa_id'], 'kehadiran_mapel_jadwal_tanggal_siswa_unique');
            $table->index(['siswa_id', 'tanggal']);
            $table->index(['guru_id', 'tanggal']);
        });

        Schema::create('presensi_lokasi', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_meter')->default(150);
            $table->unsignedInteger('akurasi_maksimal_meter')->default(150);
            $table->boolean('aktif')->default(true);
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('aktif');
        });

        Schema::create('presensi_pegawai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('tanggal');
            $table->string('status', 20)->default('hadir');
            $table->timestamp('waktu_masuk')->nullable();
            $table->decimal('masuk_latitude', 10, 7)->nullable();
            $table->decimal('masuk_longitude', 10, 7)->nullable();
            $table->decimal('masuk_akurasi_meter', 8, 2)->nullable();
            $table->foreignId('masuk_lokasi_id')->nullable()->constrained('presensi_lokasi')->nullOnDelete();
            $table->timestamp('waktu_pulang')->nullable();
            $table->decimal('pulang_latitude', 10, 7)->nullable();
            $table->decimal('pulang_longitude', 10, 7)->nullable();
            $table->decimal('pulang_akurasi_meter', 8, 2)->nullable();
            $table->foreignId('pulang_lokasi_id')->nullable()->constrained('presensi_lokasi')->nullOnDelete();
            $table->string('metode', 16)->default('gps');
            $table->foreignId('diperbarui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->string('alasan_koreksi', 500)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tanggal']);
            $table->index(['tanggal', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presensi_pegawai');
        Schema::dropIfExists('presensi_lokasi');
        Schema::dropIfExists('kehadiran_mapel');

        Schema::table('kehadiran', function (Blueprint $table) {
            foreach (['jadwal_pelajaran_id', 'dicatat_oleh'] as $foreignColumn) {
                if (Schema::hasColumn('kehadiran', $foreignColumn)) {
                    $table->dropForeign([$foreignColumn]);
                }
            }
            $columns = ['waktu_masuk', 'jadwal_pelajaran_id', 'dicatat_oleh', 'metode'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('kehadiran', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('siswa', function (Blueprint $table) {
            if (Schema::hasColumn('siswa', 'qr_token_hash')) {
                $table->dropUnique(['qr_token_hash']);
                $table->dropColumn('qr_token_hash');
            }
        });
    }
};
