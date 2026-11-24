<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\PengaturanGuru;
use App\Models\RingkasanGuru;
use App\Models\Rombel;
use App\Models\Siswa;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Services\NilaiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SiswaController extends Controller
{
    protected $nilaiService;

    public function __construct(NilaiService $nilaiService)
    {
        $this->nilaiService = $nilaiService;
    }

    public function index(Request $request)
    {
        $guruId = auth()->id();
        $kelasList = Siswa::where('aktif', true)->whereNull('archived_at')
            ->distinct()->orderBy('kelas')->pluck('kelas')->filter()->values();
        if ($kelasList->isEmpty()) {
            $kelasList = Rombel::orderBy('nama_rombel')->pluck('nama_rombel')->unique()->values();
        }

        $selectedKelas = $request->input('kelas')
            ?? Rombel::where('guru_id', $guruId)->value('nama_rombel')
            ?? PengaturanGuru::where('guru_id', $guruId)->value('kelas')
            ?? Siswa::where('guru_id', $guruId)->value('kelas')
            ?? $kelasList->first();

        if ($selectedKelas) {
            $siswa = Siswa::where('kelas', $selectedKelas)
                ->where('aktif', true)
                ->whereNull('archived_at')
                ->orderBy('nama_peserta_didik')
                ->get();
            if ($siswa->isEmpty()) {
                $siswa = Siswa::where('guru_id', $guruId)->orderBy('nama_peserta_didik')->get();
            }
        } else {
            $siswa = Siswa::where('guru_id', $guruId)->orderBy('nama_peserta_didik')->get();
        }

        return view('guru.siswa.index', compact('siswa', 'kelasList', 'selectedKelas'));
    }

    public function create()
    {
        return view('guru.siswa.create');
    }

    public function store(Request $request)
    {
        $nisRule = Rule::unique('siswa', 'nis')->where(fn ($query) => TenantContext::check()
            ? $query->where('tenant_id', TenantContext::id())
            : $query->whereNull('tenant_id'));

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'nis' => ['required', 'string', 'max:50', $nisRule],
            'jenis_kelamin' => 'required|in:L,P',
            'kelas' => 'required|string|max:50',
            'nama_orang_tua' => 'nullable|string|max:255',
            'aktif' => 'boolean',
        ]);

        $guru = auth()->user();
        $namaOrangTua = trim((string) ($validated['nama_orang_tua'] ?? ''));

        $siswa = Siswa::create([
            'uuid' => Str::uuid(),
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nama_peserta_didik' => $validated['nama'],
            'nis' => $validated['nis'],
            'jenis_kelamin' => $validated['jenis_kelamin'],
            'kelas' => $validated['kelas'],
            'nama_orang_tua' => $namaOrangTua !== '' ? $namaOrangTua : null,
            'aktif' => $validated['aktif'] ?? true,
        ]);

        $this->updateRingkasan($guru->id);

        return redirect()->route('guru.siswa.index')
            ->with('success', 'Siswa berhasil ditambahkan.');
    }

    public function edit(Siswa $siswa)
    {
        if (empty($siswa->dapodik_id) && $siswa->guru_id !== auth()->id()) {
            abort(403);
        }

        return view('guru.siswa.edit', compact('siswa'));
    }

    public function update(Request $request, Siswa $siswa)
    {
        if (empty($siswa->dapodik_id) && $siswa->guru_id !== auth()->id()) {
            abort(403);
        }

        $nisRule = Rule::unique('siswa', 'nis')
            ->ignore($siswa->id)
            ->where(fn ($query) => TenantContext::check()
                ? $query->where('tenant_id', TenantContext::id())
                : $query->whereNull('tenant_id'));

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'nis' => ['required', 'string', 'max:50', $nisRule],
            'jenis_kelamin' => 'required|in:L,P',
            'kelas' => 'required|string|max:50',
            'nama_orang_tua' => 'nullable|string|max:255',
            'aktif' => 'boolean',
        ]);

        $siswa->update([
            'nama_peserta_didik' => $validated['nama'],
            'nis' => $validated['nis'],
            'jenis_kelamin' => $validated['jenis_kelamin'],
            'kelas' => $validated['kelas'],
            'nama_orang_tua' => $validated['nama_orang_tua'] ?? null,
            'aktif' => $validated['aktif'] ?? true,
        ]);

        $this->updateRingkasan(auth()->id());

        return redirect()->route('guru.siswa.index')
            ->with('success', 'Data siswa berhasil diperbarui.');
    }

    public function destroy(Siswa $siswa)
    {
        if (! empty($siswa->dapodik_id)) {
            return back()->with('error', 'Siswa Dapodik adalah data master terpusat sekolah dan tidak dapat dihapus manual.');
        }

        if ($siswa->guru_id !== auth()->id()) {
            abort(403);
        }

        DB::transaction(function () use ($siswa) {
            $siswa->nilai()->delete();
            $siswa->kehadiran()->delete();
            $siswa->hasilKuis()->delete();
            $siswa->catatan()->delete();
            $siswa->dimensi()->delete();
            $siswa->kebiasaan()->delete();
            $siswa->delete();
        });

        $this->updateRingkasan(auth()->id());

        return redirect()->route('guru.siswa.index')
            ->with('success', 'Siswa berhasil dihapus.');
    }

    public function unduh()
    {
        $siswa = Siswa::where('guru_id', auth()->id())->get();

        $csv = "Nama,NIS,Jenis_Kelamin,Kelas,Kode_Orang_Tua\n";
        foreach ($siswa as $s) {
            $fields = [
                $s->nama_peserta_didik,
                $s->nis,
                $s->jenis_kelamin,
                $s->kelas,
                $s->nama_orang_tua,
            ];
            // Escape CSV fields to prevent injection
            $csv .= implode(',', array_map(function ($field) {
                $field = (string) ($field ?? '');
                if (in_array(substr($field, 0, 1), ['=', '+', '-', '@'])) {
                    $field = "'".$field;
                }
                if (str_contains($field, ',') || str_contains($field, '"') || str_contains($field, "\n")) {
                    $field = '"'.str_replace('"', '""', $field).'"';
                }

                return $field;
            }, $fields))."\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="siswa-'.date('Y-m-d').'.csv"');
    }

    public function impor(Request $request)
    {
        $request->validate([
            'csv' => 'required|string',
        ]);

        $rows = explode("\n", trim($request->csv));
        $count = 0;
        $errors = [];

        foreach ($rows as $row) {
            $data = str_getcsv($row);
            if (count($data) < 2) {
                continue;
            }

            if (strtolower($data[0]) === 'nama') {
                continue;
            }

            if (Siswa::where('nis', $data[1])->exists()) {
                $errors[] = "NIS {$data[1]} sudah ada.";

                continue;
            }

            $namaOrtu = isset($data[4]) && trim((string) $data[4]) !== ''
                ? trim((string) $data[4])
                : null;

            Siswa::create([
                'uuid' => Str::uuid(),
                'guru_id' => auth()->id(),
                'nama_guru' => auth()->user()->nama_lengkap,
                'nama_peserta_didik' => $data[0],
                'nis' => $data[1],
                'jenis_kelamin' => $data[2] ?? 'L',
                'kelas' => $data[3] ?? auth()->user()->kelas_mata_pelajaran,
                'nama_orang_tua' => $namaOrtu,
                'aktif' => true,
            ]);

            $count++;
        }

        $this->updateRingkasan(auth()->id());

        $message = "{$count} siswa berhasil diimpor.";
        if (! empty($errors)) {
            $message .= ' Error: '.implode(' ', $errors);
        }

        return redirect()->route('guru.siswa.index')
            ->with('success', $message);
    }

    private function updateRingkasan($guruId)
    {
        $siswa = Siswa::where('guru_id', $guruId)->where('aktif', true)->get();
        $pengaturan = PengaturanGuru::where('guru_id', $guruId)->first();

        if (! $pengaturan) {
            return;
        }

        $kkm = $pengaturan->kkm ?? 70;
        $nilaiAkhir = [];
        $tuntas = 0;

        foreach ($siswa as $s) {
            $na = $this->nilaiService->hitungNilaiAkhir($s->id);
            if ($na['nilai_akhir'] > 0) {
                $nilaiAkhir[] = $na['nilai_akhir'];
                if ($na['nilai_akhir'] >= $kkm) {
                    $tuntas++;
                }
            }
        }

        // Unik guru_id masih global. Jangan menimpa ringkasan lama/sekolah lain
        // atau memicu duplicate key saat kepemilikannya belum direkonsiliasi.
        if (RingkasanGuru::withoutGlobalScope('schoolOwnership')->where('guru_id', $guruId)->exists()
            && ! RingkasanGuru::where('guru_id', $guruId)->exists()) {
            Log::warning('Ringkasan guru belum diperbarui karena kepemilikan data lama harus direkonsiliasi.', ['guru_id' => $guruId]);
            if (request()->hasSession()) {
                session()->flash('warning', 'Ringkasan guru belum diperbarui. Hubungi administrator untuk rekonsiliasi data lama.');
            }

            return;
        }

        RingkasanGuru::updateOrCreate(
            ['guru_id' => $guruId],
            [
                'nama_guru' => auth()->user()->nama_lengkap,
                'jumlah_siswa' => $siswa->count(),
                'rata_rata' => empty($nilaiAkhir) ? 0 : array_sum($nilaiAkhir) / count($nilaiAkhir),
                'tuntas' => $tuntas,
                'belum_tuntas' => $siswa->count() - $tuntas,
                'ketuntasan_persen' => $siswa->count() ? round($tuntas / $siswa->count() * 100, 2) : 0,
                'pembaruan' => now(),
            ]
        );
    }

    public function pungut()
    {
        $guruId = auth()->id();
        $siswaSaya = Siswa::where('guru_id', $guruId)->pluck('nis')->toArray();

        $kelasList = Siswa::where('guru_id', '!=', $guruId)
            ->where('aktif', true)
            ->whereNotIn('nis', $siswaSaya)
            ->pluck('kelas')
            ->unique()
            ->sort()
            ->values();

        return view('guru.siswa.pungut', compact('kelasList'));
    }

    public function getKelasSiswa(Request $request)
    {
        $kelas = $request->input('kelas');
        $guruId = auth()->id();
        $sudahAda = Siswa::where('guru_id', $guruId)->pluck('nis')->toArray();

        $siswa = Siswa::where('guru_id', '!=', $guruId)
            ->where('aktif', true)
            ->where('kelas', $kelas)
            ->whereNotIn('nis', $sudahAda)
            ->get(['id', 'nama_peserta_didik', 'nis', 'jenis_kelamin', 'kelas', 'nama_orang_tua', 'guru_id']);

        return response()->json($siswa);
    }

    public function simpanPungut(Request $request)
    {
        $request->validate([
            'siswa_ids' => 'required|array',
            'siswa_ids.*' => 'integer',
        ]);

        $guru = auth()->user();
        $rombelGuru = Rombel::where('guru_id', $guru->id)->first();
        $pengaturanKelas = PengaturanGuru::where('guru_id', $guru->id)->value('kelas');
        $targetKelas = $rombelGuru?->nama_rombel ?: ($pengaturanKelas ?: $guru->kelas_mata_pelajaran);

        if ($targetKelas && Rombel::apakahTerkunciUntukKelas($targetKelas, $guru->id)) {
            return back()->with('error', "Kelas {$targetKelas} sedang dikunci oleh Admin Sekolah.");
        }

        $count = 0;

        foreach ($request->siswa_ids as $siswaId) {
            $asal = Siswa::find($siswaId);
            if (! $asal || (int) $asal->guru_id === (int) $guru->id) {
                continue;
            }

            if ($asal->kelas && Rombel::apakahTerkunciUntukKelas($asal->kelas, $guru->id)) {
                continue;
            }

            $kelasLama = $asal->kelas;
            $waliLama = $asal->nama_guru;

            $asal->update([
                'guru_id' => $guru->id,
                'nama_guru' => $guru->nama_lengkap,
                'kelas' => $targetKelas ?: $asal->kelas,
                'rombel_id' => $rombelGuru?->id ?? $asal->rombel_id,
                'rekaman' => array_merge($asal->rekaman ?? [], [
                    'dari_kelas' => $kelasLama,
                    'wali_kelas_asal' => $waliLama,
                    'diambil_pada' => now()->toIso8601String(),
                ]),
            ]);

            $count++;
        }

        $this->updateRingkasan($guru->id);

        return redirect()->route('guru.siswa.index')
            ->with('success', "{$count} siswa berhasil diambil dari kelas lain.");
    }
}
