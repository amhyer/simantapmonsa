<?php

namespace App\Policies;

use App\Models\Siswa;
use App\Models\Tka\StudentAssessment;
use App\Models\User;

class TkaAssessmentPolicy
{
    public function view(User $user, StudentAssessment $assessment): bool
    {
        return $this->ownsStudent($user, $assessment->siswa);
    }

    public function createForStudent(User $user, StudentAssessment $assessment, Siswa $siswa): bool
    {
        return $this->ownsStudent($user, $siswa);
    }

    public function viewStudentHistory(User $user, StudentAssessment $assessment, Siswa $siswa): bool
    {
        return $this->ownsStudent($user, $siswa);
    }

    public function generateParentReport(User $user, StudentAssessment $assessment): bool
    {
        return $this->ownsStudent($user, $assessment->siswa);
    }

    private function ownsStudent(User $user, ?Siswa $siswa): bool
    {
        return $user->peran === 'guru'
            && $siswa !== null
            && (int) $siswa->guru_id === (int) $user->id;
    }
}
