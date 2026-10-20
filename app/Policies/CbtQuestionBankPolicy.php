<?php

namespace App\Policies;

use App\Models\CbtQuestionBank;
use App\Models\User;

/**
 * Bank soal hanya boleh dikelola guru pemiliknya (Fase 2.1/2.2).
 */
class CbtQuestionBankPolicy
{
    public function view(User $user, CbtQuestionBank $bank): bool
    {
        return (int) $bank->guru_id === (int) $user->id;
    }

    public function update(User $user, CbtQuestionBank $bank): bool
    {
        return $this->view($user, $bank);
    }

    public function delete(User $user, CbtQuestionBank $bank): bool
    {
        return $this->view($user, $bank);
    }
}
