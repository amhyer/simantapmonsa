<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'sandi_awal_diterbitkan_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('sandi_awal_diterbitkan_at')->nullable()->after('force_password_change');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'sandi_awal_diterbitkan_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('sandi_awal_diterbitkan_at');
            });
        }
    }
};
