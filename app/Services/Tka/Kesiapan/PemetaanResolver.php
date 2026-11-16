<?php

namespace App\Services\Tka\Kesiapan;

use App\Models\Tka\Kesiapan\Pemetaan;
use Illuminate\Support\Collection;

/**
 * Memetakan label sumber (mata pelajaran / label indikator) ke kode indikator pemantauan.
 *
 * Urutan: (1) pemetaan sekolah (tabel tka_kesiapan_pemetaan, label persis), (2) alias pada
 * config/tka_kesiapan.php (pencocokan frasa utuh, alias terpanjang lebih dulu).
 * Label yang tidak dikenali menghasilkan null → bukti ditandai "tidak terpetakan"
 * (tidak ikut dihitung, tetapi dilaporkan di kelengkapan data).
 */
class PemetaanResolver
{
    /** @var array<string, string> */
    private array $sekolah = [];

    /** @var array<int, array{alias: string, kode: string}> */
    private array $alias = [];

    public function __construct(?int $tenantId = null)
    {
        $rows = Pemetaan::query()
            ->withoutGlobalScopes()
            ->where('aktif', true)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId), fn ($q) => $q->whereNull('tenant_id'))
            ->get(['label_sumber', 'indikator_kode']);

        foreach ($rows as $r) {
            $this->sekolah[self::norm($r->label_sumber)] = $r->indikator_kode;
        }

        $daftar = [];
        foreach ((array) config('tka_kesiapan.indikator', []) as $kode => $def) {
            foreach ((array) ($def['alias'] ?? []) as $a) {
                $daftar[] = ['alias' => self::norm($a), 'kode' => (string) $kode];
            }
        }
        usort($daftar, fn ($a, $b) => strlen($b['alias']) <=> strlen($a['alias']));
        $this->alias = $daftar;
    }

    public static function norm(?string $label): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower((string) $label)));
    }

    public function resolve(?string $label): ?string
    {
        $n = self::norm($label);
        if ($n === '') {
            return null;
        }

        if (isset($this->sekolah[$n])) {
            return $this->kodeValid($this->sekolah[$n]);
        }

        foreach ($this->alias as $a) {
            if ($a['alias'] !== '' && preg_match('/(^|[^a-z0-9])'.preg_quote($a['alias'], '/').'($|[^a-z0-9])/u', $n)) {
                return $a['kode'];
            }
        }

        return null;
    }

    private function kodeValid(string $kode): ?string
    {
        return array_key_exists($kode, (array) config('tka_kesiapan.indikator', [])) ? $kode : null;
    }

    /** @return Collection<int, string> */
    public function kodeIndikator(): Collection
    {
        return collect(array_keys((array) config('tka_kesiapan.indikator', [])));
    }
}
