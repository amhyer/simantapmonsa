<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PERBAIKAN BUG INSTALASI BARU: config/session.php memakai driver
     * `database` (dan .env.example menulis SESSION_DRIVER=database), tetapi
     * tabel `sessions` tidak pernah dibuat oleh migrasi mana pun — sehingga
     * sesi tidak dapat disimpan dan login tidak pernah berhasil ("session
     * table not found") pada instalasi yang baru di-migrate.
     *
     * Skema mengikuti bawaan Laravel (php artisan make:session-table).
     */
    public function up(): void
    {
        if (Schema::hasTable('sessions')) {
            return;
        }

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
