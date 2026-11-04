<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daftar tabel transaksional inti yang dilengkapi isolasi `tenant_id`
     * untuk kesiapan arsitektur SaaS Multi-Tenant (satu database banyak sekolah).
     *
     * @var list<string>
     */
    private array $tenantTables = [
        'users',
        'siswa',
        'rombel',
        'sekolah_settings',
        'cbt_question_banks',
        'cbt_test_sessions',
        'cbt_results',
        'exam_banks',
        'exam_sessions',
        'exam_attempts',
        'tryout_packages',
        'tryout_sessions',
        'tryout_results',
        'tka_student_assessments',
        'nilai_erapor',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('npsn', 20)->nullable()->unique();
                $table->string('nama_sekolah');
                $table->string('subdomain', 100)->nullable()->unique();
                $table->boolean('aktif')->default(true)->index();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        foreach ($this->tenantTables as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->foreignId('tenant_id')
                    ->nullable()
                    ->constrained('tenants')
                    ->nullOnDelete();

                $table->index('tenant_id', "{$tableName}_tenant_id_idx");
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tenantTables) as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropForeign(['tenant_id']);
                $table->dropIndex("{$tableName}_tenant_id_idx");
                $table->dropColumn('tenant_id');
            });
        }

        Schema::dropIfExists('tenants');
    }
};
