<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Elearning\KelasDigital;
use App\Models\Elearning\KelasDigitalMateriItem;
use App\Models\Elearning\KelasDigitalPengumpulan;
use App\Models\Elearning\KelasDigitalPertemuan;
use App\Models\Elearning\KelasDigitalProgress;
use App\Models\Elearning\KelasDigitalTugas;
use App\Models\HasilKuis;
use App\Models\Siswa;
use App\Services\Elearning\ElearningFileService;
use App\Services\Elearning\KelasDigitalAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KelasDigitalController extends Controller
{
    use HasSiswaLookup;

    public function __construct(
        private readonly KelasDigitalAccessService $access,
        private readonly ElearningFileService $files,
    ) {}

    public function index()
    {
        $siswa = $this->getSiswa();

        if (! $siswa) {
            return view('siswa.kelas-digital.index', [
                'siswa' => null,
                'kelasList' => collect(),
                'progressMap' => [],
            ]);
        }

        $kelasList = $this->access
            ->queryForSiswa($siswa)
            ->with(['guru', 'rombel'])
            ->withCount([
                'modul' => fn ($q) => $q->where('dipublikasikan', true),
                'tugas',
            ])
            ->orderBy('mata_pelajaran')
            ->orderBy('nama_kelas')
            ->get();

        $progressMap = $this->access->progressSummaryForSiswa($siswa, $kelasList);

        return view('siswa.kelas-digital.index', compact('siswa', 'kelasList', 'progressMap'));
    }

    public function show(KelasDigital $kelasDigital)
    {
        $siswa = $this->requireSiswa();
        abort_unless($this->access->siswaCanAccessKelas($siswa, $kelasDigital), 403, 'Anda tidak terdaftar pada kelas digital ini.');

        $kelasDigital->load([
            'guru',
            'rombel',
            'modul' => fn ($q) => $q->where('dipublikasikan', true)->orderBy('urutan')->orderBy('id'),
            'modul.pertemuan' => fn ($q) => $q->where('dipublikasikan', true)->orderBy('urutan')->orderBy('id')->withCount(['items', 'tugas'])->with('kuis'),
        ]);

        $pertemuanIds = $kelasDigital->modul
            ->flatMap(fn ($m) => $m->pertemuan->pluck('id'))
            ->values()
            ->all();

        $selesaiIds = $pertemuanIds === []
            ? []
            : array_flip(
                KelasDigitalProgress::query()
                    ->where('siswa_id', $siswa->id)
                    ->where('selesai', true)
                    ->whereIn('pertemuan_id', $pertemuanIds)
                    ->pluck('pertemuan_id')
                    ->all()
            );

        $totalPertemuan = count($pertemuanIds);
        $selesaiCount = count($selesaiIds);
        $persenProgress = $totalPertemuan > 0 ? (int) round(($selesaiCount / $totalPertemuan) * 100) : 0;

        return view('siswa.kelas-digital.show', compact(
            'siswa',
            'kelasDigital',
            'selesaiIds',
            'totalPertemuan',
            'selesaiCount',
            'persenProgress'
        ));
    }

    public function pertemuan(KelasDigital $kelasDigital, KelasDigitalPertemuan $pertemuan)
    {
        $siswa = $this->requireSiswa();
        abort_unless($this->access->siswaCanAccessKelas($siswa, $kelasDigital), 403, 'Anda tidak terdaftar pada kelas digital ini.');

        $pertemuan->load(['modul', 'items', 'materi', 'kuis', 'tugas']);
        abort_unless(
            $pertemuan->modul !== null
            && (int) $pertemuan->modul->kelas_digital_id === (int) $kelasDigital->id
            && $pertemuan->modul->dipublikasikan
            && $pertemuan->dipublikasikan,
            404
        );

        $progress = KelasDigitalProgress::query()
            ->where('pertemuan_id', $pertemuan->id)
            ->where('siswa_id', $siswa->id)
            ->first();

        $tugasIds = $pertemuan->tugas->pluck('id')->all();
        $pengumpulanMap = $tugasIds === []
            ? collect()
            : KelasDigitalPengumpulan::query()
                ->where('siswa_id', $siswa->id)
                ->whereIn('tugas_id', $tugasIds)
                ->get()
                ->keyBy('tugas_id');

        $hasilKuis = null;
        if ($pertemuan->kuis_id) {
            $hasilKuis = HasilKuis::query()
                ->where('kuis_id', $pertemuan->kuis_id)
                ->where('siswa_id', $siswa->id)
                ->latest('id')
                ->first();
        }

        return view('siswa.kelas-digital.pertemuan', compact(
            'siswa',
            'kelasDigital',
            'pertemuan',
            'progress',
            'pengumpulanMap',
            'hasilKuis'
        ));
    }

    public function tandaiSelesai(Request $request, KelasDigital $kelasDigital, KelasDigitalPertemuan $pertemuan)
    {
        $siswa = $this->requireSiswa();
        abort_unless($this->access->siswaCanAccessKelas($siswa, $kelasDigital), 403);

        $pertemuan->loadMissing('modul');
        abort_unless(
            $pertemuan->modul !== null
            && (int) $pertemuan->modul->kelas_digital_id === (int) $kelasDigital->id
            && $pertemuan->modul->dipublikasikan
            && $pertemuan->dipublikasikan,
            404
        );

        $selesai = $request->boolean('selesai', true);

        $record = KelasDigitalProgress::withTrashed()
            ->where('pertemuan_id', $pertemuan->id)
            ->where('siswa_id', $siswa->id)
            ->first();

        if ($record) {
            if ($record->trashed()) {
                $record->restore();
            }
            $record->update([
                'selesai' => $selesai,
                'selesai_pada' => $selesai ? now() : null,
            ]);
        } else {
            KelasDigitalProgress::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $kelasDigital->tenant_id,
                'pertemuan_id' => $pertemuan->id,
                'siswa_id' => $siswa->id,
                'selesai' => $selesai,
                'selesai_pada' => $selesai ? now() : null,
            ]);
        }

        return redirect()
            ->route('siswa.kelas-digital.pertemuan', [$kelasDigital, $pertemuan])
            ->with('success', $selesai ? 'Pertemuan ditandai selesai dibaca.' : 'Status selesai dibatalkan.');
    }

    public function kumpulTugas(Request $request, KelasDigital $kelasDigital, KelasDigitalTugas $tugas)
    {
        $siswa = $this->requireSiswa();
        abort_unless($this->access->siswaCanAccessKelas($siswa, $kelasDigital), 403);
        abort_unless((int) $tugas->kelas_digital_id === (int) $kelasDigital->id, 404);

        $tugas->loadMissing('pertemuan.modul');
        abort_unless(
            $tugas->pertemuan !== null
            && $tugas->pertemuan->modul !== null
            && $tugas->pertemuan->modul->dipublikasikan
            && $tugas->pertemuan->dipublikasikan,
            404
        );

        $validated = $request->validate([
            'jawaban_teks' => 'nullable|string|max:15000',
            'file' => ElearningFileService::validationRuleSiswa(),
        ]);

        $existing = KelasDigitalPengumpulan::withTrashed()
            ->where('tugas_id', $tugas->id)
            ->where('siswa_id', $siswa->id)
            ->first();

        $jawabanTeks = trim((string) ($validated['jawaban_teks'] ?? ''));
        $hasNewFile = $request->hasFile('file');
        $hasAnyFile = $hasNewFile || ($existing && ! $existing->trashed() && ! empty($existing->file_path));

        if ($tugas->tipe_pengumpulan === 'teks' && $jawabanTeks === '') {
            throw ValidationException::withMessages(['jawaban_teks' => 'Jawaban teks wajib diisi untuk tugas ini.']);
        }
        if ($tugas->tipe_pengumpulan === 'file' && ! $hasAnyFile) {
            throw ValidationException::withMessages(['file' => 'Lampiran berkas wajib diunggah untuk tugas ini.']);
        }
        if ($tugas->tipe_pengumpulan === 'keduanya' && $jawabanTeks === '' && ! $hasAnyFile) {
            throw ValidationException::withMessages(['jawaban_teks' => 'Isi jawaban teks atau unggah berkas tugas sebelum mengumpulkan.']);
        }

        $fileMeta = null;
        if ($hasNewFile) {
            $fileMeta = $this->files->store($request->file('file'), 'pengumpulan', $kelasDigital->tenant_id, 'file');
        }

        $terlambat = $tugas->batas_waktu !== null && now()->greaterThan($tugas->batas_waktu);
        $status = $terlambat ? 'terlambat' : 'dikumpulkan';

        if ($existing) {
            $oldPath = $existing->file_path;
            if ($existing->trashed()) {
                $existing->restore();
            }

            $existing->update([
                'jawaban_teks' => $jawabanTeks !== '' ? $jawabanTeks : $existing->jawaban_teks,
                'file_path' => $fileMeta['path'] ?? $existing->file_path,
                'file_nama_asli' => $fileMeta['original_name'] ?? $existing->file_nama_asli,
                'file_mime' => $fileMeta['mime'] ?? $existing->file_mime,
                'file_ukuran' => $fileMeta['size'] ?? $existing->file_ukuran,
                'status' => $status,
                'dikumpulkan_pada' => now(),
            ]);

            if ($fileMeta && $oldPath && $oldPath !== $fileMeta['path']) {
                $this->files->deleteQuietly($oldPath);
            }
        } else {
            KelasDigitalPengumpulan::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $kelasDigital->tenant_id,
                'tugas_id' => $tugas->id,
                'siswa_id' => $siswa->id,
                'jawaban_teks' => $jawabanTeks !== '' ? $jawabanTeks : null,
                'file_path' => $fileMeta['path'] ?? null,
                'file_nama_asli' => $fileMeta['original_name'] ?? null,
                'file_mime' => $fileMeta['mime'] ?? null,
                'file_ukuran' => $fileMeta['size'] ?? null,
                'status' => $status,
                'dikumpulkan_pada' => now(),
            ]);
        }

        return redirect()
            ->route('siswa.kelas-digital.pertemuan', [$kelasDigital, $tugas->pertemuan])
            ->with('success', $terlambat
                ? 'Tugas berhasil dikumpulkan (melewati batas waktu).'
                : 'Tugas berhasil dikumpulkan.');
    }

    public function downloadItem(KelasDigital $kelasDigital, KelasDigitalMateriItem $item)
    {
        $siswa = $this->requireSiswa();
        abort_unless($this->access->siswaCanAccessKelas($siswa, $kelasDigital), 403);

        $item->loadMissing('pertemuan.modul');
        abort_unless(
            $item->pertemuan !== null
            && $item->pertemuan->modul !== null
            && (int) $item->pertemuan->modul->kelas_digital_id === (int) $kelasDigital->id
            && $item->pertemuan->modul->dipublikasikan
            && $item->pertemuan->dipublikasikan,
            404
        );

        return $this->files->download($item->file_path, $item->file_nama_asli);
    }

    public function downloadLampiranTugas(KelasDigital $kelasDigital, KelasDigitalTugas $tugas)
    {
        $siswa = $this->requireSiswa();
        abort_unless($this->access->siswaCanAccessKelas($siswa, $kelasDigital), 403);
        abort_unless((int) $tugas->kelas_digital_id === (int) $kelasDigital->id, 404);

        return $this->files->download($tugas->lampiran_path, $tugas->lampiran_nama);
    }

    public function downloadPengumpulan(KelasDigital $kelasDigital, KelasDigitalPengumpulan $pengumpulan)
    {
        $siswa = $this->requireSiswa();
        abort_unless($this->access->siswaCanAccessKelas($siswa, $kelasDigital), 403);
        $pengumpulan->loadMissing('tugas');
        abort_unless((int) $pengumpulan->tugas?->kelas_digital_id === (int) $kelasDigital->id, 404);
        // Anti-IDOR antar-siswa di kelas yang sama: siswa hanya boleh mengunduh berkas miliknya sendiri.
        abort_unless((int) $pengumpulan->siswa_id === (int) $siswa->id, 403);

        return $this->files->download($pengumpulan->file_path, $pengumpulan->file_nama_asli);
    }

    private function requireSiswa(): Siswa
    {
        $siswa = $this->getSiswa();
        abort_if(! $siswa, 404, 'Data siswa belum tertaut pada akun ini.');

        return $siswa;
    }
}
