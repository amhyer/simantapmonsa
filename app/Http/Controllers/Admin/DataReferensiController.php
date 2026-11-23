<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ekstrakurikuler;
use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\NilaiErapot;
use App\Models\PengaturanGuru;
use App\Models\Ptk;
use App\Models\SekolahSettings;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TanggalRapor;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Services\JadwalPelajaranResolver;
use App\Services\Media\SiswaFotoStorage;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class DataReferensiController extends Controller
{
    private function schoolGuruRule()
    {
        return Rule::exists('users', 'id')->where(function ($query) {
            $query->where('peran', 'guru')->where('aktif', true)->whereNull('deleted_at');
            if (TenantContext::check()) {
                $query->where('tenant_id', TenantContext::id());
            } else {
                $query->whereNull('tenant_id')->where('tenant_owned', false);
            }
        });
    }

    public function guru()
    {
        $ptk = Ptk::with('semester')->orderBy('nama')->get();

        return view('admin.referensi.guru', compact('ptk'));
    }

    /**
     * Tambah data guru/PTK manual yang belum masuk di Dapodik.
     */
    public function storeGuru(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'nip' => 'nullable|string|max:50',
            'nuptk' => 'nullable|string|max:50',
            'jk' => 'nullable|in:L,P',
            'jenis_ptk' => 'required|string|max:100',
            'jabatan' => 'nullable|string|max:255',
            'gelar_depan' => 'nullable|string|max:50',
            'gelar_belakang' => 'nullable|string|max:100',
            'buat_akun' => 'nullable|boolean',
            'nama_pengguna' => 'nullable|string|max:100',
            'kata_sandi' => ['nullable', 'string', Password::defaults()],
        ]);

        $semester = Semester::orderByDesc('semester_id')->first()
            ?? Semester::orderByDesc('id')->first();

        $ptk = Ptk::create([
            'nama' => trim($validated['nama']),
            'nip' => ! empty($validated['nip']) ? trim($validated['nip']) : null,
            'nuptk' => ! empty($validated['nuptk']) ? trim($validated['nuptk']) : null,
            'jk' => $validated['jk'] ?? null,
            'jenis_ptk' => $validated['jenis_ptk'],
            'jabatan' => ! empty($validated['jabatan']) ? trim($validated['jabatan']) : null,
            'gelar_depan' => ! empty($validated['gelar_depan']) ? trim($validated['gelar_depan']) : null,
            'gelar_belakang' => ! empty($validated['gelar_belakang']) ? trim($validated['gelar_belakang']) : null,
            'dapodik_id' => null,
            'semester_id' => $semester?->id,
        ]);

        $buatAkun = $request->boolean('buat_akun', true);
        $generatedPassword = null;
        $finalUsername = null;
        if ($buatAkun) {
            $username = ! empty($validated['nama_pengguna'])
                ? trim($validated['nama_pengguna'])
                : (! empty($validated['nip']) ? trim($validated['nip']) : (! empty($validated['nuptk']) ? trim($validated['nuptk']) : null));

            if (! $username) {
                $base = Str::slug($validated['nama'], '');
                $username = ! empty($base) ? $base : 'guru'.random_int(100, 999);
            }

            $finalUsername = $username;
            $counter = 1;
            while (User::forSchool()->withTrashed()->where('nama_pengguna', $finalUsername)->exists()) {
                $finalUsername = $username.'.'.$counter;
                $counter++;
            }

            $jenisLower = strtolower($validated['jenis_ptk']);
            $peran = 'guru';
            if (str_contains($jenisLower, 'kepala sekolah')) {
                $peran = 'kepsek';
            } elseif (
                in_array($jenisLower, ['tendik', 'tenaga kependidikan', 'tenaga', 'kebersihan', 'tenaga kebersihan', 'security', 'satpam', 'keamanan'])
                || str_contains($jenisLower, 'tendik')
                || str_contains($jenisLower, 'kebersihan')
                || str_contains($jenisLower, 'security')
                || str_contains($jenisLower, 'satpam')
                || str_contains($jenisLower, 'keamanan')
            ) {
                $peran = 'tendik';
            }

            $password = ! empty($validated['kata_sandi']) ? $validated['kata_sandi'] : ($generatedPassword = Str::random(12));

            $user = User::create([
                'uuid' => (string) Str::uuid(),
                'nama_lengkap' => $ptk->nama,
                'nama_pengguna' => $finalUsername,
                'kata_sandi' => Hash::make($password),
                'peran' => $peran,
                'nip' => $ptk->nip,
                'aktif' => true,
                'force_password_change' => true,
                'dapodik_id' => null,
            ]);

            if ($peran === 'guru') {
                PengaturanGuru::firstOrCreate(
                    ['guru_id' => $user->id],
                    [
                        'nama_guru' => $user->nama_lengkap,
                        'kelas' => $ptk->jabatan,
                        'kkm' => function_exists('getKKM') ? getKKM() : 75,
                        'semester' => $semester?->nama_semester ? ucfirst($semester->nama_semester) : 'Ganjil',
                        'tahun_pelajaran' => $semester?->tahun_ajaran ?? (date('Y').'/'.(date('Y') + 1)),
                        'pembaruan' => now(),
                    ]
                );
            }
        }

        $pesan = 'Guru non-Dapodik ('.$ptk->nama_gelar.') berhasil ditambahkan.';
        if ($generatedPassword !== null) {
            $pesan .= " Akun: {$finalUsername} | Kata sandi awal (tampil sekali): {$generatedPassword}";
        }

        return redirect()->route('admin.referensi.guru')
            ->with('success', $pesan);
    }

    /**
     * Hapus data guru non-Dapodik.
     */
    public function destroyGuru(Ptk $ptk)
    {
        if ($ptk->dapodik_id !== null) {
            return back()->with('error', 'Guru dari Dapodik tidak dapat dihapus manual. Silakan sinkronkan kembali dari Dapodik.');
        }

        $nama = $ptk->nama_gelar;
        $ptk->delete();

        return redirect()->route('admin.referensi.guru')
            ->with('success', "Data guru {$nama} berhasil dihapus.");
    }

    public function pembelajaran()
    {
        $jadwal = JadwalPelajaran::with(['guru' => fn ($q) => $q->forSchool(), 'rombel', 'semester'])
            ->withCount('nilaiErapot')
            ->orderBy('hari')
            ->orderBy('jam_mulai')
            ->get();
        $daftarMapel = MataPelajaran::where('aktif', true)->orderBy('nama')->get();
        $daftarGuru = User::forSchool()->where('peran', 'guru')->where('aktif', true)->orderBy('nama_lengkap')->get();

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
            'guru_id' => ['required', $this->schoolGuruRule()],
            'jam_mulai' => 'required|string|max:10',
            'jam_selesai' => 'required|string|max:10',
            'ruangan' => 'nullable|string|max:50',
        ]);

        JadwalPelajaran::create($validated);

        return back()->with('success', 'Jadwal pembelajaran berhasil ditambahkan.');
    }

    /**
     * Hapus jadwal pembelajaran (cermin tombol "Hapus" di e-Rapor).
     * FK nullOnDelete: nilai terkait dipertahankan, kolomnya jadi NULL
     * (terlihat sebagai "belum terpetakan" di alignment-check).
     */
    public function destroyPembelajaran($pembelajaran)
    {
        $pembelajaran = JadwalPelajaran::findOrFail($pembelajaran);
        try {
            $pembelajaran->delete();
        } catch (QueryException $e) {
            // kehadiran_mapel.jadwal_pelajaran_id memakai RESTRICT: jangan
            // biarkan tombol Hapus memunculkan 500 saat data masih terpakai.
            return back()->with('error', 'Jadwal pembelajaran masih digunakan oleh data kehadiran mapel dan tidak dapat dihapus.');
        }

        return back()->with('success', 'Jadwal pembelajaran berhasil dihapus.');
    }

    /**
     * Halaman alignment-check e-Rapor vs pembelajaran (view: Fase 5).
     */
    public function alignmentCheck(JadwalPelajaranResolver $resolver)
    {
        $stats = $resolver->alignmentStats();
        $unmatched = NilaiErapot::whereNull('jadwal_pelajaran_id')
            ->with('siswa:id,nama_peserta_didik')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('admin.referensi.alignment-check', compact('stats', 'unmatched'));
    }

    /** Retry the resolver for every score that is still not linked to a schedule. */
    public function refreshAlignment(JadwalPelajaranResolver $resolver)
    {
        $checked = 0;
        $matched = 0;

        NilaiErapot::query()
            ->whereNull('jadwal_pelajaran_id')
            ->orderBy('id')
            ->chunkById(250, function ($scores) use ($resolver, &$checked, &$matched): void {
                foreach ($scores as $score) {
                    $checked++;
                    $jadwalId = $resolver->resolve($score);

                    if ($jadwalId === null) {
                        continue;
                    }

                    $matched += NilaiErapot::query()
                        ->whereKey($score->getKey())
                        ->whereNull('jadwal_pelajaran_id')
                        ->update([
                            'jadwal_pelajaran_id' => $jadwalId,
                            'updated_at' => now(),
                        ]);
                }
            });

        $remaining = $resolver->alignmentStats()['unmatched'];

        return back()->with(
            'success',
            "Alignment selesai: {$matched} nilai baru terpetakan dari {$checked} yang diperiksa; {$remaining} masih belum terpetakan."
        );
    }

    /** Stream all unmatched scores as an Excel-friendly CSV file. */
    public function exportUnmatchedAlignment()
    {
        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                return;
            }

            // BOM helps Excel detect UTF-8 text such as Indonesian names.
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Nama siswa', 'NIS', 'Mata pelajaran', 'Kelas', 'Semester', 'Tahun ajaran', 'Status pemetaan',
            ], ',', '"', '');

            NilaiErapot::query()
                ->whereNull('jadwal_pelajaran_id')
                ->with('siswa:id,nama_peserta_didik,nis')
                ->orderBy('id')
                ->chunkById(500, function ($scores) use ($output): void {
                    foreach ($scores as $score) {
                        $cells = [
                            $score->siswa?->nama_peserta_didik ?? 'Siswa dihapus',
                            $score->siswa?->nis ?? '-',
                            $score->mata_pelajaran ?: '(kosong)',
                            $score->kelas ?: '-',
                            $score->semester ?: '-',
                            $score->tahun_ajaran ?: '-',
                            'Belum terpetakan',
                        ];

                        fputcsv($output, array_map(fn ($cell) => $this->safeCsvCell($cell), $cells), ',', '"', '');
                    }
                });

            fclose($output);
        }, 'nilai-erapor-unmatched-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function safeCsvCell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[\\x00-\\x20]*[=+\\-@]/', $value) === 1
            ? "'".$value
            : $value;
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
        $ekskul = Ekstrakurikuler::with(['pembina' => fn ($q) => $q->forSchool()])->orderBy('nama')->get();
        $guru = User::forSchool()->where('peran', 'guru')->where('aktif', true)->orderBy('nama_lengkap')->get();

        return view('admin.referensi.ekstrakurikuler', compact('ekskul', 'guru'));
    }

    public function storeEkstrakurikuler(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:100',
            'pembina_id' => ['nullable', $this->schoolGuruRule()],
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
            'ptk.*.nip' => 'nullable|string|max:50',
            'ptk.*.gelar_depan' => 'nullable|string|max:50',
            'ptk.*.gelar_belakang' => 'nullable|string|max:100',
            'ptk.*.jenis_ptk' => 'nullable|string|max:100',
            'ptk.*.jabatan' => 'nullable|string|max:255',
        ]);

        $ids = array_keys($validated['ptk']);
        abort_unless(count($ids) === Ptk::whereIn('id', $ids)->count(), 404);
        foreach ($validated['ptk'] as $id => $row) {
            $ptkModel = Ptk::find($id);
            if (! $ptkModel) {
                continue;
            }
            $oldNip = $ptkModel->nip;
            $dataToUpdate = [
                'gelar_depan' => ($row['gelar_depan'] ?? null) ?: null,
                'gelar_belakang' => ($row['gelar_belakang'] ?? null) ?: null,
            ];

            if (array_key_exists('nip', $row)) {
                $dataToUpdate['nip'] = trim((string) ($row['nip'] ?? '')) ?: null;
            }
            if (array_key_exists('jenis_ptk', $row) && ! empty($row['jenis_ptk'])) {
                $dataToUpdate['jenis_ptk'] = trim($row['jenis_ptk']);
            }
            if (array_key_exists('jabatan', $row)) {
                $dataToUpdate['jabatan'] = trim($row['jabatan'] ?? '') ?: null;
            }

            $ptkModel->update($dataToUpdate);

            // Sinkronkan NIP, nama bergelar, peran, & jabatan ke akun User terkait jika ada
            $linkedUser = null;
            if (! empty($ptkModel->dapodik_id)) {
                $linkedUser = User::forSchool()->where('dapodik_id', $ptkModel->dapodik_id)->first();
            }
            if (! $linkedUser && ! empty($oldNip)) {
                $linkedUser = User::forSchool()->where(function ($q) use ($oldNip) {
                    $q->where('nip', $oldNip)->orWhere('nama_pengguna', $oldNip);
                })->first();
            }
            if (! $linkedUser) {
                $slug = Str::slug($ptkModel->nama, '');
                if ($slug !== '') {
                    $linkedUser = User::forSchool()->where('nama_pengguna', $slug)->first();
                }
            }
            if ($linkedUser) {
                $peranSync = 'guru';
                if ($ptkModel->isKepsek()) {
                    $peranSync = 'kepsek';
                } elseif (! $ptkModel->isGuru() && ! empty($ptkModel->jenis_ptk)) {
                    $peranSync = 'tendik';
                }
                $linkedUser->update([
                    'nip' => $ptkModel->nip,
                    'nama_lengkap' => $ptkModel->nama_gelar ?: $ptkModel->nama,
                    'peran' => $peranSync,
                ]);
            }
        }

        return back()->with('success', 'Data PTK (NIP rapor, gelar, jenis, dan jabatan) berhasil disimpan.');
    }

    public function destroyEkstrakurikuler($ekstrakurikuler)
    {
        $ekstrakurikuler = Ekstrakurikuler::findOrFail($ekstrakurikuler);
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
            'stempel_sekolah' => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
            'kop_sekolah' => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
            'nip_kepsek' => 'nullable|string|max:40',
        ], [
            '*.image' => 'File harus berupa gambar.',
            '*.mimes' => 'Format gambar harus jpeg, jpg, atau png.',
            '*.max' => 'Ukuran gambar maksimal 2MB.',
        ]);

        $tenantId = TenantContext::id() ?? auth()->user()?->tenant_id;
        $sekolah = SekolahSettings::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->first();
        if (! $sekolah) {
            // nama_sekolah wajib (NOT NULL). Arahkan admin mengisi identitas
            // sekolah dulu daripada membuat baris setengah jadi / error 500.
            return redirect()->route('admin.sekolah.index')
                ->with('error', 'Lengkapi Identitas Sekolah terlebih dahulu sebelum mengunggah logo dan tanda tangan.');
        }

        $pengaturan = $sekolah->pengaturan ?? [];
        $privateKeys = ['ttd_kepsek', 'stempel_sekolah'];
        foreach (['logo_pemda', 'logo_sekolah', 'ttd_kepsek', 'stempel_sekolah', 'kop_sekolah'] as $key) {
            if ($request->hasFile($key)) {
                if (! empty($pengaturan[$key])) {
                    foreach (['local', 'public'] as $disk) {
                        if (Storage::disk($disk)->exists($pengaturan[$key])) {
                            Storage::disk($disk)->delete($pengaturan[$key]);
                        }
                    }
                }
                if (in_array($key, $privateKeys, true)) {
                    $pengaturan[$key] = $request->file($key)->store('private/identitas', 'local');
                } else {
                    $pengaturan[$key] = $request->file($key)->store('identitas', 'public');
                }
            }
        }
        if ($request->has('nip_kepsek')) {
            $pengaturan['nip_kepsek'] = trim((string) $request->input('nip_kepsek', ''));
        }
        $sekolah->update(['pengaturan' => $pengaturan]);

        return redirect()->route('admin.referensi.logo-ttd')
            ->with('success', 'Logo dan TTD berhasil disimpan.');
    }

    public function fotoSiswa(Request $request)
    {
        $validated = $request->validate([
            'kelas' => ['nullable', 'string', 'max:50'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $kelasDipilih = trim((string) ($validated['kelas'] ?? ''));
        $cari = trim((string) ($validated['q'] ?? ''));
        $perPage = max(1, (int) config('simantap.foto_siswa_per_page', 50));

        $total = Siswa::count();
        $denganFoto = Siswa::whereNotNull('foto')->count();
        $daftarKelas = Siswa::query()
            ->whereNotNull('kelas')
            ->where('kelas', '!=', '')
            ->distinct()
            ->orderBy('kelas')
            ->pluck('kelas');

        $daftar = Siswa::query()
            ->select(['id', 'uuid', 'nama_peserta_didik', 'nis', 'nisn', 'kelas', 'foto'])
            ->when($kelasDipilih !== '', fn ($q) => $q->where('kelas', $kelasDipilih))
            ->when($cari !== '', function ($q) use ($cari): void {
                $like = '%'.mb_strtolower($cari).'%';
                $q->where(function ($sub) use ($like): void {
                    $sub->whereRaw('LOWER(nama_peserta_didik) LIKE ?', [$like])
                        ->orWhereRaw("LOWER(COALESCE(nis, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(nisn, '')) LIKE ?", [$like]);
                });
            })
            ->orderBy('nama_peserta_didik')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.referensi.foto-siswa', compact(
            'total',
            'denganFoto',
            'daftar',
            'daftarKelas',
            'kelasDipilih',
            'cari'
        ));
    }

    public function storeFotoSiswaSatuan(Request $request, Siswa $siswa, SiswaFotoStorage $fotoStorage)
    {
        $request->validate([
            'foto' => 'required|image|mimes:jpeg,jpg,png,webp|max:2048',
        ], [
            'foto.image' => 'File harus berupa gambar.',
            'foto.mimes' => 'Format gambar harus jpeg, jpg, png, atau webp.',
            'foto.max' => 'Ukuran gambar maksimal 2MB.',
        ]);

        $newPath = $fotoStorage->storeUploadedFile($request->file('foto'));
        if ($newPath === null) {
            return back()->withErrors(['foto' => 'File gambar tidak valid.']);
        }

        $fotoStorage->deleteFromAllDisks($siswa->foto);
        $siswa->update(['foto' => $newPath]);

        return back()->with('success', "Foto {$siswa->nama_peserta_didik} berhasil diperbarui.");
    }

    public function storeFotoSiswa(Request $request, SiswaFotoStorage $fotoStorage)
    {
        $request->validate([
            'foto' => 'nullable|array|max:100',
            'foto.*' => 'image|mimes:jpeg,jpg,png,webp|max:2048',
            'file_zip' => 'nullable|file|mimes:zip|max:51200',
            'reset_semua' => 'nullable|boolean',
        ], [
            'foto.*.image' => 'Semua file harus berupa gambar.',
            'foto.*.mimes' => 'Format gambar harus jpeg, jpg, png, atau webp.',
            'foto.*.max' => 'Ukuran tiap gambar maksimal 2MB.',
            'file_zip.mimes' => 'File arsip harus berformat .zip.',
            'file_zip.max' => 'Ukuran file .zip maksimal 50MB.',
        ]);

        if (! $request->hasFile('foto') && ! $request->hasFile('file_zip')) {
            return back()->with('error', 'Pilih file foto (.jpg/.png/.webp) atau arsip .zip terlebih dahulu.');
        }

        // Opsi Reset Semua Foto Siswa sebelum upload massal (sesuai e-Rapor SD)
        if ($request->boolean('reset_semua')) {
            $berfoto = Siswa::whereNotNull('foto')->get();
            foreach ($berfoto as $s) {
                $fotoStorage->deleteFromAllDisks($s->foto);
                $s->update(['foto' => null]);
            }
        }

        $cocok = 0;
        $tidakCocok = [];
        $maxZipEntries = max(1, (int) config('simantap.foto_zip_max_entries', 200));
        $maxZipBytes = max(1, (int) config('simantap.foto_zip_max_uncompressed_bytes', 100 * 1024 * 1024));

        // Batch-load seluruh kandidat siswa sekali saja (mencegah N+1 query di dalam loop ZIP/multi-file).
        $siswaByKey = [];
        $allSiswa = Siswa::query()->get(['id', 'tenant_id', 'nisn', 'nis', 'foto']);
        foreach ($allSiswa as $s) {
            if (! empty($s->nisn)) {
                $siswaByKey[(string) $s->nisn] = $s;
            }
            if (! empty($s->nis)) {
                $siswaByKey[(string) $s->nis] = $s;
            }
        }

        // 1. Proses ekstraksi file .zip jika diunggah (ala `#modal_masal` e-Rapor SD)
        if ($request->hasFile('file_zip') && class_exists(\ZipArchive::class)) {
            $zipFile = $request->file('file_zip');
            $zip = new \ZipArchive;
            if ($zip->open($zipFile->getRealPath()) === true) {
                $processedEntries = 0;
                $totalUncompressedBytes = 0;

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entryName = $zip->getNameIndex($i);
                    if (! $entryName || str_ends_with($entryName, '/') || str_starts_with(basename($entryName), '.')) {
                        continue;
                    }

                    $processedEntries++;
                    if ($processedEntries > $maxZipEntries) {
                        $tidakCocok[] = 'Batas maksimal entri ZIP terlampaui.';

                        break;
                    }

                    $kunci = trim(pathinfo($entryName, PATHINFO_FILENAME));
                    $siswa = $siswaByKey[$kunci] ?? null;
                    if (! $siswa) {
                        $tidakCocok[] = basename($entryName);

                        continue;
                    }

                    // Baca stream secara terbatas terhadap byte nyata (tidak hanya percaya header/stat ZIP)
                    $remainingBudget = $maxZipBytes - $totalUncompressedBytes;
                    if ($remainingBudget <= 0) {
                        $tidakCocok[] = 'Batas total ukuran terdekompresi ZIP terlampaui.';

                        break;
                    }

                    $entryLimit = min(SiswaFotoStorage::MAX_IMAGE_BYTES, $remainingBudget);
                    $stream = $zip->getStream($entryName);
                    if ($stream === false) {
                        $tidakCocok[] = basename($entryName);

                        continue;
                    }

                    $contents = '';
                    $exceededEntry = false;
                    while (! feof($stream)) {
                        $chunk = fread($stream, 8192);
                        if ($chunk === false) {
                            break;
                        }
                        $contents .= $chunk;
                        if (strlen($contents) > $entryLimit) {
                            $exceededEntry = true;

                            break;
                        }
                    }
                    fclose($stream);

                    $totalUncompressedBytes += strlen($contents);
                    if ($exceededEntry) {
                        if ($totalUncompressedBytes > $maxZipBytes) {
                            $tidakCocok[] = 'Batas total ukuran terdekompresi ZIP terlampaui.';

                            break;
                        }
                        $tidakCocok[] = basename($entryName);

                        continue;
                    }

                    $targetPath = $fotoStorage->storeRawBytes($contents);
                    if ($targetPath === null) {
                        $tidakCocok[] = basename($entryName);

                        continue;
                    }

                    $fotoStorage->deleteFromAllDisks($siswa->foto);
                    $siswa->update(['foto' => $targetPath]);
                    $cocok++;
                }
                $zip->close();
            }
        }

        // 2. Proses multiple file gambar langsung (`foto[]`)
        if ($request->hasFile('foto')) {
            foreach ($request->file('foto') as $file) {
                $kunci = trim(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
                $siswa = $siswaByKey[$kunci] ?? null;

                if (! $siswa) {
                    $tidakCocok[] = $file->getClientOriginalName();

                    continue;
                }

                $newPath = $fotoStorage->storeUploadedFile($file);
                if ($newPath === null) {
                    $tidakCocok[] = $file->getClientOriginalName();

                    continue;
                }

                $fotoStorage->deleteFromAllDisks($siswa->foto);
                $siswa->update(['foto' => $newPath]);
                $cocok++;
            }
        }

        return redirect()->route('admin.referensi.foto-siswa')->with([
            'success' => "{$cocok} foto berhasil dipasangkan, ".count($tidakCocok).' tidak cocok.',
            'foto_tidak_cocok' => array_slice($tidakCocok, 0, 20),
        ]);
    }

    public function destroyFotoSiswa(Siswa $siswa, SiswaFotoStorage $fotoStorage)
    {
        $fotoStorage->deleteFromAllDisks($siswa->foto);
        $siswa->update(['foto' => null]);

        return back()->with('success', "Foto {$siswa->nama_peserta_didik} berhasil dihapus.");
    }

    public function resetSemuaFotoSiswa(SiswaFotoStorage $fotoStorage)
    {
        $berfoto = Siswa::whereNotNull('foto')->get();
        $jumlah = 0;
        foreach ($berfoto as $siswa) {
            $fotoStorage->deleteFromAllDisks($siswa->foto);
            $siswa->update(['foto' => null]);
            $jumlah++;
        }

        return back()->with('success', "Berhasil mereset {$jumlah} foto siswa.");
    }
}
