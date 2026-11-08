<?php

namespace App\Modules\Tenant\Traits;

use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Data lama tidak dapat dikaitkan dengan sekolah berdasarkan pemilik akun saat ini. */
trait HasSchoolOwnership
{
    public static function bootHasSchoolOwnership(): void
    {
        static::addGlobalScope('schoolOwnership', function (Builder $query): void {
            $table = $query->getModel()->getTable();
            if (TenantContext::check()) {
                $query->where($table.'.tenant_id', TenantContext::id());
            } elseif (config('simantap.multi_tenant_enabled', false) || Tenant::query()->exists()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereNull($table.'.tenant_id')->where($table.'.tenant_owned', false);
            }
        });

        static::creating(function (Model $model): void {
            if (TenantContext::check()) {
                $model->setAttribute('tenant_id', TenantContext::id());
            }
            if ($model->getAttribute('tenant_id') === null
                && (config('simantap.multi_tenant_enabled', false) || Tenant::query()->exists())) {
                throw new \RuntimeException('Kepemilikan sekolah diperlukan untuk menyimpan data ini.');
            }
            if ($model->getAttribute('tenant_id') !== null) {
                $model->setAttribute('tenant_owned', true);
            }
        });
    }
}
