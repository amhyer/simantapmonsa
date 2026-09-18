<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->string('semester_id')->unique();
            $table->string('tahun_ajaran');
            $table->enum('nama_semester', ['ganjil', 'genap']);
            $table->timestamps();
        });

        Schema::create('ptk', function (Blueprint $table) {
            $table->id();
            $table->string('dapodik_id')->index();
            $table->foreignId('semester_id')->constrained('semesters');
            $table->string('nama');
            $table->string('nip')->nullable();
            $table->string('jenis_ptk');
            $table->string('jabatan')->nullable();
            $table->timestamps();
            $table->unique(['dapodik_id', 'semester_id']);
        });

        Schema::create('rombel', function (Blueprint $table) {
            $table->id();
            $table->string('dapodik_id')->index();
            $table->foreignId('semester_id')->constrained('semesters');
            $table->string('nama_rombel');
            $table->string('tingkat')->nullable();
            $table->timestamps();
        });

        Schema::table('siswa', function (Blueprint $table) {
            if (!Schema::hasColumn('siswa', 'dapodik_id')) {
                $table->string('dapodik_id')->nullable()->index()->after('uuid');
            }
            if (!Schema::hasColumn('siswa', 'semester_id')) {
                $table->foreignId('semester_id')->nullable()->constrained('semesters')->after('nama_guru');
            }
            if (!Schema::hasColumn('siswa', 'status_siswa')) {
                $table->string('status_siswa', 20)->nullable()->default('aktif')->after('aktif');
            }
        });

        Schema::table('jadwal_pelajaran', function (Blueprint $table) {
            if (!Schema::hasColumn('jadwal_pelajaran', 'dapodik_id')) {
                $table->string('dapodik_id')->nullable()->index()->after('id');
            }
            if (!Schema::hasColumn('jadwal_pelajaran', 'semester_id')) {
                $table->foreignId('semester_id')->nullable()->constrained('semesters')->after('dapodik_id');
            }
            if (!Schema::hasColumn('jadwal_pelajaran', 'rombel_id')) {
                $table->foreignId('rombel_id')->nullable()->constrained('rombel')->after('semester_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_pelajaran', function (Blueprint $table) {
            if (Schema::hasColumn('jadwal_pelajaran', 'rombel_id')) {
                $table->dropForeign(['rombel_id']);
            }
            if (Schema::hasColumn('jadwal_pelajaran', 'semester_id')) {
                $table->dropForeign(['semester_id']);
            }
            $table->dropColumn(['dapodik_id', 'semester_id', 'rombel_id']);
        });

        Schema::table('siswa', function (Blueprint $table) {
            if (Schema::hasColumn('siswa', 'semester_id')) {
                $table->dropForeign(['semester_id']);
            }
            $table->dropColumn(['dapodik_id', 'semester_id', 'status_siswa']);
        });

        Schema::dropIfExists('rombel');
        Schema::dropIfExists('ptk');
        Schema::dropIfExists('semesters');
    }
};
