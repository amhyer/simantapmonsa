<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presensi_mesin_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            // Tetap true bila tenant dihapus: log yatim bukan log sekolah tunggal.
            $table->boolean('tenant_owned')->default(false);
            $table->string('source', 30)->default('presensi-bridge');
            // berhasil | gagal
            $table->string('status', 20)->default('berhasil');
            $table->integer('jumlah_scan')->default(0);
            $table->integer('dibuat')->default(0);
            $table->integer('diperbarui')->default(0);
            $table->integer('tetap')->default(0);
            $table->integer('dilindungi')->default(0);
            $table->integer('nik_tidak_dikenal')->default(0);
            $table->json('contoh_nik_tidak_dikenal')->nullable();
            $table->text('pesan_error')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presensi_mesin_sync_logs');
    }
};
