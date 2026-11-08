<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tables = ['aktivitas', 'ringkasan_guru', 'ptk', 'ekstrakurikuler'];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->boolean('tenant_owned')->default(false);
            });
        }
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('tenant_owned')->default(false);
        });
        // Kepemilikan akun saat ini diketahui; ini bukan backfill data historis.
        DB::table('users')->whereNotNull('tenant_id')->update(['tenant_owned' => true]);
        // Data lama tidak otomatis dipindahkan: akun dapat berpindah sekolah.
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('tenant_owned'));
        foreach (array_reverse($this->tables) as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('tenant_id');
                $table->dropColumn('tenant_owned');
            });
        }
    }
};
