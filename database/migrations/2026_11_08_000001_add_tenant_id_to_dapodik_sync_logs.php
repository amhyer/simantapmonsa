<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dapodik_sync_logs', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            // Tetap true bila tenant dihapus: log yatim bukan log sekolah tunggal.
            $table->boolean('tenant_owned')->default(false);
            $table->index(['tenant_id', 'created_at'], 'dapodik_logs_tenant_created_idx');
        });

        // Jangan otomatis menebak pemilik log lama dari NPSN payload atau
        // user yang mungkin sudah dipindahkan ke sekolah lain.
    }

    public function down(): void
    {
        Schema::table('dapodik_sync_logs', function (Blueprint $table) {
            $table->dropIndex('dapodik_logs_tenant_created_idx');
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn('tenant_owned');
        });
    }
};
