<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sekolah_settings', function (Blueprint $table) {
            $table->string('kecamatan')->nullable()->after('alamat');
            $table->string('akreditasi')->nullable()->after('jenjang');
        });
    }

    public function down(): void
    {
        Schema::table('sekolah_settings', function (Blueprint $table) {
            $table->dropColumn(['kecamatan', 'akreditasi']);
        });
    }
};
