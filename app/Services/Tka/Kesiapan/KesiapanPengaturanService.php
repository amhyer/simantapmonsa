<?php

namespace App\Services\Tka\Kesiapan;

use App\Models\Tka\Kesiapan\Audit;
use App\Models\Tka\Kesiapan\Pemetaan;
use App\Models\Tka\Kesiapan\Pengaturan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Konfigurasi per sekolah (admin): aturan hitung internal dan pemetaan sumber → indikator.
 * Setiap perubahan dicatat di tka_kesiapan_audit (siapa, kapan, sebelum/sesudah, alasan, tenant).
 */
class KesiapanPengaturanService
{
    public const BATAS = [
        'min_bukti' => [1, 20, 'int'],
        'min_tanggal_berbeda' => [1, 20, 'int'],
        'ambang_perlu_dukungan' => [0, 100, 'float'],
        'ambang_cukup_mantap' => [0, 100, 'float'],
        'ambang_tren' => [1, 50, 'float'],
        'min_bukti_per_periode' => [1, 20, 'int'],
        'keyakinan_target_jumlah' => [1, 50, 'int'],
        'keyakinan_kebaruan_hari' => [7, 730, 'int'],
        'keyakinan_min_jenis_sumber' => [1, 6, 'int'],
        'keyakinan_maks_sebaran' => [1, 50, 'float'],
        'min_kelompok_agregat' => [1, 30, 'int'],
    ];

    /** @param array<string, mixed> $input */
    public function simpanAturan(User $admin, array $input, ?string $alasan = null): array
    {
        $bersih = [];
        foreach (self::BATAS as $kunci => [$min, $maks, $tipe]) {
            if (! array_key_exists($kunci, $input) || $input[$kunci] === null || $input[$kunci] === '') {
                continue;
            }
            if (! is_numeric($input[$kunci])) {
                throw new InvalidArgumentException("Nilai {$kunci} harus numerik.");
            }
            $v = $tipe === 'int' ? (int) $input[$kunci] : (float) $input[$kunci];
            if ($v < $min || $v > $maks) {
                throw new InvalidArgumentException("Nilai {$kunci} harus antara {$min} dan {$maks}.");
            }
            $bersih[$kunci] = $v;
        }

        $efektif = array_replace(KesiapanService::aturanTenant($admin->tenant_id), $bersih);
        if ($efektif['ambang_perlu_dukungan'] >= $efektif['ambang_cukup_mantap']) {
            throw new InvalidArgumentException('Ambang "perlu dukungan" harus lebih kecil daripada ambang "cukup mantap".');
        }

        return DB::transaction(function () use ($admin, $bersih, $alasan) {
            $row = Pengaturan::query()->withoutGlobalScopes()
                ->when($admin->tenant_id !== null, fn ($q) => $q->where('tenant_id', $admin->tenant_id), fn ($q) => $q->whereNull('tenant_id'))
                ->first();
            $sebelum = $row?->aturan ?? [];

            if (! $row) {
                $row = new Pengaturan(['tenant_id' => $admin->tenant_id]);
            }
            $row->aturan = $bersih;
            $row->diubah_oleh = $admin->id;
            $row->save();

            Audit::create([
                'tenant_id' => $admin->tenant_id,
                'user_id' => $admin->id,
                'aksi' => 'aturan',
                'sebelum' => $sebelum,
                'sesudah' => $bersih,
                'alasan' => $alasan ? trim($alasan) : null,
            ]);

            return $bersih;
        });
    }

    public function simpanPemetaan(User $admin, string $label, string $kode, ?string $alasan = null): Pemetaan
    {
        if (! array_key_exists($kode, (array) config('tka_kesiapan.indikator', []))) {
            throw new InvalidArgumentException('Kode indikator tidak dikenal.');
        }
        $norm = PemetaanResolver::norm($label);
        if ($norm === '') {
            throw new InvalidArgumentException('Label sumber wajib diisi.');
        }

        return DB::transaction(function () use ($admin, $norm, $kode, $alasan) {
            $row = Pemetaan::query()->withoutGlobalScopes()
                ->when($admin->tenant_id !== null, fn ($q) => $q->where('tenant_id', $admin->tenant_id), fn ($q) => $q->whereNull('tenant_id'))
                ->where('label_sumber', $norm)
                ->first();
            $sebelum = $row ? $row->only(['label_sumber', 'indikator_kode', 'aktif']) : null;

            if (! $row) {
                $row = new Pemetaan(['tenant_id' => $admin->tenant_id, 'label_sumber' => $norm]);
            }
            $row->fill(['indikator_kode' => $kode, 'aktif' => true, 'diubah_oleh' => $admin->id])->save();

            Audit::create([
                'tenant_id' => $admin->tenant_id,
                'user_id' => $admin->id,
                'aksi' => 'pemetaan_simpan',
                'sebelum' => $sebelum,
                'sesudah' => $row->only(['label_sumber', 'indikator_kode', 'aktif']),
                'alasan' => $alasan ? trim($alasan) : null,
            ]);

            return $row;
        });
    }

    public function hapusPemetaan(User $admin, int $id, ?string $alasan = null): void
    {
        DB::transaction(function () use ($admin, $id, $alasan) {
            $row = Pemetaan::query()->withoutGlobalScopes()
                ->when($admin->tenant_id !== null, fn ($q) => $q->where('tenant_id', $admin->tenant_id), fn ($q) => $q->whereNull('tenant_id'))
                ->whereKey($id)
                ->firstOrFail();
            $sebelum = $row->only(['label_sumber', 'indikator_kode', 'aktif']);
            $row->delete();

            Audit::create([
                'tenant_id' => $admin->tenant_id,
                'user_id' => $admin->id,
                'aksi' => 'pemetaan_hapus',
                'sebelum' => $sebelum,
                'sesudah' => null,
                'alasan' => $alasan ? trim($alasan) : null,
            ]);
        });
    }
}
