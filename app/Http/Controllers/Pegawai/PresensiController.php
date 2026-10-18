<?php

namespace App\Http\Controllers\Pegawai;

use App\Http\Controllers\Controller;
use App\Models\PresensiLokasi;
use App\Models\PresensiPegawai;
use App\Services\AktivitasService;
use App\Services\Presensi\GeofenceService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PresensiController extends Controller
{
    private const PERAN_PEGAWAI = ['guru', 'kepsek', 'tendik'];

    public function index()
    {
        $record = PresensiPegawai::with(['lokasiMasuk', 'lokasiPulang'])
            ->where('user_id', auth()->id())
            ->whereDate('tanggal', now()->toDateString())
            ->first();
        $lokasiAktif = PresensiLokasi::where('aktif', true)
            ->orderBy('nama')
            ->get(['id', 'nama', 'radius_meter', 'akurasi_maksimal_meter']);

        return view('pegawai.presensi.index', compact('record', 'lokasiAktif'));
    }

    public function masuk(Request $request, GeofenceService $geofence)
    {
        return $this->catatWaktu($request, $geofence, 'masuk');
    }

    public function pulang(Request $request, GeofenceService $geofence)
    {
        return $this->catatWaktu($request, $geofence, 'pulang');
    }

    private function catatWaktu(Request $request, GeofenceService $geofence, string $jenis)
    {
        abort_unless(in_array(auth()->user()->peran, self::PERAN_PEGAWAI, true), 403);

        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'required|numeric|min:0|max:5000',
        ]);

        $zones = PresensiLokasi::where('aktif', true)->get();
        if ($zones->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Admin belum mengatur titik lokasi absensi yang aktif.',
            ], 422);
        }

        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];
        $accuracy = (float) $validated['accuracy'];
        $location = $geofence->lokasiCocok($latitude, $longitude, $accuracy);

        if (!$location) {
            $insideAnyRadius = $zones->contains(fn (PresensiLokasi $zone) =>
                $geofence->jarakMeter($latitude, $longitude, $zone->latitude, $zone->longitude) <= $zone->radius_meter
            );

            if ($insideAnyRadius) {
                return response()->json([
                    'success' => false,
                    'message' => 'GPS belum cukup akurat untuk area ini. Coba nyalakan lokasi dengan akurasi tinggi dan ulangi.',
                ], 422);
            }

            return response()->json([
                'success' => false,
                'message' => 'Lokasi perangkat berada di luar area absensi sekolah yang diizinkan.',
            ], 403);
        }

        $today = now()->toDateString();
        try {
            $record = DB::transaction(function () use ($validated, $today, $location, $jenis) {
                $record = PresensiPegawai::where('user_id', auth()->id())
                    ->whereDate('tanggal', $today)
                    ->lockForUpdate()
                    ->first();

                if ($jenis === 'masuk') {
                    if ($record?->waktu_masuk) {
                        abort(409, 'Jam masuk hari ini sudah tercatat.');
                    }

                    $record ??= new PresensiPegawai([
                        'user_id' => auth()->id(),
                        'tanggal' => $today,
                        'status' => 'hadir',
                    ]);
                    $record->waktu_masuk = now();
                    $record->masuk_latitude = $validated['latitude'];
                    $record->masuk_longitude = $validated['longitude'];
                    $record->masuk_akurasi_meter = $validated['accuracy'];
                    $record->masuk_lokasi_id = $location->id;
                    if (!in_array($record->status, ['izin', 'sakit', 'terlambat'], true)) {
                        $record->status = 'hadir';
                    }
                } else {
                    if (!$record || !$record->waktu_masuk) {
                        abort(422, 'Catat jam masuk terlebih dahulu sebelum jam pulang.');
                    }
                    if ($record->waktu_pulang) {
                        abort(409, 'Jam pulang hari ini sudah tercatat.');
                    }

                    $record->waktu_pulang = now();
                    $record->pulang_latitude = $validated['latitude'];
                    $record->pulang_longitude = $validated['longitude'];
                    $record->pulang_akurasi_meter = $validated['accuracy'];
                    $record->pulang_lokasi_id = $location->id;
                }

                $record->metode = 'gps';
                $record->save();

                AktivitasService::log(
                    'kehadiran',
                    ucfirst($jenis) . ' pegawai: ' . auth()->user()->nama_lengkap,
                    'Presensi ' . $jenis . ' tercatat melalui geofence lokasi ' . $location->nama . '.',
                    'presensi_pegawai',
                    $record->id,
                    null,
                    ['jenis' => $jenis, 'lokasi' => $location->nama],
                );

                return $record->fresh(['lokasiMasuk', 'lokasiPulang']);
            });
        } catch (QueryException $exception) {
            // Constraint unik user/tanggal tetap melindungi dua request masuk bersamaan.
            $record = PresensiPegawai::where('user_id', auth()->id())
                ->whereDate('tanggal', $today)
                ->first();
            if ($record && (($jenis === 'masuk' && $record->waktu_masuk) || ($jenis === 'pulang' && $record->waktu_pulang))) {
                return response()->json([
                    'success' => false,
                    'message' => $jenis === 'masuk'
                        ? 'Jam masuk hari ini sudah tercatat.'
                        : 'Jam pulang hari ini sudah tercatat.',
                ], 409);
            }
            throw $exception;
        }

        return response()->json([
            'success' => true,
            'message' => $jenis === 'masuk' ? 'Jam masuk berhasil dicatat.' : 'Jam pulang berhasil dicatat.',
            'record' => [
                'status' => $record->status,
                'waktu_masuk' => $record->waktu_masuk?->format('H:i:s'),
                'waktu_pulang' => $record->waktu_pulang?->format('H:i:s'),
                'lokasi_masuk' => $record->lokasiMasuk?->nama,
                'lokasi_pulang' => $record->lokasiPulang?->nama,
            ],
        ]);
    }
}
