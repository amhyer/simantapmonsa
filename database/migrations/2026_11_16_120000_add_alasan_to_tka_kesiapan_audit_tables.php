<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom `alasan` pada tabel audit konfigurasi & revisi tindak lanjut
 * kesiapan TKA (migrasi aditif murni, aman dijalankan ulang).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tka_kesiapan_audit') && ! Schema::hasColumn('tka_kesiapan_audit', 'alasan')) {
            Schema::table('tka_kesiapan_audit', function (Blueprint $table) {
                $table->text('alasan')->nullable()->after('sesudah');
            });
        }

        if (Schema::hasTable('tka_kesiapan_tindak_lanjut_revisi') && ! Schema::hasColumn('tka_kesiapan_tindak_lanjut_revisi', 'alasan')) {
            Schema::table('tka_kesiapan_tindak_lanjut_revisi', function (Blueprint $table) {
                $table->text('alasan')->nullable()->after('catatan');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tka_kesiapan_audit') && Schema::hasColumn('tka_kesiapan_audit', 'alasan')) {
            Schema::table('tka_kesiapan_audit', function (Blueprint $table) {
                $table->dropColumn('alasan');
            });
        }

        if (Schema::hasTable('tka_kesiapan_tindak_lanjut_revisi') && Schema::hasColumn('tka_kesiapan_tindak_lanjut_revisi', 'alasan')) {
            Schema::table('tka_kesiapan_tindak_lanjut_revisi', function (Blueprint $table) {
                $table->dropColumn('alasan');
            });
        }
    }
};
