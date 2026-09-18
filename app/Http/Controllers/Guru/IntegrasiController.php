<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\PengaturanGuru;
use App\Models\SekolahSettings;
use App\Services\GoogleSheetsService;
use Illuminate\Http\Request;

class IntegrasiController extends Controller
{
    public function index()
    {
        $pengaturan = PengaturanGuru::where('guru_id', auth()->id())->first();
        $sistem = optional($pengaturan)->sistem ?? [];
        $terhubung = !empty($sistem['google_sheet_url']);
        $terakhirSinkron = isset($sistem['terhubung_pada']) ? \Carbon\Carbon::parse($sistem['terhubung_pada']) : null;
        $spreadsheetName = $sistem['spreadsheet_name'] ?? null;
        $spreadsheetUrl = $sistem['google_sheet_url'] ?? null;
        // Extract spreadsheet ID from Google Sheets URL
        $spreadsheetId = null;
        if ($spreadsheetUrl && preg_match('#/d/([a-zA-Z0-9-_]+)#', $spreadsheetUrl, $matches)) {
            $spreadsheetId = $matches[1];
        }
        $sinkronNilai = $sistem['sinkron_nilai'] ?? true;
        $sinkronKehadiran = $sistem['sinkron_kehadiran'] ?? true;
        $sinkronKebiasaan = $sistem['sinkron_kebiasaan'] ?? false;
        $sinkronDimensi = $sistem['sinkron_dimensi'] ?? false;
        $riwayatSinkron = $sistem['riwayat_sinkron'] ?? [];

        return view('guru.integrasi.index', compact(
            'pengaturan', 'terhubung', 'terakhirSinkron', 'spreadsheetName',
            'spreadsheetId', 'spreadsheetUrl', 'sinkronNilai', 'sinkronKehadiran',
            'sinkronKebiasaan', 'sinkronDimensi', 'riwayatSinkron'
        ));
    }

    public function connect(Request $request)
    {
        $validated = $request->validate([
            'google_sheet_url' => 'required|url',
        ]);

        $guruId = auth()->id();
        $pengaturan = PengaturanGuru::firstOrCreate(
            ['guru_id' => $guruId],
            ['nama_guru' => auth()->user()->nama_lengkap]
        );

        $sistem = $pengaturan->sistem ?? [];
        $sistem['google_sheet_url'] = $validated['google_sheet_url'];
        $sistem['terhubung_pada'] = now()->toDateTimeString();

        $pengaturan->update([
            'sistem' => $sistem,
            'pembaruan' => now(),
        ]);

        return back()->with('success', 'Google Sheet berhasil terhubung.');
    }

    public function disconnect()
    {
        $guruId = auth()->id();
        $pengaturan = PengaturanGuru::where('guru_id', $guruId)->first();

        if ($pengaturan) {
            $sistem = $pengaturan->sistem ?? [];
            unset($sistem['google_sheet_url']);
            unset($sistem['terhubung_pada']);

            $pengaturan->update([
                'sistem' => $sistem,
                'pembaruan' => now(),
            ]);
        }

        return back()->with('success', 'Google Sheet sudah diputuskan.');
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'sinkron_nilai' => 'nullable|boolean',
            'sinkron_kehadiran' => 'nullable|boolean',
            'sinkron_kebiasaan' => 'nullable|boolean',
            'sinkron_dimensi' => 'nullable|boolean',
        ]);

        $guruId = auth()->id();
        $pengaturan = PengaturanGuru::firstOrCreate(
            ['guru_id' => $guruId],
            ['nama_guru' => auth()->user()->nama_lengkap]
        );

        $sistem = $pengaturan->sistem ?? [];
        $sistem['sinkron_nilai'] = $request->boolean('sinkron_nilai');
        $sistem['sinkron_kehadiran'] = $request->boolean('sinkron_kehadiran');
        $sistem['sinkron_kebiasaan'] = $request->boolean('sinkron_kebiasaan');
        $sistem['sinkron_dimensi'] = $request->boolean('sinkron_dimensi');

        $pengaturan->update([
            'sistem' => $sistem,
            'pembaruan' => now(),
        ]);

        return back()->with('success', 'Pengaturan sinkronisasi berhasil disimpan.');
    }

    public function fullSync()
    {
        $settings = SekolahSettings::first();
        $googleSheet = $settings->pengaturan['google_sheet'] ?? null;

        if (!$googleSheet || empty($googleSheet['terhubung']) || empty($googleSheet['spreadsheet_id'])) {
            return back()->with('error', 'Google Sheets belum terhubung. Hubungkan melalui Admin > Sheet.');
        }

        $spreadsheetId = $googleSheet['spreadsheet_id'];
        $service = app(GoogleSheetsService::class);

        try {
            $types = ['nilai', 'kehadiran', 'kebiasaan', 'dimensi'];
            $models = [
                'nilai'     => \App\Models\Nilai::with('siswa')->get(),
                'kehadiran' => \App\Models\Kehadiran::with('siswa', 'guru')->get(),
                'kebiasaan' => \App\Models\Kebiasaan::with('siswa')->get(),
                'dimensi'   => \App\Models\Dimensi::with('siswa')->get(),
            ];

            foreach ($types as $type) {
                $tabName = config("google.sheet_tabs.{$type}");
                $headers = config("google.headers.{$type}");
                $data = $models[$type];

                $service->getOrCreateTab($spreadsheetId, $tabName);
                $service->clearSheet($spreadsheetId, $tabName);
                $service->ensureHeaders($spreadsheetId, $tabName, $headers);

                $rows = $data->map(fn($model) => $service->buildRow($type, $model))->toArray();

                if (!empty($rows)) {
                    $chunks = array_chunk($rows, 100);
                    foreach ($chunks as $chunk) {
                        $service->syncBatch($spreadsheetId, $tabName, $chunk);
                    }
                }
            }

            // Log sync history
            $pengaturan = PengaturanGuru::where('guru_id', auth()->id())->first();
            if ($pengaturan) {
                $sistem = $pengaturan->sistem ?? [];
                $riwayat = $sistem['riwayat_sinkron'] ?? [];
                array_unshift($riwayat, [
                    'waktu' => now()->toDateTimeString(),
                    'tipe' => 'full_sync',
                    'status' => 'berhasil',
                    'jumlah' => $models->sum->count(),
                ]);
                $sistem['riwayat_sinkron'] = array_slice($riwayat, 0, 20);
                $pengaturan->update(['sistem' => $sistem, 'pembaruan' => now()]);
            }

            return back()->with('success', 'Full sync berhasil! Semua data telah terkirim ke Google Sheets.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal melakukan sync: ' . $e->getMessage());
        }
    }
}
