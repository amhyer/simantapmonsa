<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\User;
use App\Models\OrangTuaSiswa;
use App\Services\NilaiService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class SiswaController extends Controller
{
    protected $nilaiService;

    public function __construct(NilaiService $nilaiService)
    {
        $this->nilaiService = $nilaiService;
    }

    public function index()
    {
        $siswa = Siswa::where('guru_id', auth()->id())->orderBy('nama_peserta_didik')->get();
        return view('guru.siswa.index', compact('siswa'));
    }

    public function create()
    {
        return view('guru.siswa.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'nis' => 'required|string|max:50|unique:siswa,nis',
            'jenis_kelamin' => 'required|in:L,P',
            'kelas' => 'required|string|max:50',
            'nama_orang_tua' => 'nullable|string|max:255',
            'aktif' => 'boolean',
        ]);

        $guru = auth()->user();

        $kodeOrtu = 'ORT' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        while (Siswa::where('nama_orang_tua', $kodeOrtu)->exists()) {
            $kodeOrtu = 'ORT' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        }

        $siswa = Siswa::create([
            'uuid' => Str::uuid(),
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nama_peserta_didik' => $validated['nama'],
            'nis' => $validated['nis'],
            'jenis_kelamin' => $validated['jenis_kelamin'],
            'kelas' => $validated['kelas'],
            'nama_orang_tua' => $validated['nama_orang_tua'] ?? $kodeOrtu,
            'aktif' => $validated['aktif'] ?? true,
        ]);

        $this->updateRingkasan($guru->id);

        return redirect()->route('guru.siswa.index')
            ->with('success', 'Siswa berhasil ditambahkan.');
    }

    public function edit(Siswa $siswa)
    {
        if ($siswa->guru_id !== auth()->id()) {
            abort(403);
        }
        return view('guru.siswa.edit', compact('siswa'));
    }

    public function update(Request $request, Siswa $siswa)
    {
        if ($siswa->guru_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'nis' => 'required|string|max:50|unique:siswa,nis,' . $siswa->id,
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
            'nama_orang_tua' => $validated['nama_orang_tua'],
            'aktif' => $validated['aktif'] ?? true,
        ]);

        $this->updateRingkasan(auth()->id());

        return redirect()->route('guru.siswa.index')
            ->with('success', 'Data siswa berhasil diperbarui.');
    }

    public function destroy(Siswa $siswa)
    {
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
                    $field = "'" . $field;
                }
                if (str_contains($field, ',') || str_contains($field, '"') || str_contains($field, "\n")) {
                    $field = '"' . str_replace('"', '""', $field) . '"';
                }
                return $field;
            }, $fields)) . "\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="siswa-' . date('Y-m-d') . '.csv"');
    }

    public function impor(Request $request)
    {
        $request->validate([
            'csv' => 'required|string'
        ]);

        $rows = explode("\n", trim($request->csv));
        $count = 0;
        $errors = [];

        foreach ($rows as $row) {
            $data = str_getcsv($row);
            if (count($data) < 2) continue;

            if (strtolower($data[0]) === 'nama') continue;

            if (Siswa::where('nis', $data[1])->exists()) {
                $errors[] = "NIS {$data[1]} sudah ada.";
                continue;
            }

            $kodeOrtu = 'ORT' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            while (Siswa::where('nama_orang_tua', $kodeOrtu)->exists()) {
                $kodeOrtu = 'ORT' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            }

            Siswa::create([
                'uuid' => Str::uuid(),
                'guru_id' => auth()->id(),
                'nama_guru' => auth()->user()->nama_lengkap,
                'nama_peserta_didik' => $data[0],
                'nis' => $data[1],
                'jenis_kelamin' => $data[2] ?? 'L',
                'kelas' => $data[3] ?? auth()->user()->kelas_mata_pelajaran,
                'nama_orang_tua' => $kodeOrtu,
                'aktif' => true,
            ]);

            $count++;
        }

        $this->updateRingkasan(auth()->id());

        $message = "{$count} siswa berhasil diimpor.";
        if (!empty($errors)) {
            $message .= " Error: " . implode(' ', $errors);
        }

        return redirect()->route('guru.siswa.index')
            ->with('success', $message);
    }

    private function updateRingkasan($guruId)
    {
        $siswa = Siswa::where('guru_id', $guruId)->where('aktif', true)->get();
        $pengaturan = \App\Models\PengaturanGuru::where('guru_id', $guruId)->first();

        if (!$pengaturan) return;

        $kkm = $pengaturan->kkm ?? 70;
        $nilaiAkhir = [];
        $tuntas = 0;

        foreach ($siswa as $s) {
            $na = $this->nilaiService->hitungNilaiAkhir($s->id);
            if ($na['nilai_akhir'] > 0) {
                $nilaiAkhir[] = $na['nilai_akhir'];
                if ($na['nilai_akhir'] >= $kkm) $tuntas++;
            }
        }

        \App\Models\RingkasanGuru::updateOrCreate(
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
        ]);

        $guru = auth()->user();
        $count = 0;

        foreach ($request->siswa_ids as $siswaId) {
            $asal = Siswa::find($siswaId);
            if (!$asal || $asal->guru_id === $guru->id) continue;

            $existing = Siswa::where('nis', $asal->nis)
                ->where('guru_id', $guru->id)
                ->first();

            if ($existing) continue;

            $kodeOrtu = $asal->nama_orang_tua;
            if (!$kodeOrtu || $kodeOrtu === 'ORT' . substr($asal->nis, -4)) {
                $kodeOrtu = 'ORT' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            }

            // Generate unique NIS with prefix for target class
            $newNis = $guru->id . '-' . $asal->nis;
            if (Siswa::where('nis', $newNis)->exists()) {
                $newNis = $guru->id . '-' . $asal->nis . '-' . Str::random(4);
            }

            Siswa::create([
                'uuid' => Str::uuid(),
                'guru_id' => $guru->id,
                'nama_guru' => $guru->nama_lengkap,
                'nis' => $newNis,
                'nisn' => $asal->nisn,
                'nama_peserta_didik' => $asal->nama_peserta_didik,
                'kelas' => $asal->kelas,
                'jenis_kelamin' => $asal->jenis_kelamin,
                'nama_orang_tua' => $asal->nama_orang_tua,
                'aktif' => true,
                'dapodik_id' => $asal->dapodik_id,
                'semester_id' => $asal->semester_id,
                'status_siswa' => $asal->status_siswa,
                'rekaman' => array_merge($asal->rekaman ?? [], [
                    'dari_kelas' => $asal->kelas,
                    'wali_kelas_asal' => $asal->nama_guru,
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