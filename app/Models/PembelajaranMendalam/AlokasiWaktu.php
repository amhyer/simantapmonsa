<?php

namespace App\Models\PembelajaranMendalam;

use App\Modules\Tenant\Traits\BelongsToTenant;
use App\Support\SchoolLevel;
use Illuminate\Database\Eloquent\Model;

class AlokasiWaktu extends Model
{
    use BelongsToTenant;

    protected $table = 'pm_alokasi_waktu';

    /**
     * Standar struktur kurikulum SD (JP per minggu) mengacu Permendikdasmen No. 13 Tahun 2025.
     * Format: nama_mapel => [tingkat => [jp_intra, jp_kokurikuler]].
     * Minggu efektif standar: 36 minggu/tahun untuk Kelas I-V, 32 minggu/tahun untuk Kelas VI.
     *
     * @var array<string, array<int, array{0: int, 1: int}>>
     */
    public const STANDAR_PERMENDIKDASMEN_2025 = [
        'Pendidikan Agama dan Budi Pekerti' => [
            1 => [3, 1], 2 => [3, 1], 3 => [3, 1], 4 => [3, 1], 5 => [3, 1], 6 => [3, 1],
        ],
        'Pendidikan Pancasila' => [
            1 => [4, 1], 2 => [4, 1], 3 => [4, 1], 4 => [4, 1], 5 => [4, 1], 6 => [4, 1],
        ],
        'Bahasa Indonesia' => [
            1 => [6, 2], 2 => [7, 2], 3 => [6, 1], 4 => [6, 1], 5 => [6, 1], 6 => [6, 1],
        ],
        'Matematika' => [
            1 => [4, 1], 2 => [5, 1], 3 => [5, 1], 4 => [5, 1], 5 => [5, 1], 6 => [5, 1],
        ],
        'Ilmu Pengetahuan Alam dan Sosial (IPAS)' => [
            1 => [0, 0], 2 => [0, 0], 3 => [5, 1], 4 => [5, 1], 5 => [5, 1], 6 => [5, 1],
        ],
        'Pendidikan Jasmani, Olahraga, dan Kesehatan (PJOK)' => [
            1 => [3, 1], 2 => [3, 1], 3 => [3, 1], 4 => [3, 1], 5 => [3, 1], 6 => [3, 1],
        ],
        'Seni dan Budaya' => [
            1 => [3, 1], 2 => [3, 1], 3 => [3, 1], 4 => [3, 1], 5 => [3, 1], 6 => [3, 1],
        ],
        'Bahasa Inggris' => [
            1 => [0, 0], 2 => [0, 0], 3 => [2, 0], 4 => [2, 0], 5 => [2, 0], 6 => [2, 0],
        ],
        'Muatan Lokal' => [
            1 => [2, 0], 2 => [2, 0], 3 => [2, 0], 4 => [2, 0], 5 => [2, 0], 6 => [2, 0],
        ],
    ];

    protected $fillable = [
        'tenant_id',
        'mata_pelajaran_id',
        'nama_mapel',
        'tingkat',
        'jp_intra',
        'jp_kokurikuler',
        'tahun_ajaran',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'tingkat' => 'integer',
            'jp_intra' => 'integer',
            'jp_kokurikuler' => 'integer',
        ];
    }

    /** @var array<string, array<int, array{0: int, 1: int}>> */
    public const STANDAR_SMP_2025 = [
        'Pendidikan Agama dan Budi Pekerti' => [7 => [2, 1], 8 => [2, 1], 9 => [2, 1]],
        'Pendidikan Pancasila' => [7 => [2, 1], 8 => [2, 1], 9 => [2, 1]],
        'Bahasa Indonesia' => [7 => [5, 1], 8 => [5, 1], 9 => [5, 1]],
        'Matematika' => [7 => [4, 1], 8 => [4, 1], 9 => [4, 1]],
        'Ilmu Pengetahuan Alam (IPA)' => [7 => [4, 1], 8 => [4, 1], 9 => [4, 1]],
        'Ilmu Pengetahuan Sosial (IPS)' => [7 => [3, 1], 8 => [3, 1], 9 => [3, 1]],
        'Bahasa Inggris' => [7 => [3, 1], 8 => [3, 1], 9 => [3, 1]],
        'Pendidikan Jasmani, Olahraga, dan Kesehatan (PJOK)' => [7 => [2, 1], 8 => [2, 1], 9 => [2, 1]],
        'Informatika' => [7 => [2, 1], 8 => [2, 1], 9 => [2, 1]],
        'Seni dan Prakarya' => [7 => [2, 1], 8 => [2, 1], 9 => [2, 1]],
        'Muatan Lokal' => [7 => [2, 0], 8 => [2, 0], 9 => [2, 0]],
    ];

    /** @var array<string, array<int, array{0: int, 1: int}>> */
    public const STANDAR_SMA_SMK_2025 = [
        'Pendidikan Agama dan Budi Pekerti' => [10 => [2, 1], 11 => [2, 1], 12 => [2, 1]],
        'Pendidikan Pancasila' => [10 => [2, 0], 11 => [2, 0], 12 => [2, 0]],
        'Bahasa Indonesia' => [10 => [3, 1], 11 => [3, 1], 12 => [3, 1]],
        'Matematika' => [10 => [3, 1], 11 => [3, 1], 12 => [3, 1]],
        'Bahasa Inggris' => [10 => [2, 1], 11 => [2, 1], 12 => [2, 1]],
        'Sejarah' => [10 => [2, 1], 11 => [2, 0], 12 => [2, 0]],
        'Pendidikan Jasmani, Olahraga, dan Kesehatan (PJOK)' => [10 => [2, 1], 11 => [2, 1], 12 => [2, 1]],
        'Seni dan Budaya' => [10 => [2, 0], 11 => [2, 0], 12 => [2, 0]],
        'Informatika' => [10 => [2, 0], 11 => [2, 0], 12 => [2, 0]],
        'Muatan Lokal' => [10 => [2, 0], 11 => [2, 0], 12 => [2, 0]],
    ];

    /**
     * @return array<string, array<int, array{0: int, 1: int}>>
     */
    public static function standarUntukJenjang(?string $jenjang = null): array
    {
        return match (SchoolLevel::normalize($jenjang, 'SD')) {
            'SMP', 'MTs' => self::STANDAR_SMP_2025,
            'SMA', 'MA', 'SMK' => self::STANDAR_SMA_SMK_2025,
            default => self::STANDAR_PERMENDIKDASMEN_2025,
        };
    }

    public static function mingguPerTahun(int $tingkat): int
    {
        return SchoolLevel::mingguEfektifPerTahun($tingkat);
    }
}
