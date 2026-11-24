<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Elearning\KelasDigitalPertemuan;
use App\Models\HasilKuis;
use App\Models\Kuis;
use App\Models\Rombel;
use App\Models\Siswa;
use App\Services\Elearning\KelasDigitalAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KuisController extends Controller
{
    use HasSiswaLookup;

    private function kuisUntukSiswaQuery(Siswa $siswa): Builder
    {
        $kelasKandidat = array_values(array_unique(array_filter([
            trim((string) ($siswa->kelas ?? '')),
            $siswa->rombel_id ? trim((string) Rombel::whereKey($siswa->rombel_id)->value('nama_rombel')) : '',
        ])));

        $kelasDigitalIds = app(KelasDigitalAccessService::class)
            ->queryForSiswa($siswa)
            ->pluck('id')
            ->all();

        $kuisKelasDigitalIds = $kelasDigitalIds === []
            ? []
            : KelasDigitalPertemuan::query()
                ->whereNotNull('kuis_id')
                ->where('dipublikasikan', true)
                ->whereHas('modul', fn ($q) => $q->whereIn('kelas_digital_id', $kelasDigitalIds)->where('dipublikasikan', true))
                ->pluck('kuis_id')
                ->unique()
                ->values()
                ->all();

        return Kuis::query()
            ->where('aktif', true)
            ->where(function (Builder $q) use ($siswa, $kelasKandidat, $kuisKelasDigitalIds) {
                $matchedAny = false;

                if (! empty($siswa->guru_id)) {
                    $q->where('guru_id', $siswa->guru_id);
                    $matchedAny = true;
                }

                if ($kelasKandidat !== []) {
                    if ($matchedAny) {
                        $q->orWhereIn('kelas', $kelasKandidat);
                    } else {
                        $q->whereIn('kelas', $kelasKandidat);
                        $matchedAny = true;
                    }
                }

                if ($kuisKelasDigitalIds !== []) {
                    if ($matchedAny) {
                        $q->orWhereIn('id', $kuisKelasDigitalIds);
                    } else {
                        $q->whereIn('id', $kuisKelasDigitalIds);
                        $matchedAny = true;
                    }
                }

                if (! $matchedAny) {
                    $q->whereRaw('1 = 0');
                }
            });
    }

    public function index()
    {
        $siswa = $this->getSiswa();

        if (! $siswa) {
            return view('siswa.kuis.index', ['kuis' => collect(), 'hasilKuis' => collect(), 'siswa' => null]);
        }

        $kuis = $this->kuisUntukSiswaQuery($siswa)
            ->orderBy('tanggal', 'desc')
            ->get();

        $hasilKuis = HasilKuis::where('siswa_id', $siswa->id)->get();

        return view('siswa.kuis.index', compact('kuis', 'hasilKuis', 'siswa'));
    }

    public function show($kuis)
    {
        $siswa = $this->getSiswa();

        if (! $siswa) {
            abort(404);
        }

        $kuis = $this->kuisUntukSiswaQuery($siswa)
            ->where('id', $kuis)
            ->firstOrFail();

        $sudahMengerjakan = HasilKuis::where('kuis_id', $kuis->id)
            ->where('siswa_id', $siswa->id)
            ->first();

        return view('siswa.kuis.show', compact('kuis', 'siswa', 'sudahMengerjakan'));
    }

    public function submit(Request $request, $kuis)
    {
        $siswa = $this->getSiswa();

        if (! $siswa) {
            abort(404);
        }

        $kuis = $this->kuisUntukSiswaQuery($siswa)
            ->where('id', $kuis)
            ->firstOrFail();

        $sudahMengerjakan = HasilKuis::where('kuis_id', $kuis->id)
            ->where('siswa_id', $siswa->id)
            ->first();

        if ($sudahMengerjakan) {
            return redirect()->route('siswa.kuis.index')
                ->with('error', 'Anda sudah mengerjakan kuis ini.');
        }

        // Enforce deadline di server: timer JS bisa dimatikan siswa.
        // Toleransi 5 menit untuk submit yang sedang transit saat waktu habis.
        if ($kuis->batas_waktu && now()->greaterThan($kuis->batas_waktu->copy()->addMinutes(5))) {
            return redirect()->route('siswa.kuis.index')
                ->with('error', 'Waktu pengerjaan kuis ini sudah berakhir.');
        }

        $validated = $request->validate([
            'jawaban' => 'required|array|max:200',
            'jawaban.*' => 'nullable|string|max:5000',
            'durasi' => 'nullable|integer|min:0|max:86400',
        ]);

        $soal = $kuis->soal ?? [];
        $benar = 0;
        $total = count($soal);
        $jawabanDetail = [];

        foreach ($soal as $i => $item) {
            $jawabanSiswa = $validated['jawaban'][$i] ?? null;
            $isBenar = ($jawabanSiswa === ($item['kunci'] ?? null));
            if ($isBenar) {
                $benar++;
            }
            $jawabanDetail[] = [
                'no' => $i + 1,
                'jawaban' => $jawabanSiswa,
                'kunci' => $item['kunci'] ?? null,
                'benar' => $isBenar,
            ];
        }

        $skor = $total > 0 ? round(($benar / $total) * 100) : 0;
        $tuntas = $skor >= $kuis->kkm;

        // PERBAIKAN: tabel hasil_kuis sudah memiliki unique index
        // (kuis_id, siswa_id) sejak migrasi 2026_09_09_075733. Pada balapan
        // submit bersamaan, request kedua melanggar index tersebut dan
        // sebelumnya melempar QueryException -> HTTP 500 padahal jawabannya
        // sudah tersimpan oleh request pertama. Kini pelanggaran itu ditangkap
        // dan diperlakukan sebagai "sudah tersimpan".
        try {
            DB::transaction(function () use ($kuis, $siswa, $benar, $total, $skor, $tuntas, $validated, $jawabanDetail) {
                $exists = HasilKuis::where('kuis_id', $kuis->id)
                    ->where('siswa_id', $siswa->id)
                    ->lockForUpdate()
                    ->exists();

                if ($exists) {
                    return;
                }

                HasilKuis::create([
                    'uuid' => Str::uuid(),
                    'guru_id' => $kuis->guru_id ?? $siswa->guru_id,
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
                    'durasi' => $validated['durasi'] ?? 0,
                    'sumber_soal' => $kuis->sumber,
                    'diisi_oleh' => 'siswa',
                    'jawaban' => $jawabanDetail,
                ]);
            });
        } catch (UniqueConstraintViolationException $e) {
            // Hanya pelanggaran unique hasil_kuis (kuis_id, siswa_id) yang
            // diperlakukan sebagai "sudah tersimpan" (index default:
            // hasil_kuis_kuis_id_siswa_id_unique).
            Log::warning('Submit kuis ganda dicegah (kuis_id '.$kuis->id.', siswa_id '.$siswa->id.', index: '.($e->index ?? '-').').');

            return redirect()->route('siswa.kuis.index')
                ->with('error', 'Jawaban Anda sudah tersimpan sebelumnya.');
        }

        return redirect()->route('siswa.kuis.index')
            ->with('success', 'Kuis berhasil dikirim. Skor Anda: '.$skor);
    }
}
