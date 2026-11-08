<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_izin_presensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->boolean('tenant_owned')->default(false);
            $table->foreignId('siswa_id')->constrained('siswa')->restrictOnDelete();
            $table->foreignId('orang_tua_id')->constrained('users')->restrictOnDelete();
            $table->date('tanggal');
            $table->string('jenis', 1); // I = izin, S = sakit
            $table->string('alasan', 500);
            $table->string('status', 20)->default('menunggu');
            $table->foreignId('diperiksa_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diperiksa_at')->nullable();
            $table->string('catatan_pemeriksa', 500)->nullable();
            $table->timestamps();
            $table->unique(['siswa_id', 'tanggal']);
            $table->index(['tenant_id', 'status', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_izin_presensi');
    }
};
