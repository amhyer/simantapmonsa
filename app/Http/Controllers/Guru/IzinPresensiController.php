<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Kehadiran;
use App\Models\PengajuanIzinPresensi;
use App\Models\Siswa;
use App\Services\AktivitasService;
use App\Traits\SyncableToSheet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IzinPresensiController extends Controller
{
    use SyncableToSheet;

    private function siswaWali(): Builder
    {
        $guruId = auth()->id();
        return Siswa::query()->where('aktif', true)->where(function (Builder $query) use ($guruId) {
            $query->whereHas('rombel', fn (Builder $rombel) => $rombel->where('guru_id', $guruId))
                ->orWhere(fn (Builder $fallback) => $fallback->whereNull('rombel_id')->where('guru_id', $guruId));
        });
    }

    public function index()
    {
        $izin = PengajuanIzinPresensi::whereIn('siswa_id', $this->siswaWali()->select('id'))
            ->with(['siswa', 'orangTua'])->orderByRaw("CASE WHEN status = 'menunggu' THEN 0 ELSE 1 END")
            ->orderByDesc('tanggal')->limit(100)->get();
        return view('guru.kehadiran.izin', compact('izin'));
    }

    public function review(Request $request, int $izin)
    {
        $data = $request->validate([
            'keputusan' => 'required|in:setujui,tolak',
            'catatan' => 'nullable|string|max:500|required_if:keputusan,tolak',
        ]);
        try {
            $attendance = DB::transaction(function () use ($izin, $data) {
                $item = PengajuanIzinPresensi::whereIn('siswa_id', $this->siswaWali()->select('id'))
                    ->lockForUpdate()->findOrFail($izin);
                abort_unless($item->status === 'menunggu', 409, 'Pengajuan ini sudah diproses.');
                $record = null;
                if ($data['keputusan'] === 'setujui') {
                    $record = Kehadiran::withTrashed()->where('siswa_id', $item->siswa_id)
                        ->whereDate('tanggal', $item->tanggal->toDateString())->lockForUpdate()->first();
                    if ($record && !$record->trashed() && ($record->status === 'H'
                        || (in_array($record->status, ['I', 'S'], true) && $record->status !== $item->jenis))) {
                        abort(409, 'Kehadiran yang sudah ada berbeda. Periksa dan koreksi secara manual sebelum menyetujui.');
                    }
                    $before = $record?->only(['status', 'keterangan', 'waktu_masuk']);
                    if ($record?->trashed()) $record->restore();
                    $record ??= new Kehadiran(['siswa_id' => $item->siswa_id, 'tanggal' => $item->tanggal]);
                    $record->fill([
                        'guru_id' => auth()->id(), 'status' => $item->jenis,
                        'waktu_masuk' => null, 'dicatat_oleh' => auth()->id(), 'metode' => 'manual',
                        'keterangan' => 'Izin orang tua diverifikasi wali kelas.',
                    ]);
                    $record->save();
                    AktivitasService::log('kehadiran', 'Verifikasi izin siswa', null, 'kehadiran', $record->id, $before, ['status' => $record->status, 'pengajuan_id' => $item->id]);
                }
                $item->status = $data['keputusan'] === 'setujui' ? 'disetujui' : 'ditolak';
                $item->diperiksa_oleh = auth()->id();
                $item->diperiksa_at = now();
                $item->catatan_pemeriksa = $data['catatan'] ?? null;
                $item->save();
                AktivitasService::log('kehadiran', 'Keputusan pengajuan izin', null, 'pengajuan_izin_presensi', $item->id, ['status' => 'menunggu'], ['status' => $item->status]);
                return $record;
            });
        } catch (QueryException $exception) {
            // Konflik dengan penulisan presensi bersamaan tidak boleh menimpa data.
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                return back()->with('error', 'Data presensi berubah bersamaan. Muat ulang dan periksa sebelum mencoba lagi.');
            }
            throw $exception;
        }
        if ($attendance) $this->syncToGoogleSheet('kehadiran', $attendance->fresh('siswa', 'guru'));
        return back()->with('success', 'Pengajuan berhasil diproses.');
    }
}
