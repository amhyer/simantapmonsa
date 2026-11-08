<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'siswa'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedInteger('qr_version_counter')->default(0);
                $table->boolean('qr_revoked')->default(false);
                $table->string('qr_generation_nonce', 32)->nullable();
            });
            // Mempertahankan urutan versi yang pernah diterbitkan sebelum migrasi.
            DB::table($name)->update(['qr_version_counter' => DB::raw('CASE WHEN printed_version > qr_card_version THEN printed_version ELSE qr_card_version END')]);
            // Tidak dapat membedakan kartu lama yang sudah dilepas dari yang belum pernah dibuat.
            // Fail closed: penerbitan awal eksplisit oleh admin.
            DB::table($name)->whereNull('qr_token_hash')->update([
                'qr_revoked' => true, 'qr_previous_token_hash' => null,
                'printed_at' => null, 'printed_version' => 0,
            ]);
        }
        Schema::table('presensi_pegawai', function (Blueprint $table) {
            $table->string('metode_masuk', 16)->nullable();
            $table->string('metode_pulang', 16)->nullable();
        });
        // Jangan menebak metode historis karena satu kolom lama dapat ditimpa saat pulang.
    }

    public function down(): void
    {
        Schema::table('presensi_pegawai', fn (Blueprint $table) => $table->dropColumn(['metode_masuk', 'metode_pulang']));
        foreach (['users', 'siswa'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['qr_version_counter', 'qr_revoked', 'qr_generation_nonce']));
        }
    }
};
