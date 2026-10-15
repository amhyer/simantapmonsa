<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PERBAIKAN KEAMANAN: kunci API tidak lagi disimpan dalam bentuk polos.
     *
     * 1. Kolom `key` dibuat nullable (kunci baru hanya menyimpan `key_hash`).
     * 2. Kunci lama yang belum punya `key_hash` di-backfill dari nilai polosnya,
     *    sehingga pencarian hash-saja di ApiKeyMiddleware tetap menemukan
     *    kunci-kunci lama (tanpa fallback plaintext).
     */
    public function up(): void
    {
        if (Schema::hasColumn('api_keys', 'key')) {
            Schema::table('api_keys', function (Blueprint $table) {
                $table->string('key', 64)->nullable()->change();
            });
        }

        if (!Schema::hasColumn('api_keys', 'key_hash')) {
            return;
        }

        DB::table('api_keys')
            ->whereNull('key_hash')
            ->whereNotNull('key')
            ->orderBy('id')
            ->each(function ($row) {
                DB::table('api_keys')
                    ->where('id', $row->id)
                    ->update(['key_hash' => hash('sha256', $row->key)]);
            });
    }

    public function down(): void
    {
        // Tidak dikembalikan ke NOT NULL: baris baru tidak menyimpan kunci polos.
    }
};
