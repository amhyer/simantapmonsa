<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Admin\CetakController;
use App\Http\Controllers\Controller;
use App\Models\Catatan;
use App\Models\Kehadiran;
use App\Models\KenaikanKelas;
use App\Models\NilaiErapot;
use App\Models\P5Penilaian;
use App\Models\Rombel;
use App\Models\SekolahSettings;
use App\Models\TanggalRapor;
use Illuminate\Http\Request;

class RaporController extends Controller
{
    use HasSiswaLookup;

    private function tahunDefault(): string
    {
        $tahun = (int) date('Y');

        return $tahun.'/'.($tahun + 1);
    }

    public function capaian(Request $request)
    {
        $siswa = $this->getSiswa();
        if (! $siswa) {
            return view('siswa.rapor.capaian', [
                'siswa' => null,
                'semester' => 'Ganjil',
                'tahunAjaran' => $this->tahunDefault(),
                'nilaiRapor' => collect(),
                'nilaiP5' => collect(),
                'ekskul' => null,
                'naikKelas' => null,
            ]);
        }

        $semester = $request->query('semester', 'Ganjil');
        $tahunAjaran = $request->query('tahun_ajaran', $this->tahunDefault());

        $nilaiRapor = NilaiErapot::where('siswa_id', $siswa->id)
            ->when($request->filled('semester'), fn ($q) => $q->where('semester', $semester))
            ->when($request->filled('tahun_ajaran'), fn ($q) => $q->where('tahun_ajaran', $tahunAjaran))
            ->orderBy('mata_pelajaran')
            ->get();

        $nilaiP5 = P5Penilaian::where('siswa_id', $siswa->id)
            ->when($request->filled('semester'), fn ($q) => $q->where('semester', $semester))
            ->when($request->filled('tahun_ajaran'), fn ($q) => $q->where('tahun_ajaran', $tahunAjaran))
            ->orderBy('tema')
            ->orderBy('dimensi')
            ->get();

        $ekskul = ($siswa->rekaman ?? [])['ekskul'] ?? null;
        $naikKelas = KenaikanKelas::where('siswa_id', $siswa->id)
            ->orderByDesc('id')
            ->first();

        return view('siswa.rapor.capaian', compact(
            'siswa', 'semester', 'tahunAjaran',
            'nilaiRapor', 'nilaiP5', 'ekskul', 'naikKelas'
        ));
    }

    public function unduh(Request $request)
    {
        $siswa = $this->getSiswa();
        $semester = $request->query('semester', 'Ganjil');
        $tahunAjaran = $request->query('tahun_ajaran', $this->tahunDefault());
        $sekolah = SekolahSettings::first();

        $jmlMapel = $siswa
            ? NilaiErapot::where('siswa_id', $siswa->id)->count()
            : 0;
        $jmlP5 = $siswa
            ? P5Penilaian::where('siswa_id', $siswa->id)->count()
            : 0;

        return view('siswa.rapor.unduh', compact(
            'siswa', 'semester', 'tahunAjaran', 'sekolah', 'jmlMapel', 'jmlP5'
        ));
    }

    public function cetakAkademik(Request $request)
    {
        $siswa = $this->getSiswa();
        abort_if(! $siswa, 404, 'Data siswa belum terhubung.');

        $semester = $request->query('semester', 'Ganjil');
        $tahunAjaran = $request->query('tahun_ajaran', $this->tahunDefault());

        $nilai = NilaiErapot::where('siswa_id', $siswa->id)
            ->when($request->filled('semester'), fn ($q) => $q->where('semester', $semester))
            ->when($request->filled('tahun_ajaran'), fn ($q) => $q->where('tahun_ajaran', $tahunAjaran))
            ->orderBy('mata_pelajaran')
            ->get();

        $sekolah = SekolahSettings::first();
        $tanggalRapor = TanggalRapor::where('tahun_ajaran', $tahunAjaran)
            ->whereRaw('LOWER(semester) = ?', [strtolower($semester)])
            ->first();

        $counts = Kehadiran::where('siswa_id', $siswa->id)
            ->selectRaw('status, COUNT(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status')
            ->all();
        $hadir = [
            'S' => (int) ($counts['S'] ?? 0),
            'I' => (int) ($counts['I'] ?? 0),
            'A' => (int) ($counts['A'] ?? 0),
        ];

        $catatan = Catatan::where('siswa_id', $siswa->id)->latest('tanggal')->first(['catatan']);
        $rata = $nilai->whereNotNull('nilai_akhir')->count()
            ? round($nilai->whereNotNull('nilai_akhir')->avg('nilai_akhir'), 2)
            : null;
        $raporConfig = CetakController::ambilConfigRapor($sekolah);
        $tampilanRapor = CetakController::ambilTampilanRapor($sekolah);
        $ttdWaliMap = ($sekolah?->pengaturan ?? [])['ttd_wali_kelas'] ?? [];
        $waliGuruId = $siswa->guru_id
            ?? ($siswa->kelas ? Rombel::where('nama_rombel', $siswa->kelas)->value('guru_id') : null);
        $ttdWaliUri = $waliGuruId ? ($ttdWaliMap[(string) $waliGuruId] ?? null) : null;
        $naikKelas = KenaikanKelas::where('siswa_id', $siswa->id)->latest('id')->first();

        return view('admin.cetak.nilai-show', compact(
            'siswa', 'nilai', 'sekolah', 'tanggalRapor', 'hadir', 'catatan',
            'rata', 'semester', 'tahunAjaran', 'raporConfig', 'tampilanRapor', 'ttdWaliUri', 'naikKelas'
        ));
    }

    public function cetakP5(Request $request)
    {
        $siswa = $this->getSiswa();
        abort_if(! $siswa, 404, 'Data siswa belum terhubung.');

        $semester = $request->query('semester', 'Ganjil');
        $tahunAjaran = $request->query('tahun_ajaran', $this->tahunDefault());
        $sekolah = SekolahSettings::first();
        $raporConfig = CetakController::ambilConfigRapor($sekolah);
        $penilaian = P5Penilaian::where('siswa_id', $siswa->id)
            ->when($request->filled('semester'), fn ($q) => $q->where('semester', $semester))
            ->when($request->filled('tahun_ajaran'), fn ($q) => $q->where('tahun_ajaran', $tahunAjaran))
            ->orderBy('tema')
            ->orderBy('dimensi')
            ->get();

        return view('admin.cetak.p5bk-show', compact(
            'siswa', 'sekolah', 'raporConfig', 'penilaian', 'semester', 'tahunAjaran'
        ));
    }

    public function cetakPelengkap()
    {
        $siswa = $this->getSiswa();
        abort_if(! $siswa, 404, 'Data siswa belum terhubung.');

        $sekolah = SekolahSettings::first();

        return view('siswa.rapor.pelengkap-show', compact('siswa', 'sekolah'));
    }
}
