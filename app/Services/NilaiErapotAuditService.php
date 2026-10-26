<?php

namespace App\Services;

use App\Models\NilaiErapot;
use App\Models\NilaiErapotAudit;
use Illuminate\Support\Facades\DB;

/**
 * Mencatat dan memverifikasi jejak audit nilai e-Rapor.
 *
 * Rantai dibentuk per baris nilai: hash catatan ke-N memasukkan hash catatan
 * ke-(N-1). Verifikasi menghitung ulang setiap hash dari snapshot yang
 * tersimpan, sehingga perubahan langsung di database (termasuk perubahan satu
 * angka nilai) akan terdeteksi.
 *
 * Catatan: penguncian `lockForUpdate` menjaga urutan pada PostgreSQL/MySQL
 * ketika beberapa penulis menyentuh baris nilai yang sama. SQLite (dipakai
 * test) mengabaikan penguncian tersebut, tetapi urutan tetap serial di dalam
 * transaksi penulisan.
 */
class NilaiErapotAuditService
{
    private const KOLOM_SNAPSHOT = [
        'id', 'uuid', 'siswa_id', 'guru_id', 'jadwal_pelajaran_id',
        'mata_pelajaran', 'kelas', 'semester', 'tahun_ajaran', 'fase',
        'nilai_formatif', 'nilai_sumatif', 'nilai_sumatif_akhir',
        'nilai_akhir', 'predikat', 'deskripsi_capaian',
        'capaian_tertinggi', 'capaian_terendah',
        'terkirim_erapor', 'erapor_synced_at', 'status',
        'created_at', 'updated_at',
    ];

    private static bool $auditAktif = true;

    /**
     * Jalankan operasi tanpa menghasilkan catatan audit baru. Dipakai saat
     * restore backup agar baris audit lama tidak bercampur dengan catatan
     * yang dibuat oleh event model selama proses restore.
     */
    public static function tanpaAudit(callable $callback): mixed
    {
        $sebelum = self::$auditAktif;
        self::$auditAktif = false;

        try {
            return $callback();
        } finally {
            self::$auditAktif = $sebelum;
        }
    }

    public function catat(NilaiErapot $nilai, string $aksi, ?string $statusLama = null): ?NilaiErapotAudit
    {
        if (! self::$auditAktif) {
            return null;
        }

        return DB::transaction(function () use ($nilai, $aksi, $statusLama): NilaiErapotAudit {
            $sebelumnya = NilaiErapotAudit::query()
                ->where('nilai_erapor_id', $nilai->getKey())
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $waktu = now()->startOfSecond();
            $snapshot = $this->snapshot($nilai);
            $statusBaru = $snapshot['status'] ?? null;
            $statusLama ??= $sebelumnya?->status_baru;

            $payload = $this->payload(
                nilaiErapotId: $nilai->getKey(),
                siswaId: $snapshot['siswa_id'] ?? null,
                guruId: $snapshot['guru_id'] ?? null,
                aksi: $aksi,
                statusLama: $statusLama,
                statusBaru: $statusBaru,
                snapshot: $snapshot,
                prevHash: $sebelumnya?->hash,
                dicatatAt: $waktu->format('Y-m-d H:i:s'),
            );

            $audit = new NilaiErapotAudit([
                'nilai_erapor_id' => $nilai->getKey(),
                'siswa_id' => $snapshot['siswa_id'] ?? null,
                'guru_id' => $snapshot['guru_id'] ?? null,
                'aksi' => $aksi,
                'status_lama' => $statusLama,
                'status_baru' => $statusBaru,
                'snapshot' => $snapshot,
                'prev_hash' => $sebelumnya?->hash,
                'hash' => $this->hash($payload),
                'ip_address' => app()->runningInConsole() ? null : request()->ip(),
                'user_agent' => app()->runningInConsole() ? null : mb_substr((string) request()->userAgent(), 0, 255),
            ]);
            $audit->created_at = $waktu;
            $audit->updated_at = $waktu;
            $audit->save();

            return $audit;
        });
    }

    /**
     * @return array{ok: bool, diperiksa: int, masalah: array<int, array<string, mixed>>}
     */
    public function verifikasi(?int $nilaiErapotId = null): array
    {
        $query = NilaiErapotAudit::query()
            ->orderBy('nilai_erapor_id')
            ->orderBy('id');

        if ($nilaiErapotId !== null) {
            $query->where('nilai_erapor_id', $nilaiErapotId);
        }

        $masalah = [];
        $diperiksa = 0;
        $hashTerakhir = [];

        foreach ($query->cursor() as $audit) {
            $diperiksa++;
            $kunci = (string) $audit->nilai_erapor_id;
            $prevDiharapkan = $hashTerakhir[$kunci] ?? null;

            if (($audit->prev_hash ?? null) !== $prevDiharapkan) {
                $masalah[] = [
                    'audit_id' => $audit->id,
                    'nilai_erapor_id' => $audit->nilai_erapor_id,
                    'jenis' => 'prev_hash_tidak_cocok',
                    'diharapkan' => $prevDiharapkan,
                    'tersimpan' => $audit->prev_hash,
                ];
            }

            $payload = $this->payload(
                nilaiErapotId: $audit->nilai_erapor_id,
                siswaId: $audit->siswa_id,
                guruId: $audit->guru_id,
                aksi: $audit->aksi,
                statusLama: $audit->status_lama,
                statusBaru: $audit->status_baru,
                snapshot: $audit->snapshot ?? [],
                prevHash: $audit->prev_hash,
                dicatatAt: $audit->created_at?->format('Y-m-d H:i:s'),
            );

            if (! hash_equals((string) $audit->hash, $this->hash($payload))) {
                $masalah[] = [
                    'audit_id' => $audit->id,
                    'nilai_erapor_id' => $audit->nilai_erapor_id,
                    'jenis' => 'hash_tidak_cocok',
                    'diharapkan' => $this->hash($payload),
                    'tersimpan' => $audit->hash,
                ];
            }

            $hashTerakhir[$kunci] = $audit->hash;
        }

        return [
            'ok' => $masalah === [],
            'diperiksa' => $diperiksa,
            'masalah' => $masalah,
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(NilaiErapot $nilai): array
    {
        $atribut = $nilai->attributesToArray();
        $snapshot = [];

        foreach (self::KOLOM_SNAPSHOT as $kolom) {
            if (array_key_exists($kolom, $atribut)) {
                $snapshot[$kolom] = $atribut[$kolom];
            }
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function payload(
        ?int $nilaiErapotId,
        ?int $siswaId,
        ?int $guruId,
        string $aksi,
        ?string $statusLama,
        ?string $statusBaru,
        array $snapshot,
        ?string $prevHash,
        ?string $dicatatAt,
    ): array {
        return [
            'nilai_erapor_id' => $nilaiErapotId,
            'siswa_id' => $siswaId,
            'guru_id' => $guruId,
            'aksi' => $aksi,
            'status_lama' => $statusLama,
            'status_baru' => $statusBaru,
            'snapshot' => $snapshot,
            'prev_hash' => $prevHash,
            'dicatat_at' => $dicatatAt,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function hash(array $payload): string
    {
        $this->urutkan($payload);

        return hash('sha256', json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        ));
    }

    /** @param array<string, mixed> $data */
    private function urutkan(array &$data): void
    {
        ksort($data);

        foreach ($data as &$nilai) {
            if (is_array($nilai)) {
                $this->urutkan($nilai);
            }
        }
    }
}
