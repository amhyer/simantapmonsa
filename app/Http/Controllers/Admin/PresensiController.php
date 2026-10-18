<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PresensiLokasi;
use App\Models\PresensiPegawai;
use App\Models\User;
use App\Services\AktivitasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PresensiController extends Controller
{
    private const PERAN_PEGAWAI = ['guru', 'kepsek', 'tendik'];

    public function index(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => 'nullable|date',
        ]);
        $tanggal = $validated['tanggal'] ?? now()->toDateString();

        $pegawai = User::whereIn('peran', self::PERAN_PEGAWAI)
            ->where('aktif', true)
            ->orderBy('peran')
            ->orderBy('nama_lengkap')
            ->get();

        $catatan = PresensiPegawai::with(['lokasiMasuk', 'lokasiPulang', 'editor'])
            ->whereDate('tanggal', $tanggal)
            ->get()
            ->keyBy('user_id');

        $lokasi = PresensiLokasi::with('pembuat')->orderByDesc('aktif')->orderBy('nama')->get();

        return view('admin.presensi.index', compact('tanggal', 'pegawai', 'catatan', 'lokasi'));
    }

    public function storeLocation(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:100',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_meter' => 'required|integer|min:20|max:5000',
            'akurasi_maksimal_meter' => 'required|integer|min:10|max:1000',
            'aktif' => 'nullable|boolean',
        ]);
        $validated['aktif'] = $request->boolean('aktif');
        $validated['dibuat_oleh'] = auth()->id();

        $location = PresensiLokasi::create($validated);
        AktivitasService::log(
            'kehadiran',
            'Tambah area presensi pegawai',
            'Area presensi "' . $location->nama . '" dibuat.',
            'presensi_lokasi',
            $location->id,
            null,
            ['nama' => $location->nama, 'radius_meter' => $location->radius_meter, 'aktif' => $location->aktif],
        );

        return redirect()->route('admin.presensi.index')
            ->with('success', 'Area presensi berhasil ditambahkan.');
    }

    public function updateLocation(Request $request, PresensiLokasi $lokasi)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:100',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_meter' => 'required|integer|min:20|max:5000',
            'akurasi_maksimal_meter' => 'required|integer|min:10|max:1000',
            'aktif' => 'nullable|boolean',
        ]);
        $validated['aktif'] = $request->boolean('aktif');
        $before = $lokasi->only(['nama', 'latitude', 'longitude', 'radius_meter', 'akurasi_maksimal_meter', 'aktif']);
        $lokasi->update($validated);

        AktivitasService::log(
            'kehadiran',
            'Ubah area presensi pegawai',
            'Area presensi "' . $lokasi->nama . '" diperbarui.',
            'presensi_lokasi',
            $lokasi->id,
            $before,
            $lokasi->only(['nama', 'latitude', 'longitude', 'radius_meter', 'akurasi_maksimal_meter', 'aktif']),
        );

        return redirect()->route('admin.presensi.index')
            ->with('success', 'Area presensi berhasil diperbarui.');
    }

    public function destroyLocation(PresensiLokasi $lokasi)
    {
        $name = $lokasi->nama;
        $old = $lokasi->only(['nama', 'latitude', 'longitude', 'radius_meter', 'aktif']);
        $lokasi->delete();

        AktivitasService::log(
            'kehadiran',
            'Hapus area presensi pegawai',
            'Area presensi "' . $name . '" dihapus.',
            'presensi_lokasi',
            null,
            $old,
            null,
        );

        return redirect()->route('admin.presensi.index')
            ->with('success', 'Area presensi berhasil dihapus.');
    }

    public function koreksi(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'tanggal' => 'required|date',
            'status' => 'required|in:hadir,terlambat,izin,sakit,alpa',
            'jam_masuk' => 'nullable|date_format:H:i',
            'jam_pulang' => 'nullable|date_format:H:i',
            'alasan_koreksi' => 'required|string|max:500',
        ]);

        $user = User::whereKey($validated['user_id'])
            ->whereIn('peran', self::PERAN_PEGAWAI)
            ->firstOrFail();

        if ($validated['jam_pulang'] && !$validated['jam_masuk']) {
            return back()->withInput()->with('error', 'Jam masuk harus diisi jika jam pulang dicatat.');
        }

        $record = DB::transaction(function () use ($validated, $user) {
            $record = PresensiPegawai::firstOrNew([
                'user_id' => $user->id,
                'tanggal' => $validated['tanggal'],
            ]);
            $before = $record->exists ? $record->only(['status', 'waktu_masuk', 'waktu_pulang']) : null;
            $record->status = $validated['status'];
            $record->waktu_masuk = $this->tanggalDanWaktu($validated['tanggal'], $validated['jam_masuk'] ?? null);
            $record->waktu_pulang = $this->tanggalDanWaktu($validated['tanggal'], $validated['jam_pulang'] ?? null);
            $record->metode = 'admin';
            $record->diperbarui_oleh = auth()->id();
            $record->alasan_koreksi = $validated['alasan_koreksi'];
            $record->save();

            AktivitasService::log(
                'kehadiran',
                'Koreksi presensi pegawai: ' . $user->nama_lengkap,
                $validated['alasan_koreksi'],
                'presensi_pegawai',
                $record->id,
                $before,
                $record->only(['status', 'waktu_masuk', 'waktu_pulang', 'alasan_koreksi']),
            );

            return $record;
        });

        return redirect()->route('admin.presensi.index', ['tanggal' => $validated['tanggal']])
            ->with('success', 'Presensi ' . $user->nama_lengkap . ' berhasil dikoreksi.');
    }

    private function tanggalDanWaktu(string $tanggal, ?string $waktu): mixed
    {
        return $waktu ? Carbon::parse($tanggal . ' ' . $waktu, config('app.timezone')) : null;
    }
}
