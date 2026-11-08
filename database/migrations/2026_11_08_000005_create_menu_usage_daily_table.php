<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_usage_daily', function (Blueprint $table) {
            $table->id();
            $table->date('visit_date');
            // 'single' untuk instalasi tunggal, 'tenant:{id}' untuk sekolah tenant.
            $table->string('scope_key', 32);
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('role', 24);
            $table->string('route_name', 120);
            $table->unsignedInteger('visits')->default(1);
            $table->timestamps();

            $table->unique(['visit_date', 'scope_key', 'role', 'route_name'], 'menu_usage_daily_unique');
            $table->index(['visit_date', 'role', 'route_name'], 'menu_usage_daily_report_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_usage_daily');
    }
};
