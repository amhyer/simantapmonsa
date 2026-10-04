<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\HasilKuis;
use App\Models\Kuis;
use App\Models\Siswa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KuisController extends Controller
{
    use HasSiswaLookup;

    // Kelonggaran latensi jaringan saat auto-submit di detik terakhir.
    private const TOLERANSI_DETIK = 60;

    public function index()
    {
        $siswa = $this->getSiswa();

        if (!$siswa) {
            return view('siswa.kuis.index', ['kuis' => collect(), 'hasilKuis' => collect(), 'siswa' => null]);
        }

        $kuis = Kuis::where('guru_id', $siswa->guru_id)
            ->where('aktif', true)
            ->orderBy('tanggal', 'desc')
            ->get();

        $hasilKuis = HasilKuis::where('siswa_id', $siswa->id)->get();

        return view('siswa.kuis.index', compact('kuis', 'hasilKuis', 'siswa'));
    }

    public function show($kuis)
    {
        $siswa = $this->getSiswa();

        if (!$siswa) {
            abort(404);
        }

        $kuis = Kuis::where('id', $kuis)
            ->where('guru_id', $siswa->guru_id)
            ->where('aktif', true)
            ->firstOrFail();

        $sudahMengerjakan = HasilKuis::where('kuis_id', $kuis->id)
            ->where('siswa_id', $siswa->id)
            ->first();

        $sisaDetik = null;
        if (!$sudahMengerjakan) {
            $mulai = $this->waktuMulai($kuis, $siswa);
            if ($kuis->batas_waktu > 0) {
                $sisaDetik = max(0, $kuis->batas_waktu * 60 - (int) $mulai->diffInSeconds(now()));
            }
        }

        return view('siswa.kuis.show', compact('kuis', 'siswa', 'sudahMengerjakan', 'sisaDetik'));
    }

    public function submit(Request $request, $kuis)
    {
        $siswa = $this->getSiswa();

        if (!$siswa) {
            abort(404);
        }

        $kuis = Kuis::where('id', $kuis)
            ->where('guru_id', $siswa->guru_id)
            ->where('aktif', true)
            ->firstOrFail();

        $sudahMengerjakan = HasilKuis::where('kuis_id', $kuis->id)
            ->where('siswa_id', $siswa->id)
            ->first();

        if ($sudahMengerjakan) {
            return redirect()->route('siswa.kuis.index')
                ->with('error', 'Anda sudah mengerjakan kuis ini.');
        }

        $validated = $request->validate([
            'jawaban' => 'nullable|array',
        ]);

        $soal = $kuis->soal ?? [];
        $benar = 0;
        $total = count($soal);
        $jawabanDetail = [];

        foreach ($soal as $i => $item) {
            $jawabanSiswa = $validated['jawaban'][$i] ?? null;
            $isBenar = ($jawabanSiswa === ($item['kunci'] ?? null));
            if ($isBenar) $benar++;
            $jawabanDetail[] = [
                'no' => $i + 1,
                'jawaban' => $jawabanSiswa,
                'kunci' => $item['kunci'] ?? null,
                'benar' => $isBenar,
            ];
        }

        $skor = $total > 0 ? round(($benar / $total) * 100) : 0;
        $tuntas = $skor >= $kuis->kkm;

        $mulai = session($this->kunciSesiMulai($kuis, $siswa));
        $durasi = $mulai ? (int) Carbon::parse($mulai)->diffInSeconds(now()) : (int) $request->get('durasi', 0);
        if ($mulai && $kuis->batas_waktu > 0 && $durasi > $kuis->batas_waktu * 60 + self::TOLERANSI_DETIK) {
            return redirect()->route('siswa.kuis.index')
                ->with('error', 'Waktu pengerjaan kuis sudah habis. Jawaban tidak dapat dikirim.');
        }

        DB::transaction(function () use ($kuis, $siswa, $benar, $total, $skor, $tuntas, $durasi, $jawabanDetail) {
            $exists = HasilKuis::where('kuis_id', $kuis->id)
                ->where('siswa_id', $siswa->id)
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                return;
            }

            HasilKuis::create([
                'uuid' => Str::uuid(),
                'guru_id' => $siswa->guru_id,
                'kuis_id' => $kuis->id,
                'siswa_id' => $siswa->id,
                'nama_guru' => $kuis->nama_guru,
                'judul_kuis' => $kuis->judul,
                'nama_siswa' => $siswa->nama_peserta_didik,
                'waktu' => now(),
                'benar' => $benar,
                'total' => $total,
                'skor' => $skor,
                'tuntas' => $tuntas,
                'durasi' => $durasi,
                'sumber_soal' => $kuis->sumber,
                'diisi_oleh' => 'siswa',
                'jawaban' => $jawabanDetail,
            ]);
        });

        session()->forget($this->kunciSesiMulai($kuis, $siswa));

        return redirect()->route('siswa.kuis.index')
            ->with('success', 'Kuis berhasil dikirim. Skor Anda: ' . $skor);
    }

    private function kunciSesiMulai(Kuis $kuis, Siswa $siswa): string
    {
        return "kuis_mulai.{$kuis->id}.{$siswa->id}";
    }

    private function waktuMulai(Kuis $kuis, Siswa $siswa): Carbon
    {
        $kunci = $this->kunciSesiMulai($kuis, $siswa);
        if (!session()->has($kunci)) {
            session([$kunci => now()->toIso8601String()]);
        }

        return Carbon::parse(session($kunci));
    }
}
