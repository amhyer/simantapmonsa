<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MataPelajaran;
use App\Models\SekolahSettings;
use App\Models\Siswa;
use App\Models\TranskripNilai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TranskripController extends Controller
{
    private function mapelTranskrip()
    {
        return MataPelajaran::where('aktif', true)
            ->where('masuk_transkrip', true)
            ->orderBy('kelompok')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();
    }

    private function getSetting(): array
    {
        $row = SekolahSettings::first();

        return ($row && is_array($row->pengaturan) && isset($row->pengaturan['transkrip']) && is_array($row->pengaturan['transkrip']))
            ? $row->pengaturan['transkrip']
            : [];
    }

    // ---------- Setting ----------

    public function setting()
    {
        $setting = $this->getSetting();

        return view('admin.transkrip.setting', compact('setting'));
    }

    public function updateSetting(Request $request)
    {
        $validated = $request->validate([
            'tempat' => 'nullable|string|max:100',
            'tahun_lulus' => 'nullable|string|max:9',
            'nip_kepala_sekolah' => 'nullable|string|max:50',
            'keterangan' => 'nullable|string|max:500',
        ]);

        $row = SekolahSettings::first();
        if (!$row) {
            return redirect()->route('admin.sekolah.index')
                ->with('error', 'Lengkapi Identitas Sekolah terlebih dahulu sebelum mengatur transkrip.');
        }
        $pengaturan = is_array($row->pengaturan) ? $row->pengaturan : [];
        $pengaturan['transkrip'] = $validated;
        $row->pengaturan = $pengaturan;
        $row->save();

        return redirect()->route('admin.transkrip.setting')
            ->with('success', 'Setting transkrip berhasil disimpan.');
    }

    // ---------- Nomor ijazah ----------

    public function nomor(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $siswa = Siswa::query()
            ->when($q !== '', fn ($query) => $query->where('nama_peserta_didik', 'like', "%{$q}%")
                ->orWhere('nisn', 'like', "%{$q}%")
                ->orWhere('nis', 'like', "%{$q}%"))
            ->orderBy('nama_peserta_didik')
            ->paginate(25)
            ->withQueryString();

        return view('admin.transkrip.nomor', compact('siswa', 'q'));
    }

    public function updateNomor(Request $request, Siswa $siswa)
    {
        $validated = $request->validate([
            'nomor_ijazah' => 'nullable|string|max:100|unique:siswa,nomor_ijazah,' . $siswa->id,
        ]);

        $siswa->nomor_ijazah = $validated['nomor_ijazah'] ?: null;
        $siswa->save();

        return back()->with('success', 'Nomor ijazah berhasil disimpan.');
    }

    public function importNomor(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $parsed = $this->parseCsv($request->file('file'), ['nisn', 'nomor_ijazah']);
        if ($parsed === null) {
            return back()->withErrors(['file' => 'Header CSV harus: nisn,nomor_ijazah']);
        }

        [$rows, $skipped] = $parsed;
        $updated = 0;

        DB::transaction(function () use ($rows, &$updated) {
            foreach ($rows as $row) {
                $nisn = trim((string) ($row['nisn'] ?? ''));
                $nomor = trim((string) ($row['nomor_ijazah'] ?? ''));
                if ($nisn === '' || $nomor === '') {
                    continue;
                }
                $siswa = Siswa::where('nisn', $nisn)->first();
                if (!$siswa) {
                    continue;
                }
                $bentrok = Siswa::where('nomor_ijazah', $nomor)->where('id', '!=', $siswa->id)->exists();
                if ($bentrok) {
                    continue;
                }
                $siswa->nomor_ijazah = $nomor;
                $siswa->save();
                $updated++;
            }
        });

        return back()->with('success', "Import nomor ijazah selesai: {$updated} diperbarui, {$skipped} baris dilewati.");
    }

    // ---------- Input nilai ----------

    public function input(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $siswa = Siswa::query()
            ->withCount('transkripNilai')
            ->when($q !== '', fn ($query) => $query->where('nama_peserta_didik', 'like', "%{$q}%")
                ->orWhere('nisn', 'like', "%{$q}%"))
            ->orderBy('nama_peserta_didik')
            ->paginate(25)
            ->withQueryString();
        $jumlahMapel = $this->mapelTranskrip()->count();

        return view('admin.transkrip.input', compact('siswa', 'q', 'jumlahMapel'));
    }

    public function formInput(Siswa $siswa)
    {
        $mapel = $this->mapelTranskrip();
        $nilai = TranskripNilai::where('siswa_id', $siswa->id)
            ->get()
            ->keyBy('mata_pelajaran_id');

        return view('admin.transkrip.input-form', compact('siswa', 'mapel', 'nilai'));
    }

    public function storeInput(Request $request, Siswa $siswa)
    {
        $validated = $request->validate([
            'nilai' => 'required|array',
            'nilai.*' => 'nullable|numeric|min:0|max:100',
        ]);

        $mapelIds = $this->mapelTranskrip()->pluck('id')->all();
        $disimpan = 0;

        DB::transaction(function () use ($validated, $mapelIds, $siswa, &$disimpan) {
            foreach ($mapelIds as $mapelId) {
                $mentah = $validated['nilai'][$mapelId] ?? null;
                if ($mentah === null || $mentah === '') {
                    continue;
                }
                $mapel = MataPelajaran::find($mapelId);
                if (!$mapel) {
                    continue;
                }
                $angka = round((float) $mentah, 2);
                TranskripNilai::updateOrCreate(
                    ['siswa_id' => $siswa->id, 'mata_pelajaran_id' => $mapelId],
                    [
                        'nama_mapel' => $mapel->nama,
                        'nilai_akhir' => $angka,
                        'predikat' => TranskripNilai::tentukanPredikat($angka),
                    ]
                );
                $disimpan++;
            }
        });

        return redirect()->route('admin.transkrip.input')
            ->with('success', "Nilai transkrip {$siswa->nama_peserta_didik} disimpan ({$disimpan} mapel).");
    }

    // ---------- Import nilai ----------

    public function importNilai()
    {
        return view('admin.transkrip.import');
    }

    public function templateNilai()
    {
        $mapel = $this->mapelTranskrip();

        return response()->streamDownload(function () use ($mapel) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['nisn', 'kode_mapel', 'nilai_akhir']);
            foreach ($mapel as $m) {
                fputcsv($out, ['', $m->kode ?? $m->nama, '']);
            }
            fclose($out);
        }, 'template-transkrip.csv', ['Content-Type' => 'text/csv']);
    }

    public function storeImportNilai(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $parsed = $this->parseCsv($request->file('file'), ['nisn', 'kode_mapel', 'nilai_akhir']);
        if ($parsed === null) {
            return back()->withErrors(['file' => 'Header CSV harus: nisn,kode_mapel,nilai_akhir']);
        }

        [$rows, $skipped] = $parsed;
        $mapelByKode = $this->mapelTranskrip()->keyBy(fn ($m) => strtolower(trim((string) ($m->kode ?: $m->nama))));
        $disimpan = 0;

        DB::transaction(function () use ($rows, $mapelByKode, &$disimpan) {
            foreach ($rows as $row) {
                $nisn = trim((string) ($row['nisn'] ?? ''));
                $kode = strtolower(trim((string) ($row['kode_mapel'] ?? '')));
                $nilaiMentah = trim((string) ($row['nilai_akhir'] ?? ''));
                if ($nisn === '' || $kode === '' || $nilaiMentah === '' || !is_numeric($nilaiMentah)) {
                    continue;
                }
                $angka = round((float) $nilaiMentah, 2);
                if ($angka < 0 || $angka > 100) {
                    continue;
                }
                $siswa = Siswa::where('nisn', $nisn)->first();
                $mapel = $mapelByKode->get($kode);
                if (!$siswa || !$mapel) {
                    continue;
                }
                TranskripNilai::updateOrCreate(
                    ['siswa_id' => $siswa->id, 'mata_pelajaran_id' => $mapel->id],
                    [
                        'nama_mapel' => $mapel->nama,
                        'nilai_akhir' => $angka,
                        'predikat' => TranskripNilai::tentukanPredikat($angka),
                    ]
                );
                $disimpan++;
            }
        });

        return back()->with('success', "Import nilai transkrip selesai: {$disimpan} disimpan, {$skipped} baris dilewati.");
    }

    // ---------- Cetak ----------

    public function cetak(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $siswa = Siswa::query()
            ->withCount('transkripNilai')
            ->when($q !== '', fn ($query) => $query->where('nama_peserta_didik', 'like', "%{$q}%")
                ->orWhere('nisn', 'like', "%{$q}%"))
            ->orderBy('nama_peserta_didik')
            ->paginate(25)
            ->withQueryString();

        return view('admin.transkrip.cetak', compact('siswa', 'q'));
    }

    public function showCetak(Siswa $siswa)
    {
        $nilai = TranskripNilai::where('siswa_id', $siswa->id)
            ->with('mapel')
            ->get()
            ->sortBy(fn ($n) => sprintf('%s-%05d-%s', $n->mapel->kelompok ?? 'ZZ', (int) ($n->mapel->urutan ?? 99999), $n->nama_mapel))
            ->values();
        $sekolah = SekolahSettings::first();
        $setting = $this->getSetting();
        $rata = $nilai->count() ? round($nilai->avg('nilai_akhir'), 2) : null;

        return view('admin.transkrip.cetak-show', compact('siswa', 'nilai', 'sekolah', 'setting', 'rata'));
    }

    // ---------- Util ----------

    /**
     * @return array{0: array<int, array<string, string>>, 1: int}|null
     */
    private function parseCsv($file, array $headerWajib): ?array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            return null;
        }
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);

            return null;
        }
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        foreach ($headerWajib as $kolom) {
            if (!in_array($kolom, $header, true)) {
                fclose($handle);

                return null;
            }
        }
        $rows = [];
        $skipped = 0;
        while (($baris = fgetcsv($handle)) !== false) {
            if (count($baris) === 1 && trim((string) $baris[0]) === '') {
                continue;
            }
            if (count($baris) !== count($header)) {
                $skipped++;
                continue;
            }
            $rows[] = array_combine($header, $baris);
        }
        fclose($handle);

        return [$rows, $skipped];
    }
}
