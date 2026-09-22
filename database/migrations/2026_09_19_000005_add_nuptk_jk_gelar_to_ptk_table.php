<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ptk', function (Blueprint $table) {
            $table->string('nuptk', 30)->nullable()->after('nip');
            $table->string('jk', 2)->nullable()->after('nuptk');
            $table->string('gelar_depan', 50)->nullable()->after('nama');
            $table->string('gelar_belakang', 100)->nullable()->after('gelar_depan');
        });
    }

    public function down(): void
    {
        Schema::table('ptk', function (Blueprint $table) {
            $table->dropColumn(['nuptk', 'jk', 'gelar_depan', 'gelar_belakang']);
        });
    }
};
