<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PERBAIKAN BUG: kolom `nilai_erapor.jadwal_pelajaran_id` dipakai kode
     * (`NilaiErapot::$fillable`, relasi `jadwalPelajaran()`,
     * `Guru\NilaiErapotController`, dan `JadwalPelajaranResolver`) tetapi
     * TIDAK PERNAH dibuat oleh migrasi mana pun — sehingga penyimpanan nilai
     * e-Rapor gagal dengan "column does not exist".
     *
     * FK `nullOnDelete` dipilih agar menghapus jadwal tidak menghapus nilai,
     * hanya menandainya belum terpetakan (lihat Cek Alignment).
     */
    public function up(): void
    {
        if (Schema::hasColumn('nilai_erapor', 'jadwal_pelajaran_id')) {
            return;
        }

        Schema::table('nilai_erapor', function (Blueprint $table) {
            $table->foreignId('jadwal_pelajaran_id')
                ->nullable()
                ->after('guru_id')
                ->constrained('jadwal_pelajaran')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('nilai_erapor', 'jadwal_pelajaran_id')) {
            return;
        }

        Schema::table('nilai_erapor', function (Blueprint $table) {
            $table->dropConstrainedForeignId('jadwal_pelajaran_id');
        });
    }
};
