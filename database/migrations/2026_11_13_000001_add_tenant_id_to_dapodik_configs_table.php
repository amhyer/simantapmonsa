<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dapodik_configs', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
            $table->boolean('tenant_owned')->default(false)->after('tenant_id');
            $table->index(['tenant_id', 'tenant_owned']);
        });
    }

    public function down(): void
    {
        Schema::table('dapodik_configs', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'tenant_owned']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn('tenant_owned');
        });
    }
};
