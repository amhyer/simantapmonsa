<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rombel', function (Blueprint $table) {
            $table->string('kurikulum', 100)->nullable()->after('tingkat');
            $table->string('jenis_rombel', 20)->nullable()->after('kurikulum');
        });
    }

    public function down(): void
    {
        Schema::table('rombel', function (Blueprint $table) {
            $table->dropColumn(['kurikulum', 'jenis_rombel']);
        });
    }
};
