<?php

namespace App\Services\Presensi;

use App\Models\JadwalPelajaran;
use App\Models\Siswa;
use Illuminate\Support\Str;

class MapelAttendancePolicy
{
    public function requiresSeparateAttendance(string $subject): bool
    {
        $normalized = $this->normalize($subject);

        foreach (config('presensi.mapel_terpisah', []) as $keyword) {
            $needle = $this->normalize($keyword);
            $matches = in_array($needle, ['pai', 'pak'], true)
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
            default => null,
        };

        $result = [];
        foreach ($students as $student) {
            $agama = $this->normalize((string) ($student->agama ?? ''));
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
