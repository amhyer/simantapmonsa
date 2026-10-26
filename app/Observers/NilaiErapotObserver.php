<?php

namespace App\Observers;

use App\Models\NilaiErapot;
use App\Services\NilaiErapotAuditService;

/**
 * Mencatat setiap perubahan nilai e-Rapor dari jalur mana pun (input guru,
 * import, sinkronisasi) tanpa bergantung pada controller tertentu.
 */
class NilaiErapotObserver
{
    public function __construct(private NilaiErapotAuditService $audit) {}

    public function created(NilaiErapot $nilai): void
    {
        $this->audit->catat($nilai, 'dibuat');
    }

    public function updated(NilaiErapot $nilai): void
    {
        if (! $nilai->wasChanged()) {
            return;
        }

        $this->audit->catat($nilai, 'diperbarui');
    }

    public function deleted(NilaiErapot $nilai): void
    {
        $this->audit->catat($nilai, 'dihapus');
    }
}
