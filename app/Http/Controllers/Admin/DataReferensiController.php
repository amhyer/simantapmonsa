<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ekstrakurikuler;
use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\Ptk;
use App\Models\SekolahSettings;
use App\Models\Siswa;
use App\Models\User;
use App\Models\TanggalRapor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DataReferensiController extends Controller
{
    public function guru()
    {
        $ptk = Ptk::with('semester')->orderBy('nama')->get();

        return view('admin.referensi.guru', compact('ptk'));
    }

    public function pembelajaran()
    {
        $jadwal = JadwalPelajaran::with(['guru', 'rombel', 'semester'])
            ->orderBy('hari')
            ->orderBy('jam_mulai')
            ->get();
        $daftarMapel = MataPelajaran::where('aktif', true)->orderBy('nama')->get();
        $daftarGuru = User::where('peran', 'guru')->where('aktif', true)->orderBy('nama_lengkap')->get();

        return view('admin.referensi.pembelajaran', compact('jadwal', 'daftarMapel', 'daftarGuru'));
    }

    /**
     * Tambah jadwal pembelajaran manual (cermin tombol
     * "Tambah Sub Pembelajaran" di e-Rapor).
     */
    public function storePembelajaran(Request $request)
    {
        $validated = $request->validate([
            'hari' => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
            'mata_pelajaran' => 'required|string|max:100',
            'kelas' => 'required|string|max:50',
            'guru_id' => 'nullable|exists:users,id',
            'jam_mulai' => 'nullable|string|max:10',
            'jam_selesai' => 'nullable|string|max:10',
            'ruangan' => 'nullable|string|max:50',
        ]);

        JadwalPelajaran::create($validated);

        return back()->with('success', 'Jadwal pembelajaran berhasil ditambahkan.');
    }

    /**
     * Hapus jadwal pembelajaran (cermin tombol "Hapus" di e-Rapor).
     */
    public function destroyPembelajaran(JadwalPelajaran $pembelajaran)
    {
        $pembelajaran->delete();

        return back()->with('success', 'Jadwal pembelajaran berhasil dihapus.');
    }

    public function tanggalRapor()
    {
        $daftar = TanggalRapor::orderByDesc('tahun_ajaran')->orderBy('semester')->get();

        return view('admin.referensi.tanggal-rapor', compact('daftar'));
    }

    public function storeTanggalRapor(Request $request)
    {
        $validated = $request->validate([
            'tahun_ajaran' => 'required|string|max:20',
            'semester' => 'required|in:ganjil,genap',
            'tanggal' => 'nullable|date',
            'tempat' => 'nullable|string|max:255',
            'keterangan' => 'nullable|string|max:500',
        ]);

        TanggalRapor::updateOrCreate(
            ['tahun_ajaran' => $validated['tahun_ajaran'], 'semester' => $validated['semester']],
            $validated
        );

        return redirect()->route('admin.referensi.tanggal-rapor')
            ->with('success', 'Tanggal rapor berhasil disimpan.');
    }

    public function destroyTanggalRapor(TanggalRapor $tanggalRapor)
    {
        $tanggalRapor->delete();

        return redirect()->route('admin.referensi.tanggal-rapor')
            ->with('success', 'Tanggal rapor berhasil dihapus.');
    }

    public function kelompokMapel()
    {
        $mapel = MataPelajaran::where('aktif', true)->orderBy('kelompok')->orderBy('nama')->get();
        $kelompokList = MataPelajaran::where('aktif', true)->whereNotNull('kelompok')
            ->distinct()->pluck('kelompok');

        return view('admin.referensi.kelompok-mapel', compact('mapel', 'kelompokList'));
    }

    public function mappingRapor()
    {
        $mapel = MataPelajaran::where('aktif', true)->orderBy('urutan')->orderBy('nama')->get();

        return view('admin.referensi.mapping-rapor', compact('mapel'));
    }

    public function updateMapelMeta(Request $request)
    {
        \Log::info('Mapel meta update request', ['data' => $request->all()]);

        $validated = $request->validate([
            'mapel' => 'required|array',
            'mapel.*.kelompok' => 'nullable|string|max:50',
            'mapel.*.urutan' => 'nullable|integer|min:0|max:999',
            'mapel.*.masuk_transkrip' => 'nullable|boolean',
        ]);

        \Log::info('Mapel meta validated', ['validated' => $validated]);

        foreach ($validated['mapel'] as $id => $row) {
            MataPelajaran::where('id', $id)->update([
                'kelompok' => $row['kelompok'] ?? null,
                'urutan' => $row['urutan'] ?? 0,
                'masuk_transkrip' => (bool) ($row['masuk_transkrip'] ?? false),
            ]);
        }

        return back()->with('success', 'Kelompok, urutan, dan status transkrip mapel berhasil disimpan.');
    }

    public function ekstrakurikuler()
    {
        $ekskul = Ekstrakurikuler::with('pembina')->orderBy('nama')->get();
        $guru = User::where('peran', 'guru')->where('aktif', true)->orderBy('nama_lengkap')->get();

        return view('admin.referensi.ekstrakurikuler', compact('ekskul', 'guru'));
    }

    public function storeEkstrakurikuler(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:100',
            'pembina_id' => 'nullable|exists:users,id',
            'deskripsi' => 'nullable|string',
        ]);

        Ekstrakurikuler::create(array_merge($validated, ['aktif' => true]));

        return redirect()->route('admin.referensi.ekstrakurikuler')
            ->with('success', 'Ekstrakurikuler berhasil ditambahkan.');
    }

    public function updateGelarPtk(Request $request)
    {
        $validated = $request->validate([
            'ptk' => 'required|array',
            'ptk.*.gelar_depan' => 'nullable|string|max:50',
            'ptk.*.gelar_belakang' => 'nullable|string|max:100',
        ]);

        foreach ($validated['ptk'] as $id => $row) {
            Ptk::where('id', $id)->update([
                'gelar_depan' => $row['gelar_depan'] ?: null,
                'gelar_belakang' => $row['gelar_belakang'] ?: null,
            ]);
        }

        return back()->with('success', 'Gelar guru berhasil disimpan.');
    }

    public function destroyEkstrakurikuler(Ekstrakurikuler $ekstrakurikuler)
    {
        $ekstrakurikuler->delete();

        return redirect()->route('admin.referensi.ekstrakurikuler')
            ->with('success', 'Ekstrakurikuler berhasil dihapus.');
    }

    public function logoTtd()
    {
        $sekolah = SekolahSettings::first();
        $pengaturan = $sekolah?->pengaturan ?? [];

        return view('admin.referensi.logo-ttd', compact('sekolah', 'pengaturan'));
    }

    public function storeLogoTtd(Request $request)
    {
        $request->validate([
            'logo_pemda' => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
            'logo_sekolah' => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
            'ttd_kepsek' => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
            'kop_sekolah' => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
        ], [
            '*.image' => 'File harus berupa gambar.',
            '*.mimes' => 'Format gambar harus jpeg, jpg, atau png.',
            '*.max' => 'Ukuran gambar maksimal 2MB.',
        ]);

        $sekolah = SekolahSettings::first();
        if (!$sekolah) {
            $sekolah = SekolahSettings::create(['guru_id' => auth()->id()]);
        }

        $pengaturan = $sekolah->pengaturan ?? [];
        foreach (['logo_pemda', 'logo_sekolah', 'ttd_kepsek', 'kop_sekolah'] as $key) {
            if ($request->hasFile($key)) {
                if (!empty($pengaturan[$key]) && Storage::disk('public')->exists($pengaturan[$key])) {
                    Storage::disk('public')->delete($pengaturan[$key]);
                }
                $pengaturan[$key] = $request->file($key)->store('identitas', 'public');
            }
        }
        $sekolah->update(['pengaturan' => $pengaturan]);

        return redirect()->route('admin.referensi.logo-ttd')
            ->with('success', 'Logo dan TTD berhasil disimpan.');
    }

    public function fotoSiswa()
    {
        $total = Siswa::count();
        $denganFoto = Siswa::whereNotNull('foto')->count();

        return view('admin.referensi.foto-siswa', compact('total', 'denganFoto'));
    }

    public function storeFotoSiswa(Request $request)
    {
        $request->validate([
            'foto' => 'required|array|max:50',
            'foto.*' => 'image|mimes:jpeg,jpg,png|max:2048',
        ], [
            'foto.*.image' => 'Semua file harus berupa gambar.',
            'foto.*.mimes' => 'Format gambar harus jpeg, jpg, atau png.',
            'foto.*.max' => 'Ukuran tiap gambar maksimal 2MB.',
        ]);

        $cocok = 0;
        $tidakCocok = [];

        foreach ($request->file('foto') as $file) {
            $kunci = trim(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $siswa = Siswa::where('nis', $kunci)->orWhere('nisn', $kunci)->first();

            if (!$siswa) {
                $tidakCocok[] = $file->getClientOriginalName();
                continue;
            }

            if ($siswa->foto && Storage::disk('public')->exists($siswa->foto)) {
                Storage::disk('public')->delete($siswa->foto);
            }
            $siswa->update(['foto' => $file->store('foto-siswa', 'public')]);
            $cocok++;
        }

        return redirect()->route('admin.referensi.foto-siswa')->with([
            'success' => "{$cocok} foto berhasil dipasangkan, " . count($tidakCocok) . ' tidak cocok.',
            'foto_tidak_cocok' => array_slice($tidakCocok, 0, 20),
        ]);
    }
}
