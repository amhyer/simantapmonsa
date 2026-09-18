<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE aktivitas DROP CONSTRAINT IF EXISTS aktivitas_jenis_check");
        DB::statement("ALTER TABLE aktivitas ADD CONSTRAINT aktivitas_jenis_check CHECK (jenis IN ('materi','kuis','nilai','kehadiran','catatan','dimensi','kebiasaan','pengaturan','login','tambah','ubah','hapus','lihat'))");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE aktivitas DROP CONSTRAINT IF EXISTS aktivitas_jenis_check");
        DB::statement("ALTER TABLE aktivitas ADD CONSTRAINT aktivitas_jenis_check CHECK (jenis IN ('materi','kuis','nilai','kehadiran','catatan','dimensi','kebiasaan','pengaturan'))");
    }
};
