<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PERBAIKAN BUG SINKRONISASI DAPODIK: `sekolah_settings.guru_id` dibuat
     * NOT NULL, padahal DapodikSyncService::syncSekolah() membuat baris
     * identitas sekolah hasil tarikan Dapodik tanpa guru pemilik
     * ('guru_id' => null) → pelanggaran NOT NULL dan data sekolah hasil
     * sinkronisasi selalu gagal disimpan (tertangkap sebagai $result['gagal']).
     *
     * Identitas sekolah adalah data tingkat sekolah, bukan milik satu guru,
     * sehingga kolomnya dibuat nullable.
     */
    public function up(): void
    {
        Schema::table('sekolah_settings', function (Blueprint $table) {
            $table->foreignId('guru_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Sengaja tidak dikembalikan ke NOT NULL: baris hasil sinkronisasi
        // Dapodik memakai guru_id NULL sehingga rollback akan gagal dan
        // berpotensi menghapus data. Turunkan manual bila yakin data bersih.
    }
};
