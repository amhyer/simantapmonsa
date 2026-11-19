<?php

namespace App\Modules\Tenant\Support;

use App\Modules\Tenant\Models\Tenant;

/**
 * Memoize hasil `Tenant::query()->exists()` per siklus request/container
 * (didaftarkan via `app()->scoped(...)` sehingga aman untuk tes & Octane).
 */
class TenantExistenceCache
{
    private ?bool $exists = null;

    public function hasAny(): bool
    {
        if ($this->exists === null) {
            $this->exists = Tenant::query()->exists();
        }

        return $this->exists;
    }

    public function forget(): void
    {
        $this->exists = null;
    }
}
