<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DapodikConfig;
use App\Models\MataPelajaran;
use App\Models\SekolahSettings;
use App\Services\AktivitasService;
use App\Services\Dapodik\DapodikClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MapelController extends Controller
{
    private function getJenjangSekolah(): string
    {
        $settings = SekolahSettings::first();
        return $settings->jenjang ?? 'SD';
    }

    public function index(Request $request)
    {
        $jenjangSekolah = $this->getJenjangSekolah();

        $query = MataPelajaran::query()->where('jenjang', $jenjangSekolah);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'ilike', "%{$search}%")
                  ->orWhere('kode', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('aktif', $request->status === 'aktif');
        }

        $mapel = $query->orderBy('nama')->paginate(20)->withQueryString();
        $total = MataPelajaran::where('jenjang', $jenjangSekolah)->count();
        $aktifCount = MataPelajaran::where('jenjang', $jenjangSekolah)->where('aktif', true)->count();

        return view('admin.mapel.index', compact('mapel', 'total', 'aktifCount', 'jenjangSekolah'));
    }

    public function create()
    {
        $jenjangSekolah = $this->getJenjangSekolah();
        return view('admin.mapel.create', compact('jenjangSekolah'));
    }

    public function store(Request $request)
    {
        $jenjangSekolah = $this->getJenjangSekolah();

        $validated = $request->validate([
            'nama' => 'required|string|max:150',
            'kode' => 'nullable|string|max:20',
        ]);

        $validated['jenjang'] = $jenjangSekolah;

        $exists = MataPelajaran::where('nama', $validated['nama'])
            ->where('jenjang', $jenjangSekolah)
            ->exists();

        if ($exists) {
            return back()->withInput()->with('error', 'Mata pelajaran "' . $validated['nama'] . '" sudah ada.');
        }

        MataPelajaran::create($validated);

        AktivitasService::tambah(
            'Tambah Mapel: ' . $validated['nama'],
            'Mata pelajaran ' . $jenjangSekolah . ' ditambahkan',
            'mata_pelajaran',
            null,
            $validated
        );

        return redirect()->route('admin.mapel.index')
            ->with('success', 'Mata pelajaran "' . $validated['nama'] . '" berhasil ditambahkan.');
    }

    public function edit(MataPelajaran $mapel)
    {
        return view('admin.mapel.edit', compact('mapel'));
    }

    public function update(Request $request, MataPelajaran $mapel)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:150',
            'kode' => 'nullable|string|max:20',
            'aktif' => 'boolean',
        ]);

        $exists = MataPelajaran::where('nama', $validated['nama'])
            ->where('jenjang', $mapel->jenjang)
            ->where('id', '!=', $mapel->id)
            ->exists();

        if ($exists) {
            return back()->withInput()->with('error', 'Mata pelajaran "' . $validated['nama'] . '" sudah ada.');
        }

        $validated['aktif'] = $request->boolean('aktif');
        $mapel->update($validated);

        AktivitasService::ubah(
            'Ubah Mapel: ' . $mapel->nama,
            'Mata pelajaran diperbarui',
            'mata_pelajaran',
            $mapel->id,
            null,
            $validated
        );

        return redirect()->route('admin.mapel.index')
            ->with('success', 'Mata pelajaran berhasil diperbarui.');
    }

    public function destroy(MataPelajaran $mapel)
    {
        $nama = $mapel->nama;
        $mapel->delete();

        AktivitasService::hapus(
            'Hapus Mapel: ' . $nama,
            'Mata pelajaran berhasil dihapus',
            'mata_pelajaran'
        );

        return redirect()->route('admin.mapel.index')
            ->with('success', 'Mata pelajaran "' . $nama . '" berhasil dihapus.');
    }

    public function destroyAll()
    {
        $jenjangSekolah = $this->getJenjangSekolah();
        MataPelajaran::where('jenjang', $jenjangSekolah)->where('aktif', true)->delete();
        return redirect()->route('admin.mapel.index')
            ->with('success', 'Semua mata pelajaran aktif berhasil dihapus.');
    }

    public function destroySelected(Request $request)
    {
        $ids = $request->input('ids', '');
        $idArray = array_filter(explode(',', $ids));

        if (empty($idArray)) {
            return redirect()->route('admin.mapel.index')
                ->with('error', 'Tidak ada mata pelajaran yang dipilih.');
        }

        MataPelajaran::whereIn('id', $idArray)->delete();

        return redirect()->route('admin.mapel.index')
            ->with('success', count($idArray) . ' mata pelajaran berhasil dihapus.');
    }

    public function importDapodik()
    {
        $jenjangSekolah = $this->getJenjangSekolah();
        $config = DapodikConfig::getInstance();

        if (empty($config->npsn) || empty($config->token)) {
            return redirect()->route('admin.mapel.index')
                ->with('error', 'Konfigurasi Dapodik belum diatur. Silakan atur NPSN dan token di menu Import Dapodik.');
        }

        try {
            $client = new DapodikClient(
                npsn: $config->npsn,
                token: $config->token,
                host: $config->host ?? 'localhost',
                port: (int) ($config->port ?? 5774),
                protocol: $config->protocol ?? 'http',
                cfAccess: $config->cf_access ?? [],
            );

            $mapelData = $client->getMataPelajaran();
        } catch (\Exception $e) {
            return redirect()->route('admin.mapel.index')
                ->with('error', 'Gagal mengambil data dari Dapodik: ' . $e->getMessage());
        }

        $existingNames = MataPelajaran::where('jenjang', $jenjangSekolah)
            ->pluck('nama')
            ->map(fn($n) => strtolower($n))
            ->toArray();

        $imported = 0;

        foreach ($mapelData as $item) {
            $nama = trim($item['nama'] ?? $item['mata_pelajaran'] ?? '');
            $kode = trim($item['kode'] ?? $item['mata_pelajaran_id'] ?? '');
            $itemJenjang = $this->resolveJenjang($item);

            if ($itemJenjang !== $jenjangSekolah) {
                continue;
            }

            if ($nama === '' || in_array(strtolower($nama), $existingNames)) {
                continue;
            }

            MataPelajaran::create([
                'nama' => $nama,
                'kode' => $kode ?: null,
                'jenjang' => $jenjangSekolah,
                'aktif' => true,
            ]);
            $existingNames[] = strtolower($nama);
            $imported++;
        }

        if ($imported > 0) {
            AktivitasService::tambah(
                'Import Dapodik Mapel',
                "{$imported} mata pelajaran {$jenjangSekolah} diimpor dari Dapodik",
                'mata_pelajaran'
            );
        }

        return redirect()->route('admin.mapel.index')
            ->with('success', "Berhasil mengimpor {$imported} mata pelajaran {$jenjangSekolah} dari Dapodik.");
    }

    public function importLokal()
    {
        $jenjangSekolah = $this->getJenjangSekolah();

        $existingNames = MataPelajaran::where('jenjang', $jenjangSekolah)
            ->pluck('nama')
            ->map(fn($n) => strtolower($n))
            ->toArray();

        $sources = [
            DB::table('jadwal_pelajaran')->distinct()->pluck('mata_pelajaran'),
            DB::table('materi')->distinct()->pluck('mata_pelajaran'),
            DB::table('kuis')->distinct()->pluck('mata_pelajaran'),
            DB::table('nilai')->distinct()->pluck('mata_pelajaran'),
        ];

        $found = collect();
        foreach ($sources as $source) {
            foreach ($source as $name) {
                $trimmed = trim($name);
                if ($trimmed === '' || in_array(strtolower($trimmed), $existingNames)) {
                    continue;
                }
                if ($this->isClassName($trimmed)) {
                    continue;
                }
                $found->push($trimmed);
                $existingNames[] = strtolower($trimmed);
            }
        }

        $found = $found->unique()->sort()->values();

        $imported = 0;
        foreach ($found as $name) {
            MataPelajaran::create([
                'nama' => $name,
                'jenjang' => $jenjangSekolah,
                'aktif' => true,
            ]);
            $imported++;
        }

        return redirect()->route('admin.mapel.index')
            ->with('success', "Berhasil mengimpor {$imported} mata pelajaran {$jenjangSekolah} dari data lokal.");
    }

    public function seedDefault()
    {
        $jenjangSekolah = $this->getJenjangSekolah();

        $allDefaults = [
            'SD' => [
                'Pendidikan Agama Islam dan Budi Pekerti' => 'PAI',
                'Pendidikan Agama Kristen dan Budi Pekerti' => 'PAKR',
                'Pendidikan Agama Katholik dan Budi Pekerti' => 'PAKT',
                'Pendidikan Agama Buddha dan Budi Pekerti' => 'PABU',
                'Pendidikan Agama Hindu dan Budi Pekerti' => 'PAHI',
                'Pendidikan Agama Konghuchu dan Budi Pekerti' => 'PAKO',
                'Pendidikan Kepercayaan terhadap Tuhan YME dan Budi Pekerti' => 'PKT',
                'Pendidikan Pancasila dan Kewarganegaraan' => 'PPKn',
                'Pembelajaran Berbasis Projek' => 'PBP',
                'Bahasa Indonesia' => 'BIND',
                'Matematika (Umum)' => 'MTK',
                'Ilmu Pengetahuan Alam dan Sosial (IPAS)' => 'IPAS',
                'Pendidikan Jasmani, Olahraga, dan Kesehatan' => 'PJOK',
                'Seni Musik' => 'SM',
                'Seni Tari' => 'ST',
                'Seni Rupa' => 'SR',
                'Seni Teater' => 'STE',
                'Seni Budaya' => 'SBK',
                'Bahasa Inggris' => 'BING',
                'Muatan Lokal Bahasa Daerah' => 'MLBD',
                'Muatan Lokal Potensi Daerah' => 'MLPD',
            ],
            'MI' => [
                'Al-Qur\'an Hadis' => 'AQH',
                'Fiqih' => 'FIQ',
                'Akidah Akhlak' => 'AA',
                'Bahasa Arab' => 'BAR',
                'Bahasa Indonesia' => 'BIND',
                'Matematika' => 'MTK',
                'IPA' => 'IPA',
                'IPS' => 'IPS',
                'PJOK' => 'PJOK',
                'Seni Budaya' => 'SBK',
                'Prakarya' => 'PRK',
            ],
            'SMP' => [
                'Bahasa Indonesia' => 'BIND',
                'Matematika' => 'MTK',
                'Bahasa Inggris' => 'BING',
                'Pendidikan Agama Islam' => 'PAI',
                'Pendidikan Agama Kristen' => 'PAKR',
                'Pendidikan Agama Katolik' => 'PAKT',
                'Pendidikan Pancasila' => 'PP',
                'IPS' => 'IPS',
                'IPA' => 'IPA',
                'Seni Budaya' => 'SBK',
                'PJOK' => 'PJOK',
                'Prakarya' => 'PRK',
                'Informatika' => 'TIK',
            ],
            'MTs' => [
                'Al-Qur\'an Hadis' => 'AQH',
                'Fikih' => 'FIQ',
                'Akidah Akhlak' => 'AA',
                'Bahasa Arab' => 'BAR',
                'Sejarah Kebudayaan Islam' => 'SKI',
                'Bahasa Indonesia' => 'BIND',
                'Matematika' => 'MTK',
                'Bahasa Inggris' => 'BING',
                'IPA' => 'IPA',
                'IPS' => 'IPS',
                'PJOK' => 'PJOK',
                'Seni Budaya' => 'SBK',
                'Prakarya' => 'PRK',
                'Informatika' => 'TIK',
            ],
            'SMA' => [
                'Bahasa Indonesia' => 'BIND',
                'Matematika' => 'MTK',
                'Bahasa Inggris' => 'BING',
                'Pendidikan Agama Islam' => 'PAI',
                'Pendidikan Agama Kristen' => 'PAKR',
                'Pendidikan Agama Katolik' => 'PAKT',
                'Pendidikan Pancasila' => 'PP',
                'PJOK' => 'PJOK',
                'Informatika' => 'TIK',
                'Seni Budaya' => 'SBK',
            ],
            'MA' => [
                'Al-Qur\'an Hadis' => 'AQH',
                'Fikih' => 'FIQ',
                'Akidah Akhlak' => 'AA',
                'Bahasa Arab' => 'BAR',
                'Sejarah Kebudayaan Islam' => 'SKI',
                'Bahasa Indonesia' => 'BIND',
                'Matematika' => 'MTK',
                'Bahasa Inggris' => 'BING',
                'PJOK' => 'PJOK',
                'Informatika' => 'TIK',
            ],
            'SMK' => [
                'Bahasa Indonesia' => 'BIND',
                'Matematika' => 'MTK',
                'Bahasa Inggris' => 'BING',
                'Pendidikan Agama Islam' => 'PAI',
                'Pendidikan Agama Kristen' => 'PAKR',
                'Pendidikan Agama Katolik' => 'PAKT',
                'Pendidikan Pancasila' => 'PP',
                'PJOK' => 'PJOK',
                'Informatika' => 'TIK',
            ],
            'SLB' => [
                'Bahasa Indonesia' => 'BIND',
                'Matematika' => 'MTK',
                'Bahasa Inggris' => 'BING',
                'Pendidikan Agama Islam' => 'PAI',
                'Pendidikan Pancasila' => 'PP',
                'PJOK' => 'PJOK',
                'Seni Budaya' => 'SBK',
            ],
        ];

        $defaults = $allDefaults[$jenjangSekolah] ?? $allDefaults['SD'];

        $added = 0;
        foreach ($defaults as $nama => $kode) {
            $exists = MataPelajaran::where('nama', $nama)->where('jenjang', $jenjangSekolah)->exists();
            if (!$exists) {
                MataPelajaran::create([
                    'nama' => $nama,
                    'kode' => $kode,
                    'jenjang' => $jenjangSekolah,
                    'aktif' => true,
                ]);
                $added++;
            }
        }

        return redirect()->route('admin.mapel.index')
            ->with('success', "Berhasil menambahkan {$added} mata pelajaran default {$jenjangSekolah}.");
    }

    private function isClassName(string $name): bool
    {
        $lower = strtolower($name);
        if (str_starts_with($lower, 'kelas')) {
            return true;
        }
        if (preg_match('/^kelas\s+\d+/', $lower)) {
            return true;
        }
        if (preg_match('/^(xii|xi|x|ix|viii|vii|vi|v|iv|iii|ii|i)[\s\.]/i', $name)) {
            return true;
        }
        if (preg_match('/^\d+\s*[a-zA-Z]/', $name)) {
            return true;
        }
        return false;
    }

    private function resolveJenjang(array $item): string
    {
        $tp = strtolower(trim($item['tingkat_pendidikan_id_str'] ?? ''));
        $nama = strtolower(trim($item['nama'] ?? ''));

        if (str_contains($tp, 'sd') || $tp === 'sd') return 'SD';
        if (str_contains($tp, 'mi') || $tp === 'mi') return 'MI';
        if (str_contains($tp, 'smp') || $tp === 'smp') return 'SMP';
        if (str_contains($tp, 'mts') || $tp === 'mts') return 'MTs';
        if (str_contains($tp, 'sma') || $tp === 'sma') return 'SMA';
        if (str_contains($tp, 'ma') && !str_contains($tp, 'mak')) return 'MA';
        if (str_contains($tp, 'smk')) return 'SMK';
        if (str_contains($tp, 'slb')) return 'SLB';

        return $this->getJenjangSekolah();
    }
}
