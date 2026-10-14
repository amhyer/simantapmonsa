<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PERBAIKAN BUG: `kuis.batas_waktu` semula `integer` (dilabeli "menit" di
     * form guru) tetapi dikonsumsi sebagai tanggal/waktu:
     *   - cast model:  Kuis::$casts['batas_waktu'] = 'datetime'
     *   - server:      Siswa\KuisController::submit() membandingkan
     *                  now()->greaterThan($kuis->batas_waktu->copy()->addMinutes(5))
     *   - tampilan:    siswa/kuis/show.blade.php memakai ->timestamp untuk countdown
     * Akibatnya nilai menit (mis. 60) dibaca sebagai 1970-01-01 00:01:00 →
     * semua kuis bertimer langsung "kedaluwarsa" dan auto-submit seketika.
     *
     * Kolom diubah menjadi timestamp NULL agar semantik konsisten:
     *   batas_waktu = waktu tutup kuis (NULL = tanpa batas waktu).
     *
     * Catatan: nilai lama (menit) tidak dapat dikonversi otomatis karena tidak
     * ada kolom waktu mulai kuis; kolom lama dibuang sehingga nilai tersebut
     * hilang dan diganti NULL (aman: sebelumnya nilai itu memang salah tafsir).
     */
    public function up(): void
    {
        if (!Schema::hasColumn('kuis', 'batas_waktu')) {
            return;
        }

        Schema::table('kuis', function (Blueprint $table) {
            $table->dropColumn('batas_waktu');
        });

        Schema::table('kuis', function (Blueprint $table) {
            $table->timestamp('batas_waktu')->nullable()->after('kkm');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('kuis', 'batas_waktu')) {
            return;
        }

        Schema::table('kuis', function (Blueprint $table) {
            $table->dropColumn('batas_waktu');
        });

        Schema::table('kuis', function (Blueprint $table) {
            $table->integer('batas_waktu')->default(0)->after('kkm');
        });
    }
};
