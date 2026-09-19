<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mata_pelajaran', function (Blueprint $table) {
            $table->string('kelompok', 50)->nullable()->after('jenjang');
            $table->integer('urutan')->default(0)->after('kelompok');
        });
    }

    public function down(): void
    {
        Schema::table('mata_pelajaran', function (Blueprint $table) {
            $table->dropColumn(['kelompok', 'urutan']);
        });
    }
};
