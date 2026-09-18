<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['users', 'siswa', 'nilai', 'kehadiran', 'kuis', 'materi', 'catatan', 'dimensi', 'kebiasaan'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $bl) {
                $bl->softDeletes();
            });
        }
    }

    public function down(): void
    {
        $tables = ['users', 'siswa', 'nilai', 'kehadiran', 'kuis', 'materi', 'catatan', 'dimensi', 'kebiasaan'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $bl) {
                $bl->dropSoftDeletes();
            });
        }
    }
};
