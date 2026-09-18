<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\NilaiErapot;
use App\Models\PengaturanGuru;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use Illuminate\Support\Facades\DB;

class ImportErapotController extends Controller
{
    public function downloadTemplate(Request $request)
    {
        $kelas = $request->get('kelas');
        $mapel = $request->get('mapel');
        $guru = auth()->user();

        $siswa = Siswa::where('guru_id', $guru->id)
            ->where('kelas', $kelas)
            ->where('aktif', true)
            ->orderBy('nama_peserta_didik')
            ->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['No', 'NISN', 'Nama Siswa', 'Nilai Formatif', 'Nilai Sumatif', 'Nilai Sumatif Akhir'];
        $sheet->fromArray($headers, null, 'A1');

        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->getStyle('A1:F1')->getFont()->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F3864');

        $row = 2;
        foreach ($siswa as $i => $s) {
            $sheet->setCellValue('A' . $row, $i + 1);
            $sheet->setCellValue('B' . $row, $s->nisn ?? $s->nis);
            $sheet->setCellValue('C' . $row, $s->nama_peserta_didik);
            $row++;
        }

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = "Template_Nilai_{$mapel}_{$kelas}.xlsx";

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');

        return response()->stream(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:5120',
            'kelas' => 'required|string',
            'mapel' => 'required|string',
            'semester' => 'required|in:Ganjil,Genap',
            'tahun_ajaran' => 'required|string',
        ]);

        $guru = auth()->user();
        $pengaturan = PengaturanGuru::where('guru_id', $guru->id)->first();
        $kkm = $pengaturan->kkm ?? 70;
        $bobot = ($pengaturan->pengaturan ?? [])['bobot_erapor'] ?? ['formatif' => 0.30, 'sumatif' => 0.40, 'sumatif_akhir' => 0.30];

        try {
            $file = $request->file('file');
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
            array_shift($rows);

            DB::beginTransaction();
            $berhasil = 0;
            $errors = [];

            foreach ($rows as $index => $row) {
                $nisn = trim($row[1] ?? '');
                if (empty($nisn)) continue;

                $siswa = Siswa::where('guru_id', $guru->id)
                    ->where(function ($q) use ($nisn) {
                        $q->where('nisn', $nisn)
                          ->orWhere('nis', $nisn);
                    })
                    ->first();

                if (!$siswa) {
                    $errors[] = "Baris " . ($index + 2) . ": NISN {$nisn} tidak ditemukan.";
                    continue;
                }

                $nilai = NilaiErapot::updateOrCreate(
                    [
                        'siswa_id' => $siswa->id,
                        'mata_pelajaran' => $request->mapel,
                        'semester' => $request->semester,
                        'tahun_ajaran' => $request->tahun_ajaran,
                    ],
                    [
                        'guru_id' => $guru->id,
                        'kelas' => $request->kelas,
                        'nilai_formatif' => is_numeric($row[3] ?? null) ? floatval($row[3]) : 0,
                        'nilai_sumatif' => is_numeric($row[4] ?? null) ? floatval($row[4]) : 0,
                        'nilai_sumatif_akhir' => is_numeric($row[5] ?? null) ? floatval($row[5]) : 0,
                    ]
                );

                $nilaiAkhir = $nilai->hitungNilaiAkhir($bobot['formatif'], $bobot['sumatif'], $bobot['sumatif_akhir']);
                $nilai->update([
                    'nilai_akhir' => $nilaiAkhir,
                    'predikat' => $nilai->generatePredikat($kkm),
                    'deskripsi_capaian' => $nilai->generateDeskripsi(),
                ]);
                $berhasil++;
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => "Berhasil import {$berhasil} nilai.", 'berhasil' => $berhasil, 'errors' => $errors]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()], 500);
        }
    }

    public function export(Request $request)
    {
        $guru = auth()->user();
        $nilai = NilaiErapot::with('siswa')
            ->where('guru_id', $guru->id)
            ->where('kelas', $request->get('kelas'))
            ->where('mata_pelajaran', $request->get('mapel'))
            ->where('semester', $request->get('semester'))
            ->where('tahun_ajaran', $request->get('tahun_ajaran'))
            ->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['No', 'NISN', 'Nama Siswa', 'Formatif', 'Sumatif', 'Sumatif Akhir', 'Nilai Akhir', 'Predikat', 'Deskripsi'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);

        $row = 2;
        foreach ($nilai as $i => $n) {
            $sheet->setCellValue('A' . $row, $i + 1);
            $sheet->setCellValue('B' . $row, $n->siswa->nisn ?? $n->siswa->nis);
            $sheet->setCellValue('C' . $row, $n->siswa->nama_peserta_didik);
            $sheet->setCellValue('D' . $row, $n->nilai_formatif);
            $sheet->setCellValue('E' . $row, $n->nilai_sumatif);
            $sheet->setCellValue('F' . $row, $n->nilai_sumatif_akhir);
            $sheet->setCellValue('G' . $row, $n->nilai_akhir);
            $sheet->setCellValue('H' . $row, $n->predikat);
            $sheet->setCellValue('I' . $row, $n->deskripsi_capaian);
            $row++;
        }

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $mapel = $request->get('mapel');
        $kelas = $request->get('kelas');
        $semester = $request->get('semester');
        $filename = "Nilai_{$mapel}_{$kelas}_{$semester}.xlsx";

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');

        return response()->stream(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
