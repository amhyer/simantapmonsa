<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Catatan;
use App\Models\Kehadiran;
use App\Models\NilaiErapot;
use App\Models\SekolahSettings;
use App\Models\Siswa;
use App\Models\TanggalRapor;
use Illuminate\Http\Request;

class CetakController extends Controller
{
    private function daftarKelas()
    {
        return Siswa::whereNotNull('kelas')
            ->distinct()
            ->orderBy('kelas')
            ->pluck('kelas');
    }

    private function tahunDefault(): string
    {
        $tahun = (int) date('Y');

        return $tahun . '/' . ($tahun + 1);
    }

    private function rekapKehadiran(int $siswaId): array
    {
        $counts = Kehadiran::where('siswa_id', $siswaId)
            ->selectRaw('status, COUNT(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status')
            ->all();

        return [
            'S' => (int) ($counts['S'] ?? 0),
            'I' => (int) ($counts['I'] ?? 0),
            'A' => (int) ($counts['A'] ?? 0),
        ];
    }

    // ---------- Leger ----------

    public function leger(Request $request)
    {
        $kelasList = $this->daftarKelas();
        $validated = $request->validate([
            'kelas' => 'nullable|string|max:50',
            'semester' => 'nullable|in:Ganjil,Genap',
            'tahun_ajaran' => 'nullable|string|max:9',
        ]);
        $kelas = $validated['kelas'] ?? $kelasList->first();
        $semester = $validated['semester'] ?? 'Ganjil';
        $tahunAjaran = $validated['tahun_ajaran'] ?? $this->tahunDefault();

        $siswa = collect();
        $mapel = collect();
        $matriks = [];
        if ($kelas) {
            $siswa = Siswa::where('kelas', $kelas)
                ->where('aktif', true)
                ->orderBy('nama_peserta_didik')
                ->get(['id', 'nama_peserta_didik', 'nisn']);
            $nilai = NilaiErapot::where('kelas', $kelas)
                ->where('semester', $semester)
                ->where('tahun_ajaran', $tahunAjaran)
                ->whereNotNull('nilai_akhir')
                ->get(['siswa_id', 'mata_pelajaran', 'nilai_akhir']);
            $mapel = $nilai->pluck('mata_pelajaran')->unique()->sort()->values();
            foreach ($nilai as $n) {
                $matriks[$n->siswa_id][$n->mata_pelajaran] = $n->nilai_akhir;
            }
        }

        return view('admin.cetak.leger', compact(
            'kelasList', 'kelas', 'semester', 'tahunAjaran', 'siswa', 'mapel', 'matriks'
        ));
    }

    // ---------- Pelengkap ----------

    public function pelengkap(Request $request)
    {
        $kelasList = $this->daftarKelas();
        $validated = $request->validate([
            'kelas' => 'nullable|string|max:50',
        ]);
        $kelas = $validated['kelas'] ?? $kelasList->first();

        $siswa = collect();
        if ($kelas) {
            $siswa = Siswa::where('kelas', $kelas)
                ->where('aktif', true)
                ->orderBy('nama_peserta_didik')
                ->get();
            foreach ($siswa as $row) {
                $row->rekap_hadir = $this->rekapKehadiran($row->id);
                $row->catatan_terakhir = Catatan::where('siswa_id', $row->id)
                    ->latest('tanggal')
                    ->first(['catatan', 'tanggal']);
            }
        }

        return view('admin.cetak.pelengkap', compact('kelasList', 'kelas', 'siswa'));
    }

    // ---------- Nilai rapor ----------

    public function nilai(Request $request)
    {
        $kelasList = $this->daftarKelas();
        $validated = $request->validate([
            'kelas' => 'nullable|string|max:50',
            'semester' => 'nullable|in:Ganjil,Genap',
            'tahun_ajaran' => 'nullable|string|max:9',
        ]);
        $kelas = $validated['kelas'] ?? $kelasList->first();
        $semester = $validated['semester'] ?? 'Ganjil';
        $tahunAjaran = $validated['tahun_ajaran'] ?? $this->tahunDefault();

        $siswa = collect();
        if ($kelas) {
            $siswa = Siswa::where('kelas', $kelas)
                ->where('aktif', true)
                ->withCount(['nilaiErapot' => fn ($q) => $q->where('semester', $semester)->where('tahun_ajaran', $tahunAjaran)])
                ->orderBy('nama_peserta_didik')
                ->get();
        }

        return view('admin.cetak.nilai', compact(
            'kelasList', 'kelas', 'semester', 'tahunAjaran', 'siswa'
        ));
    }

    public function showNilai(Request $request, Siswa $siswa)
    {
        $validated = $request->validate([
            'semester' => 'nullable|in:Ganjil,Genap',
            'tahun_ajaran' => 'nullable|string|max:9',
        ]);
        $semester = $validated['semester'] ?? 'Ganjil';
        $tahunAjaran = $validated['tahun_ajaran'] ?? $this->tahunDefault();

        $nilai = NilaiErapot::where('siswa_id', $siswa->id)
            ->where('semester', $semester)
            ->where('tahun_ajaran', $tahunAjaran)
            ->orderBy('mata_pelajaran')
            ->get();
        $sekolah = SekolahSettings::first();
        $tanggalRapor = TanggalRapor::where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester)
            ->first();
        $hadir = $this->rekapKehadiran($siswa->id);
        $catatan = Catatan::where('siswa_id', $siswa->id)->latest('tanggal')->first(['catatan']);
        $rata = $nilai->whereNotNull('nilai_akhir')->count()
            ? round($nilai->whereNotNull('nilai_akhir')->avg('nilai_akhir'), 2)
            : null;

        return view('admin.cetak.nilai-show', compact(
            'siswa', 'nilai', 'sekolah', 'tanggalRapor', 'hadir', 'catatan',
            'rata', 'semester', 'tahunAjaran'
        ));
    }
}
