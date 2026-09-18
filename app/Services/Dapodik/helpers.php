<?php

namespace App\Services\Dapodik;

function normalize_string(?string $s): ?string
{
    if ($s === null) {
        return null;
    }

    $trimmed = trim($s);

    if ($trimmed === '') {
        return null;
    }

    return preg_replace('/\s+/', ' ', $trimmed);
}

function map_gender(?string $jk): ?string
{
    if ($jk === null) {
        return null;
    }

    $lower = mb_strtolower($jk);

    if (str_contains($lower, 'laki') || $lower === 'l') {
        return 'LAKI_LAKI';
    }

    if (str_contains($lower, 'perempuan') || $lower === 'p') {
        return 'PEREMPUAN';
    }

    return null;
}

function parse_date(?string $s): ?string
{
    if ($s === null || trim($s) === '') {
        return null;
    }

    $trimmed = trim($s);

    $formats = ['Y-m-d', 'd/m/Y', 'Y-m-d H:i:s'];

    foreach ($formats as $format) {
        $date = \DateTime::createFromFormat($format, $trimmed);

        if ($date !== false && $date->format($format) === $trimmed) {
            return $date->format('Y-m-d');
        }
    }

    $timestamp = strtotime($trimmed);

    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }

    return null;
}

function resolve_nis(?string $nipd, string $nama, string $id, int $kelas): string
{
    $trimmed = normalize_string($nipd);

    if ($trimmed !== null && $trimmed !== '') {
        return $trimmed;
    }

    $year = date('Y');
    $classStr = (string) $kelas;
    $hashInput = $nama . '|' . $id;
    $hash = hash_deterministic($hashInput, 9999);

    return str_pad($year, 4, '0', STR_PAD_LEFT)
        . str_pad($classStr, 2, '0', STR_PAD_LEFT)
        . str_pad((string) $hash, 4, '0', STR_PAD_LEFT);
}

function parse_grade_from_rombel(?string $nama_rombel): string
{
    if ($nama_rombel === null || trim($nama_rombel) === '') {
        return '1';
    }

    $romanMap = [
        'VIII' => '8', 'VII' => '7', 'VI' => '6',
        'III' => '3', 'IV' => '4', 'II' => '2',
        'IX' => '9', 'V' => '5', 'I' => '1',
    ];

    $upper = mb_strtoupper(trim($nama_rombel));

    foreach ($romanMap as $roman => $number) {
        if (str_starts_with($upper, $roman)) {
            return $number;
        }
    }

    if (preg_match('/(\d+)/', $nama_rombel, $matches)) {
        return $matches[1];
    }

    return '1';
}

function combine_parent_name(?string $ayah, ?string $ibu): ?string
{
    $ayahNorm = normalize_string($ayah);
    $ibuNorm = normalize_string($ibu);

    if ($ayahNorm === null && $ibuNorm === null) {
        return null;
    }

    $parts = array_filter([$ayahNorm, $ibuNorm]);

    return implode(' / ', $parts);
}

function current_academic_year(): string
{
    $year = (int) date('Y');
    $month = (int) date('n');

    if ($month >= 7) {
        return $year . '/' . ($year + 1);
    }

    return ($year - 1) . '/' . $year;
}

function semester_to_tahun_ajaran(string $semester_id): string
{
    $year = (int) mb_substr($semester_id, 0, 4);
    $semesterDigit = (int) mb_substr($semester_id, -1);

    if ($semesterDigit === 2) {
        return ($year - 1) . '/' . $year;
    }

    return $year . '/' . ($year + 1);
}

function hash_deterministic(string $input, int $mod = 9999): int
{
    $hash = crc32($input);

    return abs($hash) % $mod + 1;
}
