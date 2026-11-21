<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambahkan role 'pengawas' pada constraint enum users.peran
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_peran_check');
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_peran_check CHECK (peran IN ('super_admin', 'admin', 'guru', 'siswa', 'ortu', 'kepsek', 'tendik', 'pengawas'))");
        } elseif (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE users MODIFY peran ENUM('super_admin', 'admin', 'guru', 'siswa', 'ortu', 'kepsek', 'tendik', 'pengawas') NOT NULL DEFAULT 'guru'");
        }

        // 2. Kolom Verifikasi Berjenjang Pengawas pada tabel pm_supervisi (Guru -> Kepsek -> Pengawas)
        if (Schema::hasTable('pm_supervisi')) {
            Schema::table('pm_supervisi', function (Blueprint $table) {
                if (! Schema::hasColumn('pm_supervisi', 'pengawas_id')) {
                    $table->unsignedBigInteger('pengawas_id')->nullable()->after('kepsek_id')->index();
                }
                if (! Schema::hasColumn('pm_supervisi', 'status_pengawas')) {
                    $table->string('status_pengawas', 30)->default('menunggu')->after('disetujui_at')->index();
                }
                if (! Schema::hasColumn('pm_supervisi', 'catatan_pengawas')) {
                    $table->text('catatan_pengawas')->nullable()->after('status_pengawas');
                }
                if (! Schema::hasColumn('pm_supervisi', 'rekomendasi_rtl')) {
                    $table->text('rekomendasi_rtl')->nullable()->after('catatan_pengawas');
                }
                if (! Schema::hasColumn('pm_supervisi', 'diverifikasi_pengawas_at')) {
                    $table->timestamp('diverifikasi_pengawas_at')->nullable()->after('rekomendasi_rtl');
                }
            });
        }

        // 3. Tabel E-Visitasi & Instrumen Observasi Kelas Pembelajaran Mendalam oleh Pengawas Sekolah
        if (! Schema::hasTable('pm_visitasi_pengawas')) {
            Schema::create('pm_visitasi_pengawas', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('pengawas_id')->index();
                $table->unsignedBigInteger('guru_id')->nullable()->index();
                $table->string('tahun_ajaran', 20)->index();
                $table->unsignedTinyInteger('semester')->default(1);
                $table->date('tanggal_visitasi')->index();
                $table->string('jenis_pendampingan', 60)->default('supervisi_akademik');
                // Rubrik Observasi Pembelajaran Mendalam (Skala 1-4)
                $table->unsignedTinyInteger('skor_berkesadaran')->default(3);
                $table->unsignedTinyInteger('skor_bermakna')->default(3);
                $table->unsignedTinyInteger('skor_menggembirakan')->default(3);
                $table->unsignedTinyInteger('skor_memahami')->default(3);
                $table->unsignedTinyInteger('skor_mengaplikasi')->default(3);
                $table->unsignedTinyInteger('skor_merefleksi')->default(3);
                $table->text('temuan')->nullable();
                $table->text('rekomendasi')->nullable();
                $table->text('tindak_lanjut')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_visitasi_pengawas');

        if (Schema::hasTable('pm_supervisi')) {
            Schema::table('pm_supervisi', function (Blueprint $table) {
                $cols = array_filter(
                    ['pengawas_id', 'status_pengawas', 'catatan_pengawas', 'rekomendasi_rtl', 'diverifikasi_pengawas_at'],
                    fn (string $c) => Schema::hasColumn('pm_supervisi', $c)
                );
                if (! empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
