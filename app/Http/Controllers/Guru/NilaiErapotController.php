<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\JadwalPelajaran;
use App\Models\NilaiErapot;
use App\Models\PengaturanGuru;
use App\Models\Rombel;
use App\Models\Siswa;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Services\JadwalPelajaranResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class NilaiErapotController extends Controller
{
    private function guruBolehMenilaiSiswa($guru, Siswa $siswa, ?string $kelasRequest = null): bool
    {
        if ((int) $siswa->guru_id === (int) $guru->id) {
            return true;
        }

        $kelasSiswa = trim((string) ($siswa->kelas ?? ''));
        if ($kelasRequest !== null && $kelasSiswa !== '' && strcasecmp($kelasSiswa, trim($kelasRequest)) !== 0) {
            return false;
        }

        $kelasDiampu = collect()
            ->merge(Rombel::where('guru_id', $guru->id)->pluck('nama_rombel'))
            ->merge(PengaturanGuru::where('guru_id', $guru->id)->pluck('kelas'))
            ->merge(JadwalPelajaran::where('guru_id', $guru->id)->pluck('kelas'))
            ->map(fn ($k) => trim((string) $k))
            ->filter()
            ->unique()
            ->values();

        if ($kelasDiampu->isEmpty()) {
            return true;
        }

        return $kelasSiswa !== '' && $kelasDiampu->contains(fn (string $k) => strcasecmp($k, $kelasSiswa) === 0);
    }

    public function index(Request $request)
    {
        $guru = auth()->user();
        $pengaturan = PengaturanGuru::where('guru_id', $guru->id)->first();
        $kelas = $request->get('kelas', optional($pengaturan)->kelas ?? 'V A');
        $mapel = $request->get('mapel', optional($pengaturan)->mata_pelajaran ?? 'Matematika');
        $semester = $request->get('semester', optional($pengaturan)->semester ?? 'Ganjil');
        $tahunAjaran = $request->get('tahun_ajaran', optional($pengaturan)->tahun_pelajaran ?? date('Y').'/'.(date('Y') + 1));

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
        $tenantId = TenantContext::id() ?? auth()->user()?->tenant_id;
        $tenantScope = fn ($q) => $tenantId !== null ? $q->where('tenant_id', $tenantId) : $q->whereNull('tenant_id');

        $request->validate([
            'nilai' => 'required|array',
            'nilai.*.siswa_id' => ['required', Rule::exists('siswa', 'id')->where($tenantScope)],
            'nilai.*.formatif' => 'nullable|numeric|min:0|max:100',
            'nilai.*.sumatif' => 'nullable|numeric|min:0|max:100',
            'nilai.*.sumatif_akhir' => 'nullable|numeric|min:0|max:100',
            'kelas' => 'required|string',
            'mapel' => 'required|string',
            'semester' => 'required|in:Ganjil,Genap',
            'tahun_ajaran' => 'required|string',
        ]);

        $guru = auth()->user();

        if (Rombel::apakahTerkunciUntukKelas($request->kelas, $guru->id)) {
            return back()->with('error', "Input nilai rapor untuk kelas {$request->kelas} sedang dikunci oleh Admin Sekolah.")->withInput();
        }
        $pengaturan = PengaturanGuru::where('guru_id', $guru->id)->first();
        $kkm = $pengaturan->kkm ?? 70;
        $bobot = ($pengaturan->pengaturan ?? [])['bobot_erapor'] ?? ['formatif' => 0.30, 'sumatif' => 0.40, 'sumatif_akhir' => 0.30];

        // Auto-resolve FK alignment sekali per request (sama untuk semua baris).
        $jadwalId = app(JadwalPelajaranResolver::class)->resolve(
            $guru->id, $request->mapel, $request->kelas, $request->semester, $request->tahun_ajaran
        );

        $siswaMap = Siswa::whereIn('id', collect($request->nilai)->pluck('siswa_id')->map(fn ($id) => (int) $id))->get()->keyBy('id');

        DB::beginTransaction();
        try {
            $berhasil = 0;
            foreach ($request->nilai as $item) {
                $adaFormatif = isset($item['formatif']) && $item['formatif'] !== '';
                $adaSumatif = isset($item['sumatif']) && $item['sumatif'] !== '';
                $adaSumatifAkhir = isset($item['sumatif_akhir']) && $item['sumatif_akhir'] !== '';

                if (! $adaFormatif && ! $adaSumatif && ! $adaSumatifAkhir) {
                    continue;
                }

                $siswa = $siswaMap->get((int) $item['siswa_id']);
                if (! $siswa) {
                    continue;
                }

                if (! $this->guruBolehMenilaiSiswa($guru, $siswa, (string) $request->kelas)) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Anda tidak memiliki hak akses untuk mengubah nilai e-Rapor siswa di kelas ini.',
                    ], 403);
                }

                if ($siswa->kelas && Rombel::apakahTerkunciUntukKelas($siswa->kelas, $guru->id)) {
                    DB::rollBack();

                    return back()->with('error', "Input nilai rapor untuk kelas {$siswa->kelas} sedang dikunci oleh Admin Sekolah.")->withInput();
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
                        'kelas' => $siswa->kelas ?: $request->kelas,
                        'jadwal_pelajaran_id' => $jadwalId,
                        'nilai_formatif' => $adaFormatif ? (float) $item['formatif'] : 0,
                        'nilai_sumatif' => $adaSumatif ? (float) $item['sumatif'] : 0,
                        'nilai_sumatif_akhir' => $adaSumatifAkhir ? (float) $item['sumatif_akhir'] : 0,
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
            Log::error('Gagal menyimpan nilai erapor: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data nilai.'], 500);
        }
    }

    public function autoSave(Request $request)
    {
        $request->validate([
            'siswa_id' => 'required|integer',
            'field' => 'required|in:formatif,sumatif,sumatif_akhir',
            'value' => 'nullable|numeric|min:0|max:100',
            'kelas' => 'required|string',
            'mapel' => 'required|string',
            'semester' => 'required|in:Ganjil,Genap',
            'tahun_ajaran' => 'required|string',
        ]);

        $siswa = Siswa::find($request->siswa_id);
        if (! $siswa) {
            return response()->json([
                'success' => false,
                'message' => 'Data siswa tidak ditemukan di sekolah Anda.',
            ], 404);
        }

        $guru = auth()->user();
        if (! $this->guruBolehMenilaiSiswa($guru, $siswa, (string) $request->kelas)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk mengubah nilai e-Rapor siswa di kelas ini.',
            ], 403);
        }

        $kelasAsli = (string) ($siswa->kelas ?: $request->kelas);
        if (Rombel::apakahTerkunciUntukKelas($request->kelas, $guru->id) || Rombel::apakahTerkunciUntukKelas($kelasAsli, $guru->id)) {
            return response()->json([
                'success' => false,
                'message' => "Input nilai rapor untuk kelas {$kelasAsli} sedang dikunci oleh Admin Sekolah.",
            ], 423);
        }

        $pengaturan = PengaturanGuru::where('guru_id', $guru->id)->first();
        $kkm = $pengaturan->kkm ?? 70;
        $bobot = ($pengaturan->pengaturan ?? [])['bobot_erapor'] ?? ['formatif' => 0.30, 'sumatif' => 0.40, 'sumatif_akhir' => 0.30];

        $nilai = NilaiErapot::firstOrNew([
            'siswa_id' => $siswa->id,
            'mata_pelajaran' => $request->mapel,
            'semester' => $request->semester,
            'tahun_ajaran' => $request->tahun_ajaran,
        ]);

        $nilai->guru_id = $guru->id;
        $nilai->kelas = $kelasAsli;
        $nilai->jadwal_pelajaran_id = app(JadwalPelajaranResolver::class)->resolve(
            $guru->id, $request->mapel, $request->kelas, $request->semester, $request->tahun_ajaran
        );
        $field = 'nilai_'.$request->field;
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
        if (! $siswa) {
            return response()->json(['success' => false]);
        }

        $nilai = NilaiErapot::where('siswa_id', $siswaId)
            ->where('guru_id', $guru->id)
            ->where('mata_pelajaran', $request->mapel)
            ->where('semester', $request->semester)
            ->where('tahun_ajaran', $request->tahun_ajaran)
            ->first();

        if (! $nilai) {
            return response()->json(['success' => false, 'message' => 'Belum ada nilai']);
        }

        return response()->json([
            'success' => true,
            'nama' => $siswa->nama_peserta_didik,
            'predikat' => $nilai->predikat,
            'deskripsi' => $nilai->deskripsi_capaian,
            'nilai_akhir' => $nilai->nilai_akhir,
        ]);
    }
}
