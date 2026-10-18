<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\JadwalPelajaran;
use App\Models\Kehadiran;
use App\Models\KehadiranMapel;
use App\Models\Siswa;
use App\Services\Presensi\MapelAttendancePolicy;
use App\Services\Presensi\StudentQrHasher;
use App\Traits\SyncableToSheet;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KehadiranController extends Controller
{
    use SyncableToSheet;

    public function index(MapelAttendancePolicy $policy)
    {
        $hariIni = now()->locale('id')->translatedFormat('l');
        $jadwalHariIni = JadwalPelajaran::with('rombel')
            ->where('guru_id', auth()->id())
            ->where('hari', $hariIni)
            ->orderBy('jam_mulai')
            ->get();

        foreach ($jadwalHariIni as $jadwal) {
            $jadwal->presensi_mapel_terpisah = $policy->requiresSeparateAttendance($jadwal->mata_pelajaran);
        }

        $kehadiran = Kehadiran::with('siswa')
            ->where('guru_id', auth()->id())
            ->latest('tanggal')
            ->limit(100)
            ->get();
        return view('guru.kehadiran.index', compact('kehadiran', 'jadwalHariIni'));
    }

    public function jadwal(JadwalPelajaran $jadwal, MapelAttendancePolicy $policy)
    {
        $this->pastikanJadwalMilikGuru($jadwal);

        $students = $this->siswaUntukJadwal($jadwal, $policy);
        $ids = $students->pluck('id');
        $today = now()->toDateString();
        $kehadiranHarian = Kehadiran::whereDate('tanggal', $today)
            ->whereIn('siswa_id', $ids)
            ->get()
            ->keyBy('siswa_id');

        $presensiMapel = collect();
        $presensiMapelTerpisah = $policy->requiresSeparateAttendance($jadwal->mata_pelajaran);
        if ($presensiMapelTerpisah) {
            $presensiMapel = KehadiranMapel::where('jadwal_pelajaran_id', $jadwal->id)
                ->whereDate('tanggal', $today)
                ->whereIn('siswa_id', $ids)
                ->get()
                ->keyBy('siswa_id');
        }

        return view('guru.kehadiran.jadwal', [
            'jadwal' => $jadwal->load('rombel'),
            'students' => $students,
            'kehadiranHarian' => $kehadiranHarian,
            'presensiMapel' => $presensiMapel,
            'presensiMapelTerpisah' => $presensiMapelTerpisah,
            'tanggal' => $today,
            'canRecordAttendance' => $this->waktuJadwalTerbuka($jadwal),
        ]);
    }

    public function scan(
        Request $request,
        JadwalPelajaran $jadwal,
        StudentQrHasher $hasher,
        MapelAttendancePolicy $policy,
    ) {
        $this->pastikanJadwalMilikGuru($jadwal);
        $this->pastikanWaktuJadwalTerbuka($jadwal);
        $validated = $request->validate([
            'qr_payload' => 'required|string|max:2048',
        ]);

        $student = Siswa::where('qr_token_hash', $hasher->hash($validated['qr_payload']))
            ->where('aktif', true)
            ->first();

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'QR belum terhubung ke data siswa. Minta admin mendaftarkan kartu tersebut.',
            ], 404);
        }

        if (!$this->siswaTerdaftarPadaJadwal($student, $jadwal, $policy)) {
            return response()->json([
                'success' => false,
                'message' => 'Kartu ini bukan siswa yang mengikuti kelas atau mata pelajaran pada jadwal ini.',
            ], 403);
        }

        $now = now();
        $today = $now->toDateString();
        $mapelTerpisah = $policy->requiresSeparateAttendance($jadwal->mata_pelajaran);

        $result = DB::transaction(function () use ($student, $jadwal, $now, $today, $mapelTerpisah): array {
            $daily = Kehadiran::withTrashed()->where('siswa_id', $student->id)
                ->whereDate('tanggal', $today)
                ->lockForUpdate()
                ->first();
            if ($daily?->trashed()) {
                $daily->restore();
            }
            $dailyAlreadyPresent = $daily?->status === 'H'
                && $daily?->waktu_masuk !== null
                && $daily?->metode === 'qr';

            if (!$daily) {
                $daily = Kehadiran::create([
                    'uuid' => (string) Str::uuid(),
                    'guru_id' => auth()->id(),
                    'siswa_id' => $student->id,
                    'tanggal' => $today,
                    'status' => 'H',
                    'waktu_masuk' => $now,
                    'jadwal_pelajaran_id' => $jadwal->id,
                    'dicatat_oleh' => auth()->id(),
                    'metode' => 'qr',
                ]);
            } elseif (!$dailyAlreadyPresent) {
                $daily->update([
                    'guru_id' => auth()->id(),
                    'status' => 'H',
                    'waktu_masuk' => $now,
                    'jadwal_pelajaran_id' => $daily->jadwal_pelajaran_id ?: $jadwal->id,
                    'dicatat_oleh' => auth()->id(),
                    'metode' => 'qr',
                    'keterangan' => $daily->status === 'A'
                        ? 'Siswa hadir setelah sebelumnya ditandai belum hadir.'
                        : $daily->keterangan,
                ]);
            }

            $mapelAlreadyPresent = false;
            $mapelAttendance = null;
            if ($mapelTerpisah) {
                $mapelAttendance = KehadiranMapel::where('jadwal_pelajaran_id', $jadwal->id)
                    ->whereDate('tanggal', $today)
                    ->where('siswa_id', $student->id)
                    ->lockForUpdate()
                    ->first();
                $mapelAlreadyPresent = $mapelAttendance?->status === 'H'
                    && $mapelAttendance?->waktu_hadir !== null
                    && $mapelAttendance?->metode === 'qr';

                if (!$mapelAttendance) {
                    $mapelAttendance = KehadiranMapel::create([
                        'jadwal_pelajaran_id' => $jadwal->id,
                        'siswa_id' => $student->id,
                        'guru_id' => auth()->id(),
                        'tanggal' => $today,
                        'mata_pelajaran' => $jadwal->mata_pelajaran,
                        'kelas' => $jadwal->kelas,
                        'status' => 'H',
                        'waktu_hadir' => $now,
                        'metode' => 'qr',
                    ]);
                } elseif (!$mapelAlreadyPresent) {
                    $mapelAttendance->update([
                        'guru_id' => auth()->id(),
                        'status' => 'H',
                        'waktu_hadir' => $now,
                        'metode' => 'qr',
                        'keterangan' => $mapelAttendance->status === 'A'
                            ? 'Siswa hadir setelah sebelumnya ditandai belum hadir.'
                            : $mapelAttendance->keterangan,
                    ]);
                }
            }

            return [
                'daily' => $daily->fresh(),
                'mapel' => $mapelAttendance?->fresh(),
                'daily_already_present' => $dailyAlreadyPresent,
                'mapel_already_present' => $mapelAlreadyPresent,
            ];
        });

        // Sinkronkan hanya ringkasan harian yang lama; catatan mapel tetap tersimpan
        // di tabel terpisah agar tidak menabrak batas satu absensi harian per siswa.
        try {
            $this->syncToGoogleSheet('kehadiran', $result['daily']->fresh('siswa', 'guru'));
        } catch (\Throwable) {
            // Kegagalan Google Sheets tidak boleh membatalkan scan yang sudah tercatat.
        }

        $message = $result['mapel']
            ? ($result['mapel_already_present'] ? 'Siswa sudah tercatat hadir pada mapel ini.' : 'Kehadiran harian dan mapel berhasil dicatat.')
            : ($result['daily_already_present'] ? 'Kehadiran harian siswa sudah tercatat.' : 'Kehadiran harian berhasil dicatat.');

        return response()->json([
            'success' => true,
            'message' => $message,
            'student' => [
                'id' => $student->id,
                'nama' => $student->nama_peserta_didik,
                'nis' => $student->nis,
                'kelas' => $student->kelas,
                'foto' => $student->foto ? asset('storage/' . ltrim($student->foto, '/')) : null,
            ],
            'daily' => [
                'status' => $result['daily']->status,
                'waktu_masuk' => $result['daily']->waktu_masuk?->format('H:i:s'),
                'already_present' => $result['daily_already_present'],
            ],
            'mapel' => $result['mapel'] ? [
                'status' => $result['mapel']->status,
                'waktu_hadir' => $result['mapel']->waktu_hadir?->format('H:i:s'),
                'already_present' => $result['mapel_already_present'],
            ] : null,
        ]);
    }

    public function setStatus(Request $request, JadwalPelajaran $jadwal, Siswa $siswa, MapelAttendancePolicy $policy)
    {
        $this->pastikanJadwalMilikGuru($jadwal);
        $this->pastikanWaktuJadwalTerbuka($jadwal);
        abort_unless($this->siswaTerdaftarPadaJadwal($siswa, $jadwal, $policy), 403);

        $validated = $request->validate([
            'cakupan' => 'required|in:harian,mapel',
            'status' => 'required|in:H,S,I,A',
            'keterangan' => 'nullable|string|max:255',
        ]);

        if ($validated['cakupan'] === 'mapel' && !$policy->requiresSeparateAttendance($jadwal->mata_pelajaran)) {
            return response()->json([
                'success' => false,
                'message' => 'Absensi terpisah belum diaktifkan untuk mata pelajaran ini.',
            ], 422);
        }

        $today = now()->toDateString();
        $status = $validated['status'];
        $time = $status === 'H' ? now() : null;

        if ($validated['cakupan'] === 'harian') {
            $record = Kehadiran::withTrashed()->firstOrNew([
                'siswa_id' => $siswa->id,
                'tanggal' => $today,
            ]);
            if ($record->trashed()) {
                $record->restore();
            }
            $record->fill([
                'guru_id' => auth()->id(),
                'status' => $status,
                'waktu_masuk' => $status === 'H' ? ($record->waktu_masuk ?? $time) : null,
                'jadwal_pelajaran_id' => $record->jadwal_pelajaran_id ?: $jadwal->id,
                'dicatat_oleh' => auth()->id(),
                'metode' => $status === 'H' && $record->metode === 'qr' && $record->waktu_masuk ? 'qr' : 'manual',
                'keterangan' => $validated['keterangan'] ?? null,
            ]);
            if (!$record->exists) {
                $record->uuid ??= (string) Str::uuid();
            }
            $record->save();
        } else {
            $record = KehadiranMapel::firstOrNew([
                'jadwal_pelajaran_id' => $jadwal->id,
                'tanggal' => $today,
                'siswa_id' => $siswa->id,
            ]);
            $record->fill([
                'guru_id' => auth()->id(),
                'mata_pelajaran' => $jadwal->mata_pelajaran,
                'kelas' => $jadwal->kelas,
                'status' => $status,
                'waktu_hadir' => $status === 'H' ? ($record->waktu_hadir ?? $time) : null,
                'metode' => $status === 'H' && $record->metode === 'qr' && $record->waktu_hadir ? 'qr' : 'manual',
                'keterangan' => $validated['keterangan'] ?? null,
            ]);
            if (!$record->exists) {
                $record->uuid ??= (string) Str::uuid();
            }
            $record->save();
        }

        $recordedTime = $validated['cakupan'] === 'harian'
            ? $record->waktu_masuk
            : $record->waktu_hadir;

        return response()->json([
            'success' => true,
            'message' => 'Status kehadiran ' . $siswa->nama_peserta_didik . ' berhasil diperbarui.',
            'siswa_id' => $siswa->id,
            'cakupan' => $validated['cakupan'],
            'status' => $status,
            'waktu' => $recordedTime?->format('H:i:s'),
        ]);
    }

    public function store(Request $request, MapelAttendancePolicy $policy)
    {
        $validated = $request->validate([
            'jadwal_pelajaran_id' => 'required|integer|exists:jadwal_pelajaran,id',
            'siswa_id' => 'required|integer|exists:siswa,id',
            'status' => 'required|in:H,S,I,A',
            'keterangan' => 'nullable|string|max:255',
        ]);

        $jadwal = JadwalPelajaran::findOrFail($validated['jadwal_pelajaran_id']);
        $this->pastikanJadwalMilikGuru($jadwal);
        $this->pastikanWaktuJadwalTerbuka($jadwal);
        $siswa = Siswa::findOrFail($validated['siswa_id']);
        abort_unless($this->siswaTerdaftarPadaJadwal($siswa, $jadwal, $policy), 403);

        $record = Kehadiran::withTrashed()->firstOrNew([
            'siswa_id' => $siswa->id,
            'tanggal' => now()->toDateString(),
        ]);
        if ($record->trashed()) {
            $record->restore();
        }
        $record->fill([
            'guru_id' => auth()->id(),
            'status' => $validated['status'],
            'waktu_masuk' => $validated['status'] === 'H' ? ($record->waktu_masuk ?? now()) : null,
            'jadwal_pelajaran_id' => $record->jadwal_pelajaran_id ?: $jadwal->id,
            'dicatat_oleh' => auth()->id(),
            'metode' => $validated['status'] === 'H' && $record->metode === 'qr' && $record->waktu_masuk ? 'qr' : 'manual',
            'keterangan' => $validated['keterangan'] ?? null,
        ]);
        $record->save();

        try {
            $this->syncToGoogleSheet('kehadiran', $record->fresh('siswa', 'guru'));
        } catch (\Throwable) {
            // Sinkronisasi eksternal tidak boleh menggagalkan pencatatan lokal.
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Kehadiran berhasil disimpan.',
                'status' => $record->status,
                'waktu_masuk' => $record->waktu_masuk?->format('H:i:s'),
            ]);
        }

        return back()->with('success', 'Kehadiran berhasil disimpan.');
    }

    public function destroy(Kehadiran $kehadiran)
    {
        if ($kehadiran->guru_id !== auth()->id()) abort(403);
        $kehadiran->delete();

        return back()->with('success', 'Kehadiran berhasil dihapus.');
    }

    private function pastikanJadwalMilikGuru(JadwalPelajaran $jadwal): void
    {
        abort_unless((int) $jadwal->guru_id === (int) auth()->id(), 403, 'Jadwal ini bukan tanggung jawab Anda.');
        $hariIni = now()->locale('id')->translatedFormat('l');
        abort_unless($jadwal->hari === $hariIni, 403, 'Absensi hanya dapat dicatat pada hari jadwal tersebut.');
    }

    private function pastikanWaktuJadwalTerbuka(JadwalPelajaran $jadwal): void
    {
        abort_unless(
            $this->waktuJadwalTerbuka($jadwal),
            403,
            'Pencatatan absensi hanya dibuka di sekitar waktu jadwal pelajaran.'
        );
    }

    private function waktuJadwalTerbuka(JadwalPelajaran $jadwal): bool
    {
        $now = now();
        try {
            $start = $now->copy()->startOfDay()->setTimeFromTimeString((string) $jadwal->jam_mulai);
            $end = $now->copy()->startOfDay()->setTimeFromTimeString((string) $jadwal->jam_selesai);
        } catch (\Throwable) {
            return false;
        }

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        $start->subMinutes(max(0, (int) config('presensi.menit_sebelum_jadwal', 15)));
        $end->addMinutes(max(0, (int) config('presensi.menit_setelah_jadwal', 60)));

        return $now->betweenIncluded($start, $end);
    }

    private function siswaUntukJadwal(JadwalPelajaran $jadwal, MapelAttendancePolicy $policy): EloquentCollection
    {
        $query = Siswa::query()->where('aktif', true)->orderBy('nama_peserta_didik');

        if ($jadwal->rombel_id) {
            $query->where('rombel_id', $jadwal->rombel_id);
        } elseif (filled($jadwal->kelas)) {
            $query->where('kelas', $jadwal->kelas);
        } else {
            abort(422, 'Jadwal belum terhubung ke rombel atau nama kelas. Admin perlu melengkapi data jadwal.');
        }

        $students = $query->get();
        $eligible = $policy->siswaSesuaiMapel($students, $jadwal);

        return new EloquentCollection($eligible);
    }

    private function siswaTerdaftarPadaJadwal(Siswa $siswa, JadwalPelajaran $jadwal, MapelAttendancePolicy $policy): bool
    {
        if (!$siswa->aktif) {
            return false;
        }

        if ($jadwal->rombel_id) {
            $belongsToClass = (int) $siswa->rombel_id === (int) $jadwal->rombel_id;
        } else {
            $belongsToClass = filled($jadwal->kelas)
                && Str::lower(trim((string) $siswa->kelas)) === Str::lower(trim((string) $jadwal->kelas));
        }

        if (!$belongsToClass) {
            return false;
        }

        return count($policy->siswaSesuaiMapel([$siswa], $jadwal)) === 1;
    }
}
