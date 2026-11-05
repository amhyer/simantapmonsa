<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel-tabel pembelajaran & asesmen yang dilengkapi isolasi `tenant_id`
     * untuk mencegah kebocoran data antar sekolah (cross-tenant leak).
     *
     * @var list<string>
     */
    private array $tenantTables = [
        'kuis',
        'hasil_kuis',
        'nilai',
        'materi',
        'catatan',
        'kebiasaan',
        'dimensi',
    ];

    public function up(): void
    {
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
    }
};
