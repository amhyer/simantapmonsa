<?php

namespace App\Http\Controllers\Admin\Cbt;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cbt\CbtSessionStatusRequest;
use App\Http\Requests\Admin\Cbt\CbtSessionStoreRequest;
use App\Models\CbtResult;
use App\Models\CbtTestSession;
use App\Models\User;
use App\Services\CbtExamService;
use App\Services\Exam\LegacyExamMapper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Monitoring & pengaturan sesi ujian CBT (tampilan admin, lintas guru).
 *
 * Disesuaikan dengan skema baru 2026_10_18 (cbt_test_sessions/cbt_results).
 * Transisi status memakai CbtExamService agar aturan pembekuan soal/peserta
 * dan penilaian tetap satu jalur dengan sisi guru.
 */
class CbtSessionController extends Controller
{
    public function __construct(private CbtExamService $exam) {}

    public function index(Request $request)
    {
        $query = CbtTestSession::with(['guru', 'bank'])->withCount('results');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('guru_id')) {
            $query->where('guru_id', $request->integer('guru_id'));
        }

        $sessions = $query->orderByDesc('id')->paginate(15)->withQueryString();
        $gurus = User::where('peran', 'guru')->orderBy('nama_lengkap')->get(['id', 'nama_lengkap']);

        return view('admin.cbt.sessions.index', compact('sessions', 'gurus'));
    }

    public function create()
    {
        $gurus = User::where('peran', 'guru')->orderBy('nama_lengkap')->get(['id', 'nama_lengkap']);
        $banks = \App\Models\CbtQuestionBank::with('guru')->orderBy('title')->get();

        return view('admin.cbt.sessions.create', compact('gurus', 'banks'));
    }

    public function store(CbtSessionStoreRequest $request)
    {
        $validated = $request->validated();

        $session = DB::transaction(function () use ($validated) {
            \App\Models\CbtQuestionBank::whereKey($validated['bank_id'])->lockForUpdate()->firstOrFail();

            return CbtTestSession::create($validated + ['status' => 'draft']);
        });

        if (config('simantap.exam_engine_cbt', true) && $session->bank) {
            $mapper = app(LegacyExamMapper::class);
            $mapper->sesiCbt($mapper->bankCbt($session->bank), $session);
        }

        return redirect()->route('admin.cbt.sessions.show', $session)
            ->with('success', 'Draf sesi dibuat. Publikasikan untuk membekukan soal & peserta.');
    }

    public function show(CbtTestSession $session)
    {
        $session->load(['guru', 'bank']);
        $results = $session->results()->with('siswa')->withCount('violations')->orderBy('id')->paginate(50);

        return view('admin.cbt.sessions.show', compact('session', 'results'));
    }

    public function updateStatus(CbtSessionStatusRequest $request, CbtTestSession $session)
    {
        $validated = $request->validated();

        match ($validated['status']) {
            'published' => $this->exam->publish($session),
            'running' => $this->exam->open($session),
            'completed' => $this->exam->complete($session),
        };

        return redirect()->route('admin.cbt.sessions.show', $session)
            ->with('success', "Status sesi menjadi {$validated['status']}.");
    }

    public function forceComplete(CbtTestSession $session)
    {
        $this->exam->complete($session);

        return redirect()->route('admin.cbt.sessions.show', $session)
            ->with('success', 'Sesi ditutup paksa; jawaban tersimpan dinilai.');
    }

    public function destroy(CbtTestSession $session)
    {
        DB::transaction(function () use ($session) {
            $locked = CbtTestSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            // FK hasil->sesi restrictOnDelete: hapus anak (violations, results) dulu.
            $locked->results()->each(function (CbtResult $result) {
                $result->violations()->delete();
                $result->delete();
            });
            $locked->delete();
        });

        if (config('simantap.exam_engine_cbt', true)) {
            app(LegacyExamMapper::class)->lupakanSesiCbt($session->id);
        }

        return redirect()->route('admin.cbt.sessions.index')->with('success', 'Sesi beserta hasilnya dihapus.');
    }

    public function analisisButir(CbtTestSession $session, \App\Services\Exam\ExamItemAnalysisService $analysis)
    {
        $session->load(['guru', 'bank']);
        $hasil = $analysis->untukSesiCbt($session);

        return view('admin.cbt.sessions.analisis', compact('session', 'hasil'));
    }

    public function exportResults(CbtTestSession $session, string $format)
    {
        abort_unless($format === 'csv', 404, 'Format ekspor hanya mendukung csv.');

        $session->load('guru', 'bank');
        $results = $session->results()->with('siswa')->withCount('violations')->orderBy('id')->get();

        $namaFile = 'cbt-sesi-'.$session->id.'-hasil.csv';
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Sesi', 'Peserta', 'NIS', 'Mulai', 'Selesai', 'Benar', 'Skor', 'Pelanggaran']);
        foreach ($results as $r) {
            fputcsv($handle, [
                $session->title,
                $r->siswa?->nama_peserta_didik ?? 'Siswa dihapus',
                $r->siswa?->nis ?? '-',
                $r->started_at?->format('Y-m-d H:i:s') ?? '-',
                $r->submitted_at?->format('Y-m-d H:i:s') ?? 'belum',
                $r->correct_count,
                $r->score ?? '-',
                $r->violations_count,
            ]);
        }
        rewind($handle);

        return response()->streamDownload(function () use ($handle) {
            fpassthru($handle);
            fclose($handle);
        }, $namaFile, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Fase 3, langkah 5b: ekspor CSV yang dibaca **hanya** dari engine terpadu
     * `exam_*`. Kolom & nama berkas dibuat identik dengan `exportResults` agar
     * dapat dibandingkan langsung (diuji `ExamReportParityTest`), sehingga
     * langkah 6 (hapus tabel lama) punya pembaca pengganti yang terbukti.
     */
    public function exportResultsEngine(CbtTestSession $session, string $format, \App\Services\Exam\ExamReportService $report)
    {
        abort_unless($format === 'csv', 404, 'Format ekspor hanya mendukung csv.');

        $rows = $report->hasilSesiCbt($session);

        $namaFile = 'cbt-sesi-'.$session->id.'-hasil.csv';
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Sesi', 'Peserta', 'NIS', 'Mulai', 'Selesai', 'Benar', 'Skor', 'Pelanggaran']);
        foreach ($rows as $r) {
            fputcsv($handle, [
                $session->title,
                $r['peserta'],
                $r['nis'],
                $r['mulai']?->format('Y-m-d H:i:s') ?? '-',
                $r['selesai']?->format('Y-m-d H:i:s') ?? 'belum',
                $r['benar'],
                $r['skor'] ?? '-',
                $r['pelanggaran'],
            ]);
        }
        rewind($handle);

        return response()->streamDownload(function () use ($handle) {
            fpassthru($handle);
            fclose($handle);
        }, $namaFile, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
