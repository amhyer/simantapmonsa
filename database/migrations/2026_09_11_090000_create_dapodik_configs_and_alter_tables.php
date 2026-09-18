<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dapodik_configs', function (Blueprint $table) {
            $table->string('id')->primary()->default('singleton');
            $table->string('npsn')->nullable();
            $table->string('host')->default('localhost');
            $table->integer('port')->default(5774);
            $table->string('protocol')->default('http');
            $table->text('token')->nullable();
            $table->boolean('allow_insecure_in_production')->default(false);
            $table->boolean('archive_unlisted')->default(true);
            $table->boolean('auto_sync_enabled')->default(false);
            $table->integer('auto_sync_interval_hours')->default(24);
            $table->timestamp('auto_sync_last_run_at')->nullable();
            $table->string('auto_sync_run_status')->nullable();
            $table->text('auto_sync_run_error')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->string('last_sync_by')->nullable();
            $table->json('cf_access')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'dapodik_id')) {
                $table->string('dapodik_id')->nullable()->index();
            }
            if (!Schema::hasColumn('users', 'archived_at')) {
                $table->timestamp('archived_at')->nullable();
            }
        });

        Schema::table('siswa', function (Blueprint $table) {
            if (!Schema::hasColumn('siswa', 'archived_at')) {
                $table->timestamp('archived_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dapodik_configs');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'dapodik_id')) {
                $table->dropColumn('dapodik_id');
            }
            if (Schema::hasColumn('users', 'archived_at')) {
                $table->dropColumn('archived_at');
            }
        });

        Schema::table('siswa', function (Blueprint $table) {
            if (Schema::hasColumn('siswa', 'archived_at')) {
                $table->dropColumn('archived_at');
            }
        });
    }
};
