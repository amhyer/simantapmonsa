<?php

namespace App\Policies;

use App\Models\Tryout\Session;
use App\Models\User;

/**
 * Sesi tryout hanya boleh dikelola guru pembuatnya; admin boleh semua.
 */
class TryoutSessionPolicy
{
    public function view(User $user, Session $session): bool
    {
        return $user->peran === 'admin' || (int) $session->guru_id === (int) $user->id;
    }

    public function update(User $user, Session $session): bool
    {
        return $this->view($user, $session);
    }

    public function delete(User $user, Session $session): bool
    {
        return $this->view($user, $session);
    }

    public function publish(User $user, Session $session): bool
    {
        return $this->view($user, $session);
    }

    public function start(User $user, Session $session): bool
    {
        return $this->view($user, $session);
    }

    public function complete(User $user, Session $session): bool
    {
        return $this->view($user, $session);
    }

    public function monitoring(User $user, Session $session): bool
    {
        return $this->view($user, $session);
    }
}
