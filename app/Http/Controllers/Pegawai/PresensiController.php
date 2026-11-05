<?php

namespace App\Http\Controllers\Pegawai;

use App\Http\Controllers\Controller;
use App\Models\PresensiLokasi;
use App\Models\PresensiPegawai;
use App\Models\SekolahSettings;
use App\Models\Siswa;
use App\Models\User;
use App\Services\AktivitasService;
use App\Services\Presensi\GeofenceService;
use App\Services\Presensi\IdentityCardService;
use App\Services\Presensi\StudentQrHasher;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PresensiController extends Controller
{
    private const PERAN_PEGAWAI = ['guru', 'kepsek', 'tendik'];

    public function index(IdentityCardService $cards)
    {
        $user = auth()->user();
        abort_unless($user && in_array($user->peran, self::PERAN_PEGAWAI, true), 403);

        $record = PresensiPegawai::with(['lokasiMasuk', 'lokasiPulang'])
            ->where('user_id', $user->id)
            ->whereDate('tanggal', now()->toDateString())
            ->first();

        $lokasiAktif = PresensiLokasi::where('aktif', true)
            ->orderBy('nama')
            ->get(['id', 'nama', 'radius_meter', 'akurasi_maksimal_meter']);

        $sekolah = SekolahSettings::query()->first();
        $kartuSaya = $cards->buildPegawaiCardViewModel($user, $sekolah, null, true);

        $riwayat = PresensiPegawai::with(['lokasiMasuk', 'lokasiPulang'])
            ->where('user_id', $user->id)
            ->orderByDesc('tanggal')
            ->limit(10)
            ->get();

        return view('pegawai.presensi.index', compact('record', 'lokasiAktif', 'kartuSaya', 'riwayat'));
    }

    public function kartuSaya(IdentityCardService $cards)
    {
        $user = auth()->user();
        abort_unless($user && in_array($user->peran, self::PERAN_PEGAWAI, true), 403);

        $sekolah = SekolahSettings::query()->first();

        return view('presensi.kartu-cetak', [
            'judul' => 'Kartu Identitas Saya — ' . $user->nama_lengkap,
            'subjudul' => ucfirst((string) $user->peran),
            'cards' => [$cards->buildPegawaiCardViewModel($user, $sekolah, null, true)],
            'kembaliUrl' => route('presensi.pegawai.index'),
        ]);
    }

    public function scanMandiri(
        Request $request,
        StudentQrHasher $hasher,
        GeofenceService $geofence,
    ) {
        $user = auth()->user();
        abort_unless($user && in_array($user->peran, self::PERAN_PEGAWAI, true), 403);

        $validated = $request->validate([
            'qr_payload' => 'required|string|max:2048',
            'jenis' => 'required|in:masuk,pulang',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0|max:5000',
        ]);

        if (!filled($user->qr_token_hash)) {
            return response()->json([
                'success' => false,
                'message' => 'Kartu identitas Anda belum aktif. Muat ulang halaman atau minta admin menerbitkan kartu.',
            ], 422);
        }

        $digest = $hasher->hash($validated['qr_payload']);
        if (!hash_equals((string) $user->qr_token_hash, $digest)) {
            if (User::where('qr_token_hash', $digest)->where('id', '!=', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kartu yang dipindai adalah milik guru/pegawai lain. Anda wajib memindai kartu identitas milik sendiri.',
                ], 403);
            }

            if (Siswa::where('qr_token_hash', $digest)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ini adalah kartu identitas siswa. Gunakan menu Kehadiran Siswa pada jadwal mengajar Anda.',
                ], 422);
            }

            return response()->json([
                'success' => false,
                'message' => 'QR kartu identitas tidak dikenali atau sudah diganti dengan versi baru.',
            ], 404);
        }

        $zones = PresensiLokasi::where('aktif', true)->get();
        $wajibGps = (bool) config('presensi.wajib_gps_scan_qr_pegawai', false);
        $hasCoords = isset($validated['latitude'], $validated['longitude'])
            && $validated['latitude'] !== null
            && $validated['longitude'] !== null;

        if ($wajibGps && !$hasCoords) {
            return response()->json([
                'success' => false,
                'message' => 'Kebijakan sekolah mewajibkan izin lokasi GPS aktif saat memindai kartu identitas.',
            ], 422);
        }

        $location = null;
        if ($hasCoords && $zones->isNotEmpty()) {
            $latitude = (float) $validated['latitude'];
            $longitude = (float) $validated['longitude'];
            $accuracy = (float) ($validated['accuracy'] ?? 50);
            $location = $geofence->lokasiCocok($latitude, $longitude, $accuracy);

            if (!$location) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lokasi perangkat berada di luar area sekolah yang diizinkan saat scan kartu identitas.',
                ], 403);
            }
        }

        $jenis = $validated['jenis'];
        $metode = $location ? 'qr+gps' : 'qr';
        $coords = $hasCoords ? [
            'latitude' => (float) $validated['latitude'],
            'longitude' => (float) $validated['longitude'],
            'accuracy' => (float) ($validated['accuracy'] ?? 0),
        ] : null;

        return $this->simpanPresensi($jenis, $metode, $location, $coords);
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

        return $this->simpanPresensi($jenis, 'gps', $location, [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy' => $accuracy,
        ]);
    }

    private function simpanPresensi(
        string $jenis,
        string $metode,
        ?PresensiLokasi $location,
        ?array $coords,
    ) {
        $today = now()->toDateString();

        try {
            $record = DB::transaction(function () use ($today, $jenis, $metode, $location, $coords) {
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
                    if ($coords) {
                        $record->masuk_latitude = $coords['latitude'];
                        $record->masuk_longitude = $coords['longitude'];
                        $record->masuk_akurasi_meter = $coords['accuracy'];
                    }
                    if ($location) {
                        $record->masuk_lokasi_id = $location->id;
                    }
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
                    if ($coords) {
                        $record->pulang_latitude = $coords['latitude'];
                        $record->pulang_longitude = $coords['longitude'];
                        $record->pulang_akurasi_meter = $coords['accuracy'];
                    }
                    if ($location) {
                        $record->pulang_lokasi_id = $location->id;
                    }
                }

                $record->metode = $metode;
                $record->save();

                $sumberLabel = str_starts_with($metode, 'qr')
                    ? 'scan mandiri kartu identitas QR' . ($location ? ' di ' . $location->nama : '')
                    : 'geofence lokasi ' . ($location?->nama ?? '-');

                AktivitasService::log(
                    'kehadiran',
                    ucfirst($jenis) . ' pegawai: ' . auth()->user()->nama_lengkap,
                    'Presensi ' . $jenis . ' tercatat melalui ' . $sumberLabel . '.',
                    'presensi_pegawai',
                    $record->id,
                    null,
                    ['jenis' => $jenis, 'metode' => $metode, 'lokasi' => $location?->nama],
                );

                return $record->fresh(['lokasiMasuk', 'lokasiPulang']);
            });
        } catch (QueryException $exception) {
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

        $labelMetode = str_starts_with($metode, 'qr') ? ' melalui scan kartu identitas' : '';

        return response()->json([
            'success' => true,
            'message' => ($jenis === 'masuk' ? 'Jam masuk berhasil dicatat' : 'Jam pulang berhasil dicatat') . $labelMetode . '.',
            'record' => [
                'status' => $record->status,
                'metode' => $record->metode,
                'waktu_masuk' => $record->waktu_masuk?->format('H:i:s'),
                'waktu_pulang' => $record->waktu_pulang?->format('H:i:s'),
                'lokasi_masuk' => $record->lokasiMasuk?->nama ?? (str_starts_with((string) $record->metode, 'qr') ? 'Scan Kartu Identitas' : null),
                'lokasi_pulang' => $record->lokasiPulang?->nama ?? ($record->waktu_pulang && str_starts_with((string) $record->metode, 'qr') ? 'Scan Kartu Identitas' : null),
            ],
        ]);
    }
}
