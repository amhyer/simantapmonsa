<?php

namespace App\Http\Controllers\Kepsek;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Siswa;
use App\Models\RingkasanGuru;
use App\Services\NilaiService;
use Illuminate\Http\Request;

class RekapController extends Controller
{
    protected $nilaiService;

    public function __construct(NilaiService $nilaiService)
    {
        $this->nilaiService = $nilaiService;
    }

    public function index()
    {
        $ringkasan = RingkasanGuru::with('guru')->orderBy('nama_guru')->get();
        $totalSiswa = $ringkasan->sum('jumlah_siswa');
        $rataRata = $ringkasan->avg('rata_rata') ?: 0;
        $ketuntasan = $ringkasan->avg('ketuntasan_persen') ?: 0;
        $kehadiran = $ringkasan->avg('kehadiran_persen') ?: 0;
        $guruTanpaData = User::where('peran', 'guru')->whereDoesntHave('ringkasanGuru')->get();

        return view('kepsek.rekap.index', compact(
            'ringkasan', 'totalSiswa', 'rataRata', 'ketuntasan', 'kehadiran', 'guruTanpaData'
        ));
    }

    public function detail($guruId)
    {
        $ringkasan = RingkasanGuru::with('guru')->where('guru_id', $guruId)->firstOrFail();
        $siswa = Siswa::where('guru_id', $guruId)->where('aktif', true)->get();

        $dataSiswa = [];
        foreach ($siswa as $s) {
            $na = $this->nilaiService->hitungNilaiAkhir($s->id);
            $predikat = $na['nilai_akhir'] > 0 
                ? $this->nilaiService->getPredikat($na['nilai_akhir'], $ringkasan->kkm) 
                : null;
            $dataSiswa[] = ['siswa' => $s, 'nilai' => $na['nilai_akhir'], 'predikat' => $predikat];
        }

        return view('kepsek.rekap.detail', compact('ringkasan', 'dataSiswa'));
    }

    public function unduh()
    {
        $ringkasan = RingkasanGuru::with('guru')->orderBy('nama_guru')->get();
        $csv = "Guru,Mata Pelajaran,Kelas,Jumlah Siswa,Rata-rata,Ketuntasan (%),Kehadiran (%)\n";
        foreach ($ringkasan as $r) {
            $csv .= "{$r->nama_guru},{$r->mata_pelajaran},{$r->kelas},{$r->jumlah_siswa},{$r->rata_rata},{$r->ketuntasan_persen},{$r->kehadiran_persen}\n";
        }
        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="rekap-sekolah-' . date('Y-m-d') . '.csv"');
    }
}
