<?php

namespace App\Policies;

use App\Models\User;

class PegawaiFotoPolicy
{
    public function viewFoto(User $viewer, User $target): bool
    {
        $viewerTenant = $viewer->tenant_id !== null ? (int) $viewer->tenant_id : null;
        $targetTenant = $target->tenant_id !== null ? (int) $target->tenant_id : null;

        if ($viewerTenant !== $targetTenant) {
            return false;
        }

        if ((int) $viewer->id === (int) $target->id) {
            return true;
        }

        return in_array($viewer->peran, ['admin', 'kepsek'], true);
    }
}
