<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only run if is_active column exists (new installs won't have it)
        if (Schema::hasColumn('users', 'is_active')) {
            // Sync is_active → aktif for users
            DB::table('users')->where('is_active', true)->where('aktif', false)->update(['aktif' => true]);
            DB::table('users')->where('is_active', false)->where('aktif', true)->update(['aktif' => false]);
            Schema::table('users', function ($table) {
                $table->dropColumn('is_active');
            });
        }

        if (Schema::hasColumn('siswa', 'is_active')) {
            // Sync is_active → aktif for siswa
            DB::table('siswa')->where('is_active', true)->where('aktif', false)->update(['aktif' => true]);
            DB::table('siswa')->where('is_active', false)->where('aktif', true)->update(['aktif' => false]);
            Schema::table('siswa', function ($table) {
                $table->dropColumn('is_active');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function ($table) {
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('aktif');
            }
        });
        Schema::table('siswa', function ($table) {
            if (!Schema::hasColumn('siswa', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('aktif');
            }
        });

        if (Schema::hasColumn('users', 'is_active')) {
            DB::table('users')->update(['is_active' => DB::raw('aktif')]);
        }
        if (Schema::hasColumn('siswa', 'is_active')) {
            DB::table('siswa')->update(['is_active' => DB::raw('aktif')]);
        }
    }
};
