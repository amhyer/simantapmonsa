<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ptk;
use App\Models\Rombel;
use App\Models\SekolahSettings;
use App\Models\Siswa;
use App\Models\User;
use App\Services\AktivitasService;
use App\Services\Presensi\IdentityCardService;
use App\Services\Presensi\StudentQrHasher;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PresensiKartuController extends Controller
{
    public function index(Request $request)
    {
        $jenis = in_array($request->query('jenis'), ['siswa', 'pegawai'], true)
            ? (string) $request->query('jenis')
            : 'siswa';

        $studentQuery = Siswa::query()->with('rombel')->where('aktif', true);
        if ($request->filled('rombel_id')) {
            $studentQuery->where('rombel_id', (int) $request->query('rombel_id'));
        }
        if ($jenis === 'siswa' && $request->filled('q')) {
            $term = trim((string) $request->query('q'));
            $studentQuery->where(function ($builder) use ($term) {
                $pattern = '%' . mb_strtolower($term) . '%';
                $builder->whereRaw('LOWER(nama_peserta_didik) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(nis) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(nisn, \'\')) LIKE ?', [$pattern]);
            });
        }

        $students = $studentQuery->orderBy('kelas')->orderBy('nama_peserta_didik')->paginate(40)->withQueryString();
        $rombels = Rombel::query()->orderBy('tingkat')->orderBy('nama_rombel')->get(['id', 'nama_rombel', 'tingkat']);

        $pegawaiQuery = User::query()
            ->whereIn('peran', IdentityCardService::PERAN_PEGAWAI)
            ->where('aktif', true);
        if ($request->filled('peran') && in_array($request->query('peran'), IdentityCardService::PERAN_PEGAWAI, true)) {
            $pegawaiQuery->where('peran', (string) $request->query('peran'));
        }
        if ($jenis === 'pegawai' && $request->filled('q')) {
            $term = trim((string) $request->query('q'));
            $pegawaiQuery->where(function ($builder) use ($term) {
                $pattern = '%' . mb_strtolower($term) . '%';
                $builder->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(nama_pengguna) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(nip, \'\')) LIKE ?', [$pattern]);
            });
        }

        $pegawai = $pegawaiQuery->orderBy('peran')->orderBy('nama_lengkap')->get();

        $ringkasan = [
            'siswa_total' => Siswa::query()->where('aktif', true)->count(),
            'siswa_berkartu' => Siswa::query()->where('aktif', true)->whereNotNull('qr_token_hash')->count(),
            'pegawai_total' => User::query()->whereIn('peran', IdentityCardService::PERAN_PEGAWAI)->where('aktif', true)->count(),
            'pegawai_berkartu' => User::query()->whereIn('peran', IdentityCardService::PERAN_PEGAWAI)->where('aktif', true)->whereNotNull('qr_token_hash')->count(),
        ];

        return view('admin.presensi.kartu', compact('students', 'rombels', 'pegawai', 'jenis', 'ringkasan'));
    }

    public function bind(Request $request, Siswa $siswa, StudentQrHasher $hasher)
    {
        abort_unless($siswa->aktif, 422, 'Siswa tidak aktif.');
        $validated = $request->validate([
            'qr_payload' => 'required|string|max:2048',
        ]);
        $digest = $hasher->hash($validated['qr_payload']);

        $otherStudent = Siswa::where('qr_token_hash', $digest)->where('id', '!=', $siswa->id)->first();
        $otherStaff = User::where('qr_token_hash', $digest)->first();
        if ($otherStudent || $otherStaff) {
            return response()->json([
                'success' => false,
                'message' => 'QR ini sudah terhubung ke pengguna lain. Lepaskan kartu lama terlebih dahulu.',
            ], Response::HTTP_CONFLICT);
        }

        $hadCard = filled($siswa->qr_token_hash);
        $siswa->forceFill([
            'qr_token_hash' => $digest,
            'qr_card_version' => 0,
        ])->save();

        AktivitasService::log(
            'kehadiran',
            $hadCard ? 'Ganti QR kartu siswa' : 'Hubungkan QR kartu siswa',
            'QR kartu untuk siswa ' . $siswa->nama_peserta_didik . ' ' . ($hadCard ? 'diperbarui.' : 'dihubungkan.'),
            'siswa',
            $siswa->id,
            ['qr_terhubung' => $hadCard],
            ['qr_terhubung' => true],
        );

        return response()->json([
            'success' => true,
            'message' => 'Kartu QR berhasil dihubungkan ke ' . $siswa->nama_peserta_didik . '.',
            'student' => ['id' => $siswa->id, 'nama' => $siswa->nama_peserta_didik],
        ]);
    }

    public function reset(Siswa $siswa)
    {
        abort_unless(filled($siswa->qr_token_hash), 404);
        $siswa->forceFill([
            'qr_token_hash' => null,
            'qr_card_version' => 0,
        ])->save();

        AktivitasService::log(
            'kehadiran',
            'Lepas QR kartu siswa',
            'QR kartu siswa ' . $siswa->nama_peserta_didik . ' dinonaktifkan oleh admin.',
            'siswa',
            $siswa->id,
            ['qr_terhubung' => true],
            ['qr_terhubung' => false],
        );

        return redirect()->route('admin.presensi.kartu.index', ['jenis' => 'siswa'])
            ->with('success', 'QR kartu siswa berhasil dilepas.');
    }

    public function issueSiswa(Siswa $siswa, IdentityCardService $cards)
    {
        abort_unless($siswa->aktif, 422, 'Siswa tidak aktif.');
        $hadCard = filled($siswa->qr_token_hash);
        $cards->issueSiswaCard($siswa, true);

        AktivitasService::log(
            'kehadiran',
            $hadCard ? 'Putar ulang kartu identitas siswa' : 'Terbitkan kartu identitas siswa',
            'Kartu identitas QR sistem untuk siswa ' . $siswa->nama_peserta_didik . ' diterbitkan (v' . $siswa->qr_card_version . ').',
            'siswa',
            $siswa->id,
            ['qr_terhubung' => $hadCard],
            ['qr_terhubung' => true, 'versi' => $siswa->qr_card_version],
        );

        return redirect()->back()->with('success', 'Kartu identitas QR untuk ' . $siswa->nama_peserta_didik . ' berhasil diterbitkan.');
    }

    public function issueSiswaMassal(Request $request, IdentityCardService $cards)
    {
        $query = Siswa::query()->where('aktif', true);
        if ($request->filled('rombel_id')) {
            $query->where('rombel_id', (int) $request->input('rombel_id'));
        }
        $putarUlang = $request->boolean('putar_ulang');
        if (!$putarUlang) {
            $query->whereNull('qr_token_hash');
        }

        $jumlah = 0;
        foreach ($query->cursor() as $siswa) {
            $cards->issueSiswaCard($siswa, $putarUlang);
            $jumlah++;
        }

        AktivitasService::log(
            'kehadiran',
            'Terbitkan massal kartu identitas siswa',
            "Menerbitkan {$jumlah} kartu identitas QR siswa.",
            'siswa',
            null,
            null,
            ['jumlah' => $jumlah, 'putar_ulang' => $putarUlang],
        );

        return redirect()->route('admin.presensi.kartu.index', array_filter([
            'jenis' => 'siswa',
            'rombel_id' => $request->input('rombel_id'),
        ]))->with('success', "{$jumlah} kartu identitas siswa siap dicetak dan dipindai.");
    }

    public function cetakSiswa(Siswa $siswa, IdentityCardService $cards)
    {
        abort_unless($siswa->aktif, 404);
        $cards->markSiswaAsPrinted($siswa);
        $siswa->loadMissing('rombel');
        $sekolah = SekolahSettings::query()->first();

        return view('presensi.kartu-cetak', [
            'judul' => 'Kartu Identitas Siswa — ' . $siswa->nama_peserta_didik,
            'subjudul' => ($siswa->rombel?->nama_rombel ?? $siswa->kelas ?? 'Siswa Aktif'),
            'cards' => [$cards->buildSiswaCardViewModel($siswa, $sekolah, true)],
            'kembaliUrl' => route('admin.presensi.kartu.index', ['jenis' => 'siswa']),
        ]);
    }

    public function cetakSiswaMassal(Request $request, IdentityCardService $cards)
    {
        $query = Siswa::query()->with('rombel')->where('aktif', true);
        $subjudul = 'Semua Kelas';

        if ($request->filled('rombel_id')) {
            $rombel = Rombel::findOrFail((int) $request->query('rombel_id'));
            $query->where('rombel_id', $rombel->id);
            $subjudul = 'Kelas ' . $rombel->nama_rombel;
        }

        $sekolah = SekolahSettings::query()->first();
        $list = $query->orderBy('kelas')->orderBy('nama_peserta_didik')->limit(250)->get();
        foreach ($list as $s) {
            $cards->markSiswaAsPrinted($s);
        }
        $cardItems = $list->map(fn (Siswa $s) => $cards->buildSiswaCardViewModel($s, $sekolah, true))->all();

        return view('presensi.kartu-cetak', [
            'judul' => 'Cetak Kartu Identitas Siswa',
            'subjudul' => $subjudul . ' (' . count($cardItems) . ' kartu)',
            'cards' => $cardItems,
            'kembaliUrl' => route('admin.presensi.kartu.index', array_filter([
                'jenis' => 'siswa',
                'rombel_id' => $request->query('rombel_id'),
            ])),
        ]);
    }

    public function rollbackSiswa(Siswa $siswa, IdentityCardService $cards)
    {
        abort_unless($siswa->aktif, 422, 'Siswa tidak aktif.');
        abort_unless((int) $siswa->printed_version > 0, 422, 'Siswa belum memiliki versi kartu fisik yang tercetak.');

        $oldVer = $siswa->qr_card_version;
        $success = $cards->rollbackSiswaToPrintedVersion($siswa);

        if ($success) {
            AktivitasService::log(
                'kehadiran',
                'Pulihkan versi QR kartu siswa',
                "Mengembalikan QR kartu {$siswa->nama_peserta_didik} dari v{$oldVer} ke versi cetak v{$siswa->printed_version}.",
                'siswa',
                $siswa->id,
                ['versi_sebelum' => $oldVer],
                ['versi_dipulihkan' => $siswa->printed_version],
            );

            return redirect()->back()->with('success', "QR kartu {$siswa->nama_peserta_didik} berhasil dikembalikan ke versi tercetak (v{$siswa->printed_version}). Kartu fisik lama dapat digunakan kembali!");
        }

        return redirect()->back()->with('error', 'Gagal memulihkan ke versi cetak.');
    }

    public function bindPegawai(Request $request, User $pegawai, StudentQrHasher $hasher)
    {
        abort_unless(
            $pegawai->aktif && in_array($pegawai->peran, IdentityCardService::PERAN_PEGAWAI, true),
            422,
            'Akun bukan pegawai aktif.'
        );

        $validated = $request->validate([
            'qr_payload' => 'required|string|max:2048',
        ]);
        $digest = $hasher->hash($validated['qr_payload']);

        $otherStaff = User::where('qr_token_hash', $digest)->where('id', '!=', $pegawai->id)->first();
        $otherStudent = Siswa::where('qr_token_hash', $digest)->first();
        if ($otherStaff || $otherStudent) {
            return response()->json([
                'success' => false,
                'message' => 'QR ini sudah terhubung ke pengguna lain. Lepaskan kartu lama terlebih dahulu.',
            ], Response::HTTP_CONFLICT);
        }

        $hadCard = filled($pegawai->qr_token_hash);
        $pegawai->forceFill([
            'qr_token_hash' => $digest,
            'qr_card_version' => 0,
        ])->save();

        AktivitasService::log(
            'kehadiran',
            $hadCard ? 'Ganti QR kartu pegawai' : 'Hubungkan QR kartu pegawai',
            'QR kartu untuk pegawai ' . $pegawai->nama_lengkap . ' dihubungkan.',
            'users',
            $pegawai->id,
            ['qr_terhubung' => $hadCard],
            ['qr_terhubung' => true],
        );

        return response()->json([
            'success' => true,
            'message' => 'Kartu QR berhasil dihubungkan ke ' . $pegawai->nama_lengkap . '.',
            'staff' => ['id' => $pegawai->id, 'nama' => $pegawai->nama_lengkap],
        ]);
    }

    public function issuePegawai(User $pegawai, IdentityCardService $cards)
    {
        abort_unless(
            $pegawai->aktif && in_array($pegawai->peran, IdentityCardService::PERAN_PEGAWAI, true),
            422,
            'Akun bukan pegawai aktif.'
        );

        $hadCard = filled($pegawai->qr_token_hash);
        $cards->issuePegawaiCard($pegawai, true);

        AktivitasService::log(
            'kehadiran',
            $hadCard ? 'Putar ulang kartu identitas pegawai' : 'Terbitkan kartu identitas pegawai',
            'Kartu identitas QR sistem untuk ' . $pegawai->nama_lengkap . ' diterbitkan (v' . $pegawai->qr_card_version . ').',
            'users',
            $pegawai->id,
            ['qr_terhubung' => $hadCard],
            ['qr_terhubung' => true, 'versi' => $pegawai->qr_card_version],
        );

        return redirect()->back()->with('success', 'Kartu identitas QR untuk ' . $pegawai->nama_lengkap . ' berhasil diterbitkan.');
    }

    public function issuePegawaiMassal(Request $request, IdentityCardService $cards)
    {
        $query = User::query()
            ->whereIn('peran', IdentityCardService::PERAN_PEGAWAI)
            ->where('aktif', true);

        $putarUlang = $request->boolean('putar_ulang');
        if (!$putarUlang) {
            $query->whereNull('qr_token_hash');
        }

        $jumlah = 0;
        foreach ($query->cursor() as $person) {
            $cards->issuePegawaiCard($person, $putarUlang);
            $jumlah++;
        }

        AktivitasService::log(
            'kehadiran',
            'Terbitkan massal kartu identitas pegawai',
            "Menerbitkan {$jumlah} kartu identitas QR guru & pegawai.",
            'users',
            null,
            null,
            ['jumlah' => $jumlah, 'putar_ulang' => $putarUlang],
        );

        return redirect()->route('admin.presensi.kartu.index', ['jenis' => 'pegawai'])
            ->with('success', "{$jumlah} kartu identitas guru & pegawai siap dicetak dan dipindai.");
    }

    public function resetPegawai(User $pegawai)
    {
        abort_unless(filled($pegawai->qr_token_hash), 404);
        $pegawai->forceFill([
            'qr_token_hash' => null,
            'qr_card_version' => 0,
        ])->save();

        AktivitasService::log(
            'kehadiran',
            'Lepas QR kartu pegawai',
            'QR kartu pegawai ' . $pegawai->nama_lengkap . ' dinonaktifkan oleh admin.',
            'users',
            $pegawai->id,
            ['qr_terhubung' => true],
            ['qr_terhubung' => false],
        );

        return redirect()->route('admin.presensi.kartu.index', ['jenis' => 'pegawai'])
            ->with('success', 'QR kartu pegawai berhasil dilepas.');
    }

    public function cetakPegawai(User $pegawai, IdentityCardService $cards)
    {
        abort_unless(
            $pegawai->aktif && in_array($pegawai->peran, IdentityCardService::PERAN_PEGAWAI, true),
            404
        );
        $cards->markPegawaiAsPrinted($pegawai);
        $sekolah = SekolahSettings::query()->first();

        return view('presensi.kartu-cetak', [
            'judul' => 'Kartu Identitas Pegawai — ' . $pegawai->nama_lengkap,
            'subjudul' => ucfirst((string) $pegawai->peran),
            'cards' => [$cards->buildPegawaiCardViewModel($pegawai, $sekolah, null, true)],
            'kembaliUrl' => route('admin.presensi.kartu.index', ['jenis' => 'pegawai']),
        ]);
    }

    public function cetakPegawaiMassal(Request $request, IdentityCardService $cards)
    {
        $query = User::query()
            ->whereIn('peran', IdentityCardService::PERAN_PEGAWAI)
            ->where('aktif', true);

        if ($request->filled('peran') && in_array($request->query('peran'), IdentityCardService::PERAN_PEGAWAI, true)) {
            $query->where('peran', (string) $request->query('peran'));
        }

        $sekolah = SekolahSettings::query()->first();
        $ptkByDapodik = Ptk::query()->whereNotNull('dapodik_id')->get()->keyBy('dapodik_id');
        $list = $query->orderBy('peran')->orderBy('nama_lengkap')->get();
        foreach ($list as $p) {
            $cards->markPegawaiAsPrinted($p);
        }

        $cardItems = $list->map(function (User $person) use ($cards, $sekolah, $ptkByDapodik) {
            $ptk = $person->dapodik_id ? $ptkByDapodik->get($person->dapodik_id) : null;

            return $cards->buildPegawaiCardViewModel($person, $sekolah, $ptk, true);
        })->all();

        return view('presensi.kartu-cetak', [
            'judul' => 'Cetak Kartu Identitas Guru & Pegawai',
            'subjudul' => count($cardItems) . ' kartu aktif',
            'cards' => $cardItems,
            'kembaliUrl' => route('admin.presensi.kartu.index', ['jenis' => 'pegawai']),
        ]);
    }

    public function rollbackPegawai(User $pegawai, IdentityCardService $cards)
    {
        abort_unless(
            $pegawai->aktif && in_array($pegawai->peran, IdentityCardService::PERAN_PEGAWAI, true),
            422,
            'Akun bukan pegawai aktif.'
        );
        abort_unless((int) $pegawai->printed_version > 0, 422, 'Pegawai belum memiliki versi kartu fisik yang tercetak.');

        $oldVer = $pegawai->qr_card_version;
        $success = $cards->rollbackPegawaiToPrintedVersion($pegawai);

        if ($success) {
            AktivitasService::log(
                'kehadiran',
                'Pulihkan versi QR kartu pegawai',
                "Mengembalikan QR kartu {$pegawai->nama_lengkap} dari v{$oldVer} ke versi cetak v{$pegawai->printed_version}.",
                'users',
                $pegawai->id,
                ['versi_sebelum' => $oldVer],
                ['versi_dipulihkan' => $pegawai->printed_version],
            );

            return redirect()->back()->with('success', "QR kartu {$pegawai->nama_lengkap} berhasil dikembalikan ke versi tercetak (v{$pegawai->printed_version}). Kartu fisik lama dapat digunakan kembali!");
        }

        return redirect()->back()->with('error', 'Gagal memulihkan ke versi cetak.');
    }
}
