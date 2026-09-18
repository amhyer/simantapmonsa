<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\NilaiErapot;
use App\Models\PengaturanGuru;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NilaiErapotController extends Controller
{
    public function index(Request $request)
    {
        $guru = auth()->user();
        $pengaturan = PengaturanGuru::where('guru_id', $guru->id)->first();
        $kelas = $request->get('kelas', optional($pengaturan)->kelas ?? 'V A');
        $mapel = $request->get('mapel', optional($pengaturan)->mata_pelajaran ?? 'Matematika');
        $semester = $request->get('semester', optional($pengaturan)->semester ?? 'Ganjil');
        $tahunAjaran = $request->get('tahun_ajaran', optional($pengaturan)->tahun_pelajaran ?? date('Y') . '/' . (date('Y') + 1));

        $siswa = Siswa::where('guru_id', $guru->id)
            ->where('kelas', $kelas)
            ->where('aktif', true)
            ->orderBy('nama_peserta_didik')
            ->get();

        $nilaiExisting = NilaiErapot::where('guru_id', $guru->id)
            ->where('kelas', $kelas)
            ->where('mata_pelajaran', $mapel)
            ->where('semester', $semester)
            ->where('tahun_ajaran', $tahunAjaran)
            ->get()
            ->keyBy('siswa_id');

        return view('guru.nilai-erapor.index', compact(
            'siswa', 'nilaiExisting', 'kelas', 'mapel',
            'semester', 'tahunAjaran', 'pengaturan'
        ));
    }

    public function simpan(Request $request)
    {
        $request->validate([
            'nilai' => 'required|array',
            'nilai.*.siswa_id' => 'required|exists:siswa,id',
            'nilai.*.formatif' => 'nullable|numeric|min:0|max:100',
            'nilai.*.sumatif' => 'nullable|numeric|min:0|max:100',
            'nilai.*.sumatif_akhir' => 'nullable|numeric|min:0|max:100',
            'kelas' => 'required|string',
            'mapel' => 'required|string',
            'semester' => 'required|in:Ganjil,Genap',
            'tahun_ajaran' => 'required|string',
        ]);

        $guru = auth()->user();
        $pengaturan = PengaturanGuru::where('guru_id', $guru->id)->first();
        $kkm = $pengaturan->kkm ?? 70;
        $bobot = ($pengaturan->pengaturan ?? [])['bobot_erapor'] ?? ['formatif' => 0.30, 'sumatif' => 0.40, 'sumatif_akhir' => 0.30];

        DB::beginTransaction();
        try {
            $berhasil = 0;
            foreach ($request->nilai as $item) {
                if (empty($item['formatif']) && empty($item['sumatif']) && empty($item['sumatif_akhir'])) continue;

                $nilai = NilaiErapot::updateOrCreate(
                    [
                        'siswa_id' => $item['siswa_id'],
                        'mata_pelajaran' => $request->mapel,
                        'semester' => $request->semester,
                        'tahun_ajaran' => $request->tahun_ajaran,
                    ],
                    [
                        'guru_id' => $guru->id,
                        'kelas' => $request->kelas,
                        'nilai_formatif' => $item['formatif'] ?? 0,
                        'nilai_sumatif' => $item['sumatif'] ?? 0,
                        'nilai_sumatif_akhir' => $item['sumatif_akhir'] ?? 0,
                    ]
                );

                $nilaiAkhir = $nilai->hitungNilaiAkhir($bobot['formatif'], $bobot['sumatif'], $bobot['sumatif_akhir']);
                $predikat = $nilai->generatePredikat($kkm);
                $nilai->update([
                    'nilai_akhir' => $nilaiAkhir,
                    'predikat' => $predikat,
                ]);
                $nilai->deskripsi_capaian = $nilai->generateDeskripsi();
                $nilai->save();
                $berhasil++;
            }
            DB::commit();
            return response()->json(['success' => true, 'message' => "{$berhasil} nilai berhasil disimpan.", 'count' => $berhasil]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal menyimpan nilai erapor: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data nilai.'], 500);
        }
    }

    public function autoSave(Request $request)
    {
        $request->validate([
            'siswa_id' => 'required|exists:siswa,id',
            'field' => 'required|in:formatif,sumatif,sumatif_akhir',
            'value' => 'nullable|numeric|min:0|max:100',
            'kelas' => 'required|string',
            'mapel' => 'required|string',
            'semester' => 'required|string',
            'tahun_ajaran' => 'required|string',
        ]);

        $guru = auth()->user();
        $pengaturan = PengaturanGuru::where('guru_id', $guru->id)->first();
        $kkm = $pengaturan->kkm ?? 70;
        $bobot = ($pengaturan->pengaturan ?? [])['bobot_erapor'] ?? ['formatif' => 0.30, 'sumatif' => 0.40, 'sumatif_akhir' => 0.30];

        $nilai = NilaiErapot::firstOrNew([
            'siswa_id' => $request->siswa_id,
            'mata_pelajaran' => $request->mapel,
            'semester' => $request->semester,
            'tahun_ajaran' => $request->tahun_ajaran,
            'guru_id' => $guru->id,
        ]);

        $nilai->guru_id = $guru->id;
        $nilai->kelas = $request->kelas;
        $field = 'nilai_' . $request->field;
        $nilai->$field = $request->value ?? 0;
        $nilai->save();

        $nilaiAkhir = $nilai->hitungNilaiAkhir($bobot['formatif'], $bobot['sumatif'], $bobot['sumatif_akhir']);
        $predikat = $nilai->generatePredikat($kkm);
        $nilai->update([
            'nilai_akhir' => $nilaiAkhir,
            'predikat' => $predikat,
        ]);
        $nilai->deskripsi_capaian = $nilai->generateDeskripsi();
        $nilai->save();

        return response()->json(['success' => true, 'nilai_akhir' => $nilaiAkhir, 'predikat' => $predikat, 'deskripsi' => $nilai->deskripsi_capaian]);
    }

    public function deskripsi(Request $request, $siswaId)
    {
        $guru = auth()->user();
        $siswa = Siswa::find($siswaId);
        if (!$siswa) return response()->json(['success' => false]);

        $nilai = NilaiErapot::where('siswa_id', $siswaId)
            ->where('guru_id', $guru->id)
            ->where('mata_pelajaran', $request->mapel)
            ->where('semester', $request->semester)
            ->where('tahun_ajaran', $request->tahun_ajaran)
            ->first();

        if (!$nilai) return response()->json(['success' => false, 'message' => 'Belum ada nilai']);

        return response()->json([
            'success' => true,
            'nama' => $siswa->nama_peserta_didik,
            'predikat' => $nilai->predikat,
            'deskripsi' => $nilai->deskripsi_capaian,
            'nilai_akhir' => $nilai->nilai_akhir,
        ]);
    }
}
