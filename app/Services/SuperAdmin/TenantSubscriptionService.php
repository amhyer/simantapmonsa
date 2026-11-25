<?php

namespace App\Services\SuperAdmin;

use App\Mail\SchoolRegistrationStatusMail;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use App\Services\Tenant\TenantQuotaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class TenantSubscriptionService
{
    public function updateSubscription(Tenant $tenant, array $validated): Tenant
    {
        $domainKustom = ! empty($validated['domain_kustom'])
            ? strtolower(trim((string) $validated['domain_kustom']))
            : null;

        $oldExpiry = $tenant->tgl_kedaluwarsa?->toDateString();
        $newExpiry = ! empty($validated['tgl_kedaluwarsa'])
            ? Carbon::parse((string) $validated['tgl_kedaluwarsa'])->toDateString()
            : null;

        $kuotaAiHarian = isset($validated['kuota_ai_harian']) && $validated['kuota_ai_harian'] !== '' && $validated['kuota_ai_harian'] !== null
            ? max(10, min(50000, (int) $validated['kuota_ai_harian']))
            : TenantQuotaService::defaultAiQuotaForPaket($validated['paket'] ?? $tenant->paket);

        $wasRenewed = ($newExpiry !== null && ($oldExpiry === null || $newExpiry > $oldExpiry))
            || ($oldExpiry !== null && $newExpiry === null);

        DB::transaction(function () use ($tenant, $validated, $domainKustom, $newExpiry, $kuotaAiHarian, $wasRenewed): void {
            $locked = Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $meta = is_array($locked->meta) ? $locked->meta : [];
            $meta['kuota_ai_harian'] = $kuotaAiHarian;
            if ($wasRenewed) {
                unset($meta['last_expiry_reminder_sent'], $meta['is_trial']);
            }

            $locked->update([
                'nama_sekolah' => $validated['nama_sekolah'],
                'wilayah' => $validated['wilayah'] ?? null,
                'domain_kustom' => $domainKustom,
                'paket' => $validated['paket'],
                'kuota_siswa' => (int) $validated['kuota_siswa'],
                'kuota_storage_mb' => (int) $validated['kuota_storage_mb'],
                'tgl_kedaluwarsa' => $newExpiry,
                'catatan_langganan' => $validated['catatan_langganan'] ?? null,
                'meta' => $meta,
            ]);
        });

        $fresh = $tenant->fresh();
        if ($wasRenewed && $fresh) {
            $this->notifyOperator($fresh, 'subscription_renewed');
        }

        return $fresh ?? $tenant;
    }

    public function extendSubscription(Tenant $tenant, int $months, ?string $catatan = null): Tenant
    {
        $months = max(1, min(60, $months));

        DB::transaction(function () use ($tenant, $months, $catatan): void {
            $locked = Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $today = now()->startOfDay();
            $baseDate = ($locked->tgl_kedaluwarsa && $locked->tgl_kedaluwarsa->greaterThan($today))
                ? $locked->tgl_kedaluwarsa->copy()->startOfDay()
                : $today;

            $newExpiry = $baseDate->addMonthsNoOverflow($months)->toDateString();

            $meta = is_array($locked->meta) ? $locked->meta : [];
            unset($meta['last_expiry_reminder_sent'], $meta['is_trial']);
            $meta['last_extended_at'] = now()->toIso8601String();
            $meta['last_extended_months'] = $months;

            $updatePayload = [
                'tgl_kedaluwarsa' => $newExpiry,
                'meta' => $meta,
            ];

            if ($catatan !== null && trim($catatan) !== '') {
                $updatePayload['catatan_langganan'] = trim($catatan);
            }

            // Aktifkan kembali sekolah yang sebelumnya sudah disetujui tetapi sempat habis masa aktif
            if (! $locked->aktif && ! in_array($locked->approvalStatus(), ['pending', 'rejected'], true)) {
                $updatePayload['aktif'] = true;
                User::withoutGlobalScopes()
                    ->whereNull('deleted_at')
                    ->where('tenant_id', $locked->id)
                    ->where('peran', 'admin')
                    ->update(['aktif' => true]);
            }

            $locked->update($updatePayload);
        });

        $fresh = $tenant->fresh();
        if ($fresh) {
            $this->notifyOperator($fresh, 'subscription_renewed', "Perpanjangan layanan +{$months} bulan");
        }

        return $fresh ?? $tenant;
    }

    /**
     * Kirim email pengingat jatuh tempo langganan secara idempoten (tidak kirim ganda untuk H-N yang sama).
     *
     * @param  array<int, int>  $targetDays
     * @return array{checked: int, sent: int, skipped: int}
     */
    public function sendExpiryReminders(array $targetDays = [14, 7, 3, 1]): array
    {
        $days = array_values(array_unique(array_filter(
            array_map('intval', $targetDays),
            fn (int $d): bool => $d >= 0 && $d <= 90
        )));

        if ($days === []) {
            return ['checked' => 0, 'sent' => 0, 'skipped' => 0];
        }

        $today = now()->startOfDay();
        $targetDates = array_map(fn (int $d): string => $today->copy()->addDays($d)->toDateString(), $days);

        $tenants = Tenant::query()
            ->where('aktif', true)
            ->whereNotNull('tgl_kedaluwarsa')
            ->whereIn('tgl_kedaluwarsa', $targetDates)
            ->get();

        $checked = $tenants->count();
        $sent = 0;
        $skipped = 0;

        foreach ($tenants as $tenant) {
            $sisaHari = $tenant->sisaHariLangganan();
            if ($sisaHari === null || ! in_array($sisaHari, $days, true)) {
                $skipped++;

                continue;
            }

            $expiryKey = $tenant->tgl_kedaluwarsa->toDateString().':H-'.$sisaHari;
            $meta = is_array($tenant->meta) ? $tenant->meta : [];
            if (($meta['last_expiry_reminder_sent'] ?? null) === $expiryKey) {
                $skipped++;

                continue;
            }

            if ($this->notifyOperator($tenant, 'subscription_expiring', (string) $sisaHari)) {
                $meta['last_expiry_reminder_sent'] = $expiryKey;
                $meta['last_expiry_reminder_at'] = now()->toIso8601String();
                $tenant->update(['meta' => $meta]);
                $sent++;
            } else {
                $skipped++;
            }
        }

        return ['checked' => $checked, 'sent' => $sent, 'skipped' => $skipped];
    }

    public function notifyOperator(Tenant $tenant, string $status, ?string $reason = null): bool
    {
        try {
            $admin = User::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('tenant_id', $tenant->id)
                ->where('peran', 'admin')
                ->whereNotNull('email')
                ->first();

            if (! $admin || ! filled($admin->email)) {
                return false;
            }

            Mail::to($admin->email)->send(new SchoolRegistrationStatusMail($tenant, $status, $reason));

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }
}
