<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('nama_pengguna', 'users_nama_pengguna_idx');
        });

        Schema::table('siswa', function (Blueprint $table) {
            $table->index('guru_id', 'siswa_guru_id_idx');
            $table->index('kelas', 'siswa_kelas_idx');
            $table->index('nis', 'siswa_nis_idx');
        });

        Schema::table('nilai', function (Blueprint $table) {
            $table->index('siswa_id', 'nilai_siswa_id_idx');
            $table->index('guru_id', 'nilai_guru_id_idx');
            $table->index('kuis_id', 'nilai_kuis_id_idx');
        });

        Schema::table('kehadiran', function (Blueprint $table) {
            $table->index('siswa_id', 'kehadiran_siswa_id_idx');
            $table->index('guru_id', 'kehadiran_guru_id_idx');
        });

        Schema::table('hasil_kuis', function (Blueprint $table) {
            $table->index('siswa_id', 'hasil_kuis_siswa_id_idx');
            $table->index('kuis_id', 'hasil_kuis_kuis_id_idx');
            $table->index('guru_id', 'hasil_kuis_guru_id_idx');
        });

        Schema::table('kuis', function (Blueprint $table) {
            $table->index('guru_id', 'kuis_guru_id_idx');
        });

        Schema::table('materi', function (Blueprint $table) {
            $table->index('guru_id', 'materi_guru_id_idx');
        });

        Schema::table('catatan', function (Blueprint $table) {
            $table->index('siswa_id', 'catatan_siswa_id_idx');
            $table->index('guru_id', 'catatan_guru_id_idx');
        });

        Schema::table('dimensi', function (Blueprint $table) {
            $table->index('siswa_id', 'dimensi_siswa_id_idx');
        });

        Schema::table('kebiasaan', function (Blueprint $table) {
            $table->index('siswa_id', 'kebiasaan_siswa_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_nama_pengguna_idx');
        });

        Schema::table('siswa', function (Blueprint $table) {
            $table->dropIndex('siswa_guru_id_idx');
            $table->dropIndex('siswa_kelas_idx');
            $table->dropIndex('siswa_nis_idx');
        });

        Schema::table('nilai', function (Blueprint $table) {
            $table->dropIndex('nilai_siswa_id_idx');
            $table->dropIndex('nilai_guru_id_idx');
            $table->dropIndex('nilai_kuis_id_idx');
        });

        Schema::table('kehadiran', function (Blueprint $table) {
            $table->dropIndex('kehadiran_siswa_id_idx');
            $table->dropIndex('kehadiran_guru_id_idx');
        });

        Schema::table('hasil_kuis', function (Blueprint $table) {
            $table->dropIndex('hasil_kuis_siswa_id_idx');
            $table->dropIndex('hasil_kuis_kuis_id_idx');
            $table->dropIndex('hasil_kuis_guru_id_idx');
        });

        Schema::table('kuis', function (Blueprint $table) {
            $table->dropIndex('kuis_guru_id_idx');
        });

        Schema::table('materi', function (Blueprint $table) {
            $table->dropIndex('materi_guru_id_idx');
        });

        Schema::table('catatan', function (Blueprint $table) {
            $table->dropIndex('catatan_siswa_id_idx');
            $table->dropIndex('catatan_guru_id_idx');
        });

        Schema::table('dimensi', function (Blueprint $table) {
            $table->dropIndex('dimensi_siswa_id_idx');
        });

        Schema::table('kebiasaan', function (Blueprint $table) {
            $table->dropIndex('kebiasaan_siswa_id_idx');
        });
    }
};
