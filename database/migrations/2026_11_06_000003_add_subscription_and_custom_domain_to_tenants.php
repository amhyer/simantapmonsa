<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('domain_kustom', 191)->nullable()->unique()->after('subdomain');
            $table->string('paket', 32)->default('standar')->after('domain_kustom');
            $table->unsignedInteger('kuota_siswa')->default(500)->after('paket');
            $table->unsignedInteger('kuota_storage_mb')->default(2048)->after('kuota_siswa');
            $table->date('tgl_kedaluwarsa')->nullable()->after('kuota_storage_mb');
            $table->text('catatan_langganan')->nullable()->after('tgl_kedaluwarsa');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'domain_kustom',
                'paket',
                'kuota_siswa',
                'kuota_storage_mb',
                'tgl_kedaluwarsa',
                'catatan_langganan',
            ]);
        });
    }
};
