<?php

namespace App\Http\Controllers\Guru\PembelajaranMendalam;

use App\Http\Controllers\Controller;
use App\Models\PembelajaranMendalam\BukuPaketSibi;
use App\Models\User;
use App\Services\PembelajaranMendalam\GeminiPembelajaranService;
use App\Services\PembelajaranMendalam\PembelajaranContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

class BukuPaketController extends Controller
{
    public function __construct(
        private readonly PembelajaranContextService $ctx,
        private readonly GeminiPembelajaranService $ai,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);
        $mapelList = $this->ctx->daftarMapel();

        $ctx = $this->ctx->getContext($request);
        $tingkat = $ctx['tingkat'];
        $mapelAktif = trim((string) $request->query('mapel', $mapelList->first()?->nama ?? 'Matematika'));

        $bukuAktif = BukuPaketSibi::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tingkat', $tingkat)
            ->where('nama_mapel', $mapelAktif)
            ->first();

        $ringkasanKelas = BukuPaketSibi::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('tingkat', $tingkat)
            ->orderBy('nama_mapel')
            ->get()
            ->keyBy('nama_mapel');

        return view('guru.pm.buku-paket.index', [
            'tingkat' => $tingkat,
            'mapelList' => $mapelList,
            'mapelAktif' => $mapelAktif,
            'bukuAktif' => $bukuAktif,
            'ringkasanKelas' => $ringkasanKelas,
            'aiQuota' => $this->ai->quotaStatus($tenantId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);

        $data = $request->validate([
            'nama_mapel' => ['required', 'string', 'max:120'],
            'tingkat' => ['required', 'integer', 'min:1', 'max:6'],
            'judul' => ['required', 'string', 'max:255'],
            'penulis' => ['nullable', 'string', 'max:200'],
            'tautan_sibi' => ['nullable', 'url', 'max:500'],
            'daftar_isi' => ['nullable', 'string'],
        ]);

        $mapelId = $this->ctx->daftarMapel()->firstWhere('nama', $data['nama_mapel'])?->id;

        BukuPaketSibi::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'nama_mapel' => $data['nama_mapel'],
                'tingkat' => (int) $data['tingkat'],
            ],
            [
                'guru_id' => $guru->id,
                'mata_pelajaran_id' => $mapelId,
                'judul' => $data['judul'],
                'penulis' => $data['penulis'] ?? null,
                'tautan_sibi' => $data['tautan_sibi'] ?? null,
                'daftar_isi' => $data['daftar_isi'] ?? null,
            ]
        );

        return redirect()
            ->route('guru.pm.buku-paket.index', ['tingkat' => $data['tingkat'], 'mapel' => $data['nama_mapel']])
            ->with('success', 'Data Buku Paket (SIBI) dan daftar isi berhasil disimpan.');
    }

    public function aiGenerate(Request $request): RedirectResponse
    {
        /** @var User $guru */
        $guru = Auth::user();
        $tenantId = $this->ctx->tenantId($guru);

        $data = $request->validate([
            'nama_mapel' => ['required', 'string', 'max:120'],
            'tingkat' => ['required', 'integer', 'min:1', 'max:6'],
            'judul' => ['required', 'string', 'max:255'],
        ]);

        try {
            $babList = $this->ai->susunDaftarIsiBuku($data['nama_mapel'], (int) $data['tingkat'], $data['judul'], $tenantId, $guru->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $mapelId = $this->ctx->daftarMapel()->firstWhere('nama', $data['nama_mapel'])?->id;

        BukuPaketSibi::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'nama_mapel' => $data['nama_mapel'],
                'tingkat' => (int) $data['tingkat'],
            ],
            [
                'guru_id' => $guru->id,
                'mata_pelajaran_id' => $mapelId,
                'judul' => $data['judul'],
                'daftar_isi' => implode("\n", $babList),
            ]
        );

        return redirect()
            ->route('guru.pm.buku-paket.index', ['tingkat' => $data['tingkat'], 'mapel' => $data['nama_mapel']])
            ->with('success', count($babList).' bab daftar isi berhasil disusun dengan AI.');
    }
}
