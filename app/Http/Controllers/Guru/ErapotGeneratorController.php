<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\NilaiErapot;
use App\Models\Kehadiran;
use App\Models\Catatan;
use App\Models\Dimensi;
use App\Models\PengaturanGuru;
use App\Models\OrangTuaSiswa;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ErapotGeneratorController extends Controller
{
    public function index()
    {
        $guru = auth()->user();
        $pengaturan = PengaturanGuru::where('guru_id', $guru->id)->first();
        $siswa = Siswa::where('guru_id', $guru->id)->orderBy('nama_peserta_didik')->get();

        return view('guru.erapor.index', compact('siswa', 'pengaturan'));
    }

    public function generatePdf($siswaId)
    {
        $guru = auth()->user();
        $siswa = Siswa::with(['orangTua'])->findOrFail($siswaId);

        if ($siswa->guru_id !== $guru->id) {
            abort(403);
        }

        $pengaturan = PengaturanGuru::where('guru_id', $guru->id)->first();
        $data = $this->prepareData($siswa, $pengaturan);

        $pdf = Pdf::loadView('guru.erapor.pdf', $data);
        $pdf->setPaper('A4', 'portrait');

        $filename = 'e-Rapor_' . str_replace(' ', '_', $siswa->nama_peserta_didik) . '_' . date('Y-m-d') . '.pdf';
        return $pdf->download($filename);
    }

    public function generateBulkPdf(Request $request)
    {
        $guru = auth()->user();
        $pengaturan = PengaturanGuru::where('guru_id', $guru->id)->first();

        $siswaIds = $request->input('siswa_ids', []);
        if (empty($siswaIds)) {
            $siswa = Siswa::where('guru_id', $guru->id)->orderBy('nama_peserta_didik')->get();
        } else {
            // Authorization: only return students belonging to this guru
            $siswa = Siswa::where('guru_id', $guru->id)->whereIn('id', $siswaIds)->get();
        }

        $allData = [];
        foreach ($siswa as $s) {
            $allData[] = $this->prepareData($s, $pengaturan);
        }

        $pdf = Pdf::loadView('guru.erapor.pdf-bulk', ['data' => $allData, 'pengaturan' => $pengaturan]);
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download('e-Rapor_Bulk_' . date('Y-m-d') . '.pdf');
    }

    public function exportExcel(Request $request)
    {
        $guru = auth()->user();
        $pengaturan = PengaturanGuru::where('guru_id', $guru->id)->first();
        $kelas = $request->get('kelas', $pengaturan->kelas ?? null);

        $query = Siswa::where('guru_id', $guru->id)->orderBy('nama_peserta_didik');
        if ($kelas) {
            $query->where('kelas', $kelas);
        }
        $siswa = $query->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('e-Rapor');

        $sheet->mergeCells('A1:L1');
        $sheet->setCellValue('A1', strtoupper($pengaturan->pengaturan['sekolah'] ?? 'SEKOLAH'));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');

        $sheet->mergeCells('A2:L2');
        $sheet->setCellValue('A2', 'LAPORAN HASIL BELAJAR (E-RAPOR)');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal('center');

        $sheet->mergeCells('A3:L3');
        $sheet->setCellValue('A3', 'Tahun Pelajaran: ' . ($pengaturan->tahun_pelajaran ?? '-') . ' | Semester: ' . ($pengaturan->semester ?? '-'));
        $sheet->getStyle('A3')->getAlignment()->setHorizontal('center');

        $headers = ['No', 'NISN', 'Nama Siswa', 'L/P', 'Kelas', 'Formatif', 'Sumatif', 'Sumatif Akhir', 'Nilai Akhir', 'Predikat', 'Deskripsi', 'Kehadiran (%)'];
        $sheet->fromArray($headers, null, 'A5');
        $sheet->getStyle('A5:L5')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A5:L5')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F3864');

        $row = 6;
        foreach ($siswa as $i => $s) {
            $nilai = NilaiErapot::where('siswa_id', $s->id)
                ->where('guru_id', $guru->id)
                ->where('semester', $pengaturan->semester)
                ->where('tahun_ajaran', $pengaturan->tahun_pelajaran)
                ->first();

            $kehadiran = Kehadiran::where('siswa_id', $s->id)->get();
            $persenHadir = $kehadiran->count() > 0
                ? round($kehadiran->where('status', 'H')->count() / $kehadiran->count() * 100, 1)
                : 0;

            $sheet->setCellValue('A' . $row, $i + 1);
            $sheet->setCellValue('B' . $row, $s->nisn ?? $s->nis);
            $sheet->setCellValue('C' . $row, $s->nama_peserta_didik);
            $sheet->setCellValue('D' . $row, $s->jenis_kelamin);
            $sheet->setCellValue('E' . $row, $s->kelas);
            $sheet->setCellValue('F' . $row, $nilai->nilai_formatif ?? 0);
            $sheet->setCellValue('G' . $row, $nilai->nilai_sumatif ?? 0);
            $sheet->setCellValue('H' . $row, $nilai->nilai_sumatif_akhir ?? 0);
            $sheet->setCellValue('I' . $row, $nilai->nilai_akhir ?? 0);
            $sheet->setCellValue('J' . $row, $nilai->predikat ?? '-');
            $sheet->setCellValue('K' . $row, $nilai->deskripsi_capaian ?? '-');
            $sheet->setCellValue('L' . $row, $persenHadir);
            $row++;
        }

        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'e-Rapor_Export_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $kelas ?? 'Semua') . '_' . date('Y-m-d') . '.xlsx';

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');

        return response()->stream(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    protected function prepareData(Siswa $siswa, $pengaturan): array
    {
        $guru = auth()->user();

        $nilaiMapel = NilaiErapot::where('siswa_id', $siswa->id)
            ->where('semester', $pengaturan->semester ?? 'Ganjil')
            ->where('tahun_ajaran', $pengaturan->tahun_pelajaran ?? date('Y') . '/' . (date('Y') + 1))
            ->get();

        $kehadiran = Kehadiran::where('siswa_id', $siswa->id)->get();
        $rekapHadir = [
            'hadir' => $kehadiran->where('status', 'H')->count(),
            'sakit' => $kehadiran->where('status', 'S')->count(),
            'izin' => $kehadiran->where('status', 'I')->count(),
            'alpa' => $kehadiran->where('status', 'A')->count(),
            'total' => $kehadiran->count(),
        ];

        $dimensi = Dimensi::where('siswa_id', $siswa->id)->orderBy('no_dimensi')->get();

        $catatan = Catatan::where('siswa_id', $siswa->id)
            ->orderBy('tanggal', 'desc')
            ->limit(5)
            ->get();

        $ayah = OrangTuaSiswa::where('siswa_id', $siswa->id)->where('tipe', 'ayah')->first();
        $ibu = OrangTuaSiswa::where('siswa_id', $siswa->id)->where('tipe', 'ibu')->first();

        return [
            'siswa' => $siswa,
            'pengaturan' => $pengaturan,
            'nilaiMapel' => $nilaiMapel,
            'rekapHadir' => $rekapHadir,
            'dimensi' => $dimensi,
            'catatan' => $catatan,
            'ayah' => $ayah,
            'ibu' => $ibu,
            'guru' => $guru,
        ];
    }
}
