<?php

namespace App\Policies;

use App\Models\CbtTestSession;
use App\Models\User;

/**
 * Sesi ujian hanya boleh dikendalikan guru pembuatnya.
 */
class CbtTestSessionPolicy
{
    public function view(User $user, CbtTestSession $session): bool
    {
        return (int) $session->guru_id === (int) $user->id;
    }

    public function update(User $user, CbtTestSession $session): bool
    {
        return $this->view($user, $session);
    }

    public function publish(User $user, CbtTestSession $session): bool
    {
        return $this->view($user, $session);
    }

    public function start(User $user, CbtTestSession $session): bool
    {
        return $this->view($user, $session);
    }

    public function complete(User $user, CbtTestSession $session): bool
    {
        return $this->view($user, $session);
    }

    public function monitoring(User $user, CbtTestSession $session): bool
    {
        return $this->view($user, $session);
    }
}
