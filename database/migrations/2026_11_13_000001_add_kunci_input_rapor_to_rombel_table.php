<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('rombel', 'kunci_input_rapor')) {
            Schema::table('rombel', function (Blueprint $table) {
                $table->boolean('kunci_input_rapor')->default(false)->after('guru_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('rombel', 'kunci_input_rapor')) {
            Schema::table('rombel', function (Blueprint $table) {
                $table->dropColumn('kunci_input_rapor');
            });
        }
    }
};
