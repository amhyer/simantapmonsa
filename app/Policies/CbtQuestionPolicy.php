<?php

namespace App\Policies;

use App\Models\CbtQuestion;
use App\Models\User;

/**
 * Soal dimiliki lewat bank-nya.
 */
class CbtQuestionPolicy
{
    public function view(User $user, CbtQuestion $question): bool
    {
        return (int) $question->bank->guru_id === (int) $user->id;
    }

    public function update(User $user, CbtQuestion $question): bool
    {
        return $this->view($user, $question);
    }

    public function delete(User $user, CbtQuestion $question): bool
    {
        return $this->view($user, $question);
    }
}
