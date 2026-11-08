<?php

namespace App\Http\Controllers\Ortu;

use App\Http\Controllers\Controller;
use App\Models\Tka\AdaptiveSimulation;
use App\Services\Tka\TkaCognitiveAnalyticsService;
use Illuminate\Http\Request;

class TkaRiwayatController extends Controller
{
    use HasAnakLookup;

    public function index(TkaCognitiveAnalyticsService $analytics)
    {
        $anakList = $this->anakList();
        $anak = $this->getAnak($anakList);

        if (! $anak) {
            return view('ortu.tka.index', [
                'anak' => null,
                'anakList' => $anakList,
                'profile' => null,
                'rekomendasi' => null,
                'riwayatSimulasi' => collect(),
            ]);
        }

        $profile = $analytics->buildStudentCognitiveProfile($anak);
        $rekomendasi = $profile['rekomendasi'];

        $riwayatSimulasi = AdaptiveSimulation::query()
            ->where('siswa_id', $anak->id)
            ->where('status', 'selesai')
            ->latest('submitted_at')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return view('ortu.tka.index', compact(
            'anak',
            'anakList',
            'profile',
            'rekomendasi',
            'riwayatSimulasi'
        ));
    }

    public function show(AdaptiveSimulation $simulation, TkaCognitiveAnalyticsService $analytics)
    {
        $anakIds = $this->anakIds();
        abort_unless(
            in_array((int) $simulation->siswa_id, $anakIds, true),
            403,
            'Anda tidak memiliki akses ke laporan simulasi TKA ini.'
        );

        $anak = $simulation->siswa;
        $profile = $anak ? $analytics->buildStudentCognitiveProfile($anak) : null;

        return view('ortu.tka.show', compact('simulation', 'anak', 'profile'));
    }

    public function konfirmasiBaca(Request $request, AdaptiveSimulation $simulation)
    {
        $anakIds = $this->anakIds();
        abort_unless(
            in_array((int) $simulation->siswa_id, $anakIds, true),
            403,
            'Anda tidak memiliki akses ke laporan simulasi TKA ini.'
        );

        $validated = $request->validate([
            'catatan_ortu' => 'nullable|string|max:500',
        ]);

        $simulation->update([
            'dibaca_ortu_at' => $simulation->dibaca_ortu_at ?? now(),
            'dibaca_oleh_ortu_id' => auth()->id(),
            'catatan_ortu' => $validated['catatan_ortu'] ?? null,
        ]);

        return back()->with('success', 'Konfirmasi pemantauan orang tua berhasil disimpan.');
    }
}
