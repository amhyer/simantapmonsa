<?php

namespace App\Policies;

use App\Models\SekolahSettings;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy otorisasi untuk entitas Modul Pembelajaran Mendalam (CP, TP, ATP, Prota, Prosem, Kokurikuler, HariLibur).
 *
 * - Selalu menegakkan isolasi tenant (tenant_id harus cocok).
 * - Admin sekolah selalu diizinkan mengelola data dalam tenantnya.
 * - Mode kolaboratif sekolah (default: true) mengizinkan sesama guru dalam 1 sekolah saling menyunting master kurikulum.
 * - Bila sekolah menonaktifkan `pm_mode_kolaboratif = false` di pengaturan sekolah, hanya pemilik (`guru_id` / `created_by`) yang dapat mengubah/menghapus.
 */
class PembelajaranMendalamPolicy
{
    public function view(User $user, Model $record): bool
    {
        if (! in_array($user->peran, ['guru', 'admin', 'kepsek'], true)) {
            return false;
        }

        return $this->sameTenant($user, $record);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->canMutate($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->canMutate($user, $record);
    }

    private function canMutate(User $user, Model $record): bool
    {
        if (! $this->sameTenant($user, $record)) {
            return false;
        }

        if ($user->peran === 'admin') {
            return true;
        }

        if ($user->peran !== 'guru') {
            return false;
        }

        $ownerId = $record->getAttribute('guru_id') ?? $record->getAttribute('created_by');
        if ($ownerId !== null && (int) $ownerId === (int) $user->id) {
            return true;
        }

        return $this->isModeKolaboratif($this->resolveTenantId($user));
    }

    private function sameTenant(User $user, Model $record): bool
    {
        $userTenant = $this->resolveTenantId($user);
        $recordTenant = $record->getAttribute('tenant_id') !== null ? (int) $record->getAttribute('tenant_id') : null;

        if ($userTenant === null && $recordTenant === null) {
            return true;
        }

        return $userTenant !== null && $recordTenant !== null && $userTenant === $recordTenant;
    }

    private function resolveTenantId(User $user): ?int
    {
        if (TenantContext::check()) {
            return (int) TenantContext::id();
        }

        return $user->tenant_id !== null ? (int) $user->tenant_id : null;
    }

    private function isModeKolaboratif(?int $tenantId): bool
    {
        $sekolah = SekolahSettings::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->first(['pengaturan']);

        $pengaturan = is_array($sekolah?->pengaturan) ? $sekolah->pengaturan : [];

        if (! array_key_exists('pm_mode_kolaboratif', $pengaturan)) {
            return true;
        }

        return (bool) $pengaturan['pm_mode_kolaboratif'];
    }
}
