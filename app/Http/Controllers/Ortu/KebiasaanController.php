<?php

namespace App\Http\Controllers\Ortu;

use App\Http\Controllers\Controller;
use App\Models\Kebiasaan;
use App\Models\Siswa;
use App\Traits\SyncableToSheet;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KebiasaanController extends Controller
{
    use HasPredikatKaih, SyncableToSheet;
    public function index(Request $request)
    {
        $user = auth()->user();
        $anakIds = $user->terhubung_dengan ?? [];

        if (empty($anakIds)) {
            return view('ortu.kebiasaan.index', [
                'anak' => null, 'hariIni' => null, 'kebiasaan' => null,
                'hari' => 0, 'rata' => 0, 'predikat' => ['huruf' => '-', 'label' => '-', 'kelas' => 'tag-mut'],
                'siswa' => null, 'anakList' => collect(), 'rataKebiasaan' => [], 'fields' => [],
                'semuaKebiasaan' => collect(),
            ]);
        }

        $siswaId = $request->get('siswa_id') ?? $anakIds[0];

        if (!in_array($siswaId, $anakIds)) {
            abort(403, 'Anda tidak memiliki akses ke data siswa ini.');
        }

        $siswa = Siswa::find($siswaId);

        if (!$siswa) {
            return view('ortu.kebiasaan.index', [
                'anak' => null, 'hariIni' => null, 'kebiasaan' => null,
                'hari' => 0, 'rata' => 0, 'predikat' => ['huruf' => '-', 'label' => '-', 'kelas' => 'tag-mut'],
                'siswa' => null, 'anakList' => Siswa::whereIn('id', $anakIds)->get(),
                'rataKebiasaan' => [], 'fields' => [], 'semuaKebiasaan' => collect(),
            ]);
        }

        $tanggal = $request->get('tanggal') ?? date('Y-m-d');
        $kebiasaan = Kebiasaan::where('siswa_id', $siswa->id)->where('tanggal', $tanggal)->first();
        $semuaKebiasaan = Kebiasaan::where('siswa_id', $siswa->id)->get();
        $hari = $semuaKebiasaan->count();

        $fields = ['bangun_pagi', 'beribadah', 'berolahraga', 'makan_sehat', 'gemar_belajar', 'bermasyarakat', 'tidur_cepat'];
        $rataKebiasaan = [];
        foreach ($fields as $field) {
            $values = $semuaKebiasaan->pluck($field)->filter()->values();
            $rataKebiasaan[$field] = $values->count() ? $values->avg() : 0;
        }

        $rata = collect($rataKebiasaan)->avg();
        $predikat = $this->getPredikatKaih($rata);
        $anakList = Siswa::whereIn('id', $anakIds)->get();

        return view('ortu.kebiasaan.index', compact(
            'siswa', 'anakList', 'tanggal', 'kebiasaan', 'semuaKebiasaan',
            'hari', 'rataKebiasaan', 'rata', 'predikat', 'fields'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'siswa_id' => 'required|exists:siswa,id',
            'tanggal' => 'required|date',
            'bangun_pagi' => 'nullable|integer|min:0|max:4',
            'beribadah' => 'nullable|integer|min:0|max:4',
            'berolahraga' => 'nullable|integer|min:0|max:4',
            'makan_sehat' => 'nullable|integer|min:0|max:4',
            'gemar_belajar' => 'nullable|integer|min:0|max:4',
            'bermasyarakat' => 'nullable|integer|min:0|max:4',
            'tidur_cepat' => 'nullable|integer|min:0|max:4',
            'catatan' => 'nullable|string',
        ]);

        $user = auth()->user();
        $anakIds = $user->terhubung_dengan ?? [];

        if (!in_array($validated['siswa_id'], $anakIds)) {
            abort(403, 'Anda tidak memiliki akses ke siswa ini.');
        }

        $kebiasaan = Kebiasaan::where('siswa_id', $validated['siswa_id'])
            ->where('tanggal', $validated['tanggal'])->first();

        $fields = ['bangun_pagi', 'beribadah', 'berolahraga', 'makan_sehat', 'gemar_belajar', 'bermasyarakat', 'tidur_cepat'];
        $hasValue = false;
        foreach ($fields as $field) {
            if (!empty($validated[$field])) { $hasValue = true; break; }
        }

        if (!$hasValue && empty($validated['catatan'])) {
            if ($kebiasaan) $kebiasaan->delete();
            return redirect()->back()->with('success', 'Laporan dikosongkan.');
        }

        if ($kebiasaan) {
            $updateData = $validated;
            $updateData['catatan_orang_tua'] = $validated['catatan'] ?? null;
            unset($updateData['catatan']);
            $kebiasaan->update($updateData);
            $this->syncToGoogleSheet('kebiasaan', $kebiasaan->fresh('siswa'));
        } else {
            $kebiasaan = Kebiasaan::create([
                'uuid' => Str::uuid(),
                'siswa_id' => $validated['siswa_id'],
                'tanggal' => $validated['tanggal'],
                'bangun_pagi' => $validated['bangun_pagi'] ?? null,
                'beribadah' => $validated['beribadah'] ?? null,
                'berolahraga' => $validated['berolahraga'] ?? null,
                'makan_sehat' => $validated['makan_sehat'] ?? null,
                'gemar_belajar' => $validated['gemar_belajar'] ?? null,
                'bermasyarakat' => $validated['bermasyarakat'] ?? null,
                'tidur_cepat' => $validated['tidur_cepat'] ?? null,
                'catatan_orang_tua' => $validated['catatan'] ?? null,
                'diisi_oleh' => $user->nama_lengkap,
                'waktu_simpan' => now(),
            ]);
            $this->syncToGoogleSheet('kebiasaan', $kebiasaan->fresh('siswa'));
        }

        return redirect()->back()->with('success', 'Laporan 7 Kebiasaan berhasil disimpan.');
    }
}
