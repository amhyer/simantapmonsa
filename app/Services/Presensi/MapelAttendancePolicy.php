<?php

namespace App\Services\Presensi;

use App\Models\JadwalPelajaran;
use App\Models\Siswa;
use Illuminate\Support\Str;

class MapelAttendancePolicy
{
    public function requiresSeparateAttendance(string $subject, ?JadwalPelajaran $jadwal = null): bool
    {
        if ($jadwal !== null) {
            $rombel = $jadwal->relationLoaded('rombel') ? $jadwal->rombel : $jadwal->rombel()->first();
            if ($rombel && $rombel->guru_id && (int) $jadwal->guru_id !== (int) $rombel->guru_id) {
                return true;
            }
        }

        $normalized = $this->normalize($subject);

        foreach (config('presensi.mapel_terpisah', []) as $keyword) {
            $needle = $this->normalize($keyword);
            $matches = in_array($needle, ['pai', 'pak', 'tik', 'btq'], true)
                ? (bool) preg_match('/(^| )' . preg_quote($needle, '/') . '($| )/', $normalized)
                : Str::contains($normalized, $needle);
            if ($matches) {
                return true;
            }
        }

        return false;
    }

    /**
     * Siswa yang agama-nya diketahui tidak ditampilkan pada mapel agama lain.
     * Data agama yang kosong tetap ditampilkan agar kesalahan impor tidak
     * menghilangkan siswa dari daftar guru tanpa mekanisme koreksi.
     */
    public function siswaSesuaiMapel(iterable $students, JadwalPelajaran $jadwal): array
    {
        $subject = $this->normalize($jadwal->mata_pelajaran);
        $religion = match (true) {
            Str::contains($subject, 'agama islam') || preg_match('/(^| )pai($| )/', $subject) => 'islam',
            Str::contains($subject, 'agama kristen') || preg_match('/(^| )pak($| )/', $subject) => 'kristen',
            Str::contains($subject, 'agama katolik') || Str::contains($subject, 'agama katholik') => 'katolik',
            Str::contains($subject, 'agama hindu') => 'hindu',
            Str::contains($subject, 'agama buddha') || Str::contains($subject, 'agama budha') => 'bud',
            Str::contains($subject, 'agama khonghucu') || Str::contains($subject, 'agama konghucu') => 'konghucu',
            default => null,
        };

        $result = [];
        foreach ($students as $student) {
            $agama = $this->normalize((string) ($student->agama ?? ''));
            $agama = str_replace(['katholik', 'khonghucu'], ['katolik', 'konghucu'], $agama);
            if ($religion && $agama !== '' && !Str::contains($agama, $religion)) {
                continue;
            }
            $result[] = $student;
        }

        return $result;
    }

    private function normalize(string $value): string
    {
        $value = Str::lower(Str::ascii($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}
