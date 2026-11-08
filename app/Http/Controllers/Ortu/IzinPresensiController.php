<?php

namespace App\Http\Controllers\Ortu;

use App\Http\Controllers\Controller;
use App\Models\PengajuanIzinPresensi;
use App\Services\AktivitasService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class IzinPresensiController extends Controller
{
    use HasAnakLookup;

    public function store(Request $request)
    {
        $data = $request->validate([
            'siswa_id' => 'required|integer',
            'tanggal' => ['required', 'date', 'after_or_equal:'.today()->subDays(7)->toDateString(), 'before_or_equal:'.today()->toDateString()],
            'jenis' => 'required|in:I,S',
            'alasan' => 'required|string|max:500',
        ]);
        $anak = $this->anakList()->firstWhere('id', (int) $data['siswa_id']);
        abort_unless($anak && $anak->aktif, 403, 'Anak tidak terhubung atau tidak aktif.');

        try {
            $izin = PengajuanIzinPresensi::create([
                'siswa_id' => $anak->id,
                'orang_tua_id' => $request->user()->id,
                'tanggal' => $data['tanggal'],
                'jenis' => $data['jenis'],
                'alasan' => $data['alasan'],
            ]);
        } catch (QueryException $exception) {
            if (PengajuanIzinPresensi::where('siswa_id', $anak->id)->whereDate('tanggal', $data['tanggal'])->exists()) {
                throw ValidationException::withMessages(['tanggal' => 'Sudah ada pengajuan untuk anak dan tanggal ini. Hubungi wali kelas bila perlu koreksi.']);
            }
            throw $exception;
        }

        AktivitasService::log('kehadiran', 'Pengajuan izin siswa', 'Menunggu verifikasi wali kelas.', 'pengajuan_izin_presensi', $izin->id, null, ['siswa_id' => $anak->id, 'tanggal' => $data['tanggal'], 'jenis' => $data['jenis']]);
        return redirect()->route('ortu.kehadiran.index', ['siswa_id' => $anak->id])
            ->with('success', 'Pengajuan terkirim dan menunggu verifikasi wali kelas.');
    }
}
