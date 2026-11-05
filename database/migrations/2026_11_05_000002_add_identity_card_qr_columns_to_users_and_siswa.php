<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $attendanceTenantTables = [
        'jadwal_pelajaran',
        'kehadiran',
        'kehadiran_mapel',
        'presensi_lokasi',
        'presensi_pegawai',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'qr_token_hash')) {
                $table->string('qr_token_hash', 64)->nullable()->unique();
            }
            if (!Schema::hasColumn('users', 'qr_card_version')) {
                $table->unsignedInteger('qr_card_version')->default(0);
            }
            if (!Schema::hasColumn('users', 'nip')) {
                $table->string('nip', 50)->nullable();
            }
            if (!Schema::hasColumn('users', 'foto')) {
                $table->string('foto', 255)->nullable();
            }
        });

        Schema::table('siswa', function (Blueprint $table) {
            if (!Schema::hasColumn('siswa', 'qr_card_version')) {
                $table->unsignedInteger('qr_card_version')->default(0);
            }
        });

        if (Schema::hasTable('tenants')) {
            foreach ($this->attendanceTenantTables as $tableName) {
                if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'tenant_id')) {
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
    }

    public function down(): void
    {
        foreach (array_reverse($this->attendanceTenantTables) as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropForeign(['tenant_id']);
                $table->dropIndex("{$tableName}_tenant_id_idx");
                $table->dropColumn('tenant_id');
            });
        }

        Schema::table('siswa', function (Blueprint $table) {
            if (Schema::hasColumn('siswa', 'qr_card_version')) {
                $table->dropColumn('qr_card_version');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'qr_token_hash')) {
                $table->dropUnique(['qr_token_hash']);
                $table->dropColumn('qr_token_hash');
            }
            foreach (['qr_card_version', 'nip', 'foto'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
