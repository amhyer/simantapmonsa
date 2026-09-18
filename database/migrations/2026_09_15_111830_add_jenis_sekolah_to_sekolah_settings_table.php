<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sekolah_settings', function (Blueprint $table) {
            $table->string('jenis_sekolah', 50)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('sekolah_settings', function (Blueprint $table) {
            $table->dropColumn('jenis_sekolah');
        });
    }
};
