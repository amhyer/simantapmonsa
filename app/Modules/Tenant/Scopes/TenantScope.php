<?php

namespace App\Modules\Tenant\Scopes;

use App\Modules\Tenant\Contexts\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global Scope isolasi data multi-tenant (single-database).
 *
 * Saat `TenantContext` aktif, setiap query otomatis diberi klausa
 * `WHERE <tabel>.tenant_id = <current_tenant_id>` sehingga kebocoran data
 * lintas sekolah (cross-tenant leak) dicegah di level ORM.
 * Saat mode sekolah tunggal (konteks tidak aktif), query berjalan normal.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = TenantContext::id();

        if ($tenantId !== null) {
            $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
        }
    }
}
