<?php

namespace App\Services\Presensi;

use App\Models\Ptk;
use App\Models\SekolahSettings;
use App\Models\Siswa;
use App\Models\User;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;

class IdentityCardService
{
    public const PERAN_PEGAWAI = ['guru', 'kepsek', 'tendik'];

    public function __construct(
        private readonly StudentQrHasher $hasher,
    ) {
    }

    public function payloadForSiswa(Siswa $siswa, ?int $version = null): string
    {
        if (empty($siswa->uuid)) {
            $siswa->forceFill(['uuid' => (string) Str::uuid()])->save();
        }

        $ver = $version ?? max(1, (int) $siswa->qr_card_version);
        $sig = substr(
            hash_hmac(
                'sha256',
                "simantap-id:siswa:{$siswa->id}:{$siswa->uuid}:v{$ver}",
                (string) config('app.key')
            ),
            0,
            28
        );

        return "SIMANTAP:SISWA:{$siswa->uuid}:v{$ver}:{$sig}";
    }

    public function payloadForPegawai(User $user, ?int $version = null): string
    {
        if (empty($user->uuid)) {
            $user->forceFill(['uuid' => (string) Str::uuid()])->save();
        }

        $ver = $version ?? max(1, (int) $user->qr_card_version);
        $sig = substr(
            hash_hmac(
                'sha256',
                "simantap-id:pegawai:{$user->id}:{$user->uuid}:v{$ver}",
                (string) config('app.key')
            ),
            0,
            28
        );

        return "SIMANTAP:PEGAWAI:{$user->uuid}:v{$ver}:{$sig}";
    }

    /**
     * Pastikan kartu QR sistem siswa tersedia (kecuali jika admin memetakan kartu fisik eksternal).
     */
    public function ensureSiswaCard(Siswa $siswa): ?string
    {
        if (filled($siswa->qr_token_hash) && (int) $siswa->qr_card_version === 0) {
            return null;
        }

        if (filled($siswa->qr_token_hash) && (int) $siswa->qr_card_version > 0) {
            return $this->payloadForSiswa($siswa, (int) $siswa->qr_card_version);
        }

        return $this->issueSiswaCard($siswa, false);
    }

    public function issueSiswaCard(Siswa $siswa, bool $rotate = true): string
    {
        $nextVersion = $rotate
            ? ((int) $siswa->qr_card_version + 1)
            : max(1, (int) $siswa->qr_card_version);

        $payload = $this->payloadForSiswa($siswa, $nextVersion);
        $siswa->forceFill([
            'qr_card_version' => $nextVersion,
            'qr_token_hash' => $this->hasher->hash($payload),
        ])->save();

        return $payload;
    }

    /**
     * Pastikan kartu QR sistem pegawai (guru, kepsek, tendik) tersedia.
     */
    public function ensurePegawaiCard(User $user): ?string
    {
        if (filled($user->qr_token_hash) && (int) $user->qr_card_version === 0) {
            return null;
        }

        if (filled($user->qr_token_hash) && (int) $user->qr_card_version > 0) {
            return $this->payloadForPegawai($user, (int) $user->qr_card_version);
        }

        return $this->issuePegawaiCard($user, false);
    }

    public function issuePegawaiCard(User $user, bool $rotate = true): string
    {
        $nextVersion = $rotate
            ? ((int) $user->qr_card_version + 1)
            : max(1, (int) $user->qr_card_version);

        $payload = $this->payloadForPegawai($user, $nextVersion);
        $user->forceFill([
            'qr_card_version' => $nextVersion,
            'qr_token_hash' => $this->hasher->hash($payload),
        ])->save();

        return $payload;
    }

    public function svgQr(string $payload, int $size = 156): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 2),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $svg = $writer->writeString($payload, 'UTF-8', ErrorCorrectionLevel::M());

        return trim((string) preg_replace('/^<\?xml[^>]*\?>\s*/i', '', $svg));
    }

    public function sekolahInfo(?SekolahSettings $sekolah = null): array
    {
        $sekolah ??= SekolahSettings::query()->first();

        return [
            'nama' => $sekolah?->nama_sekolah ?: 'SIMANTAP Sekolah',
            'npsn' => $sekolah?->npsn ?: '-',
            'kota' => $sekolah?->kota ?: ($sekolah?->kecamatan ?: 'Indonesia'),
            'kepala_sekolah' => $sekolah?->kepala_sekolah ?: '-',
        ];
    }

    public function buildSiswaCardViewModel(
        Siswa $siswa,
        ?SekolahSettings $sekolah = null,
        bool $autoIssue = true,
    ): array {
        $payload = $autoIssue
            ? $this->ensureSiswaCard($siswa)
            : ((filled($siswa->qr_token_hash) && (int) $siswa->qr_card_version > 0)
                ? $this->payloadForSiswa($siswa, (int) $siswa->qr_card_version)
                : null);

        $nisText = trim(($siswa->nis ?: '-') . ($siswa->nisn ? ' / ' . $siswa->nisn : ''));
        $jkLabel = match ($siswa->jenis_kelamin) {
            'L' => 'Laki-laki',
            'P' => 'Perempuan',
            default => '-',
        };

        return [
            'jenis' => 'siswa',
            'badge' => 'KARTU IDENTITAS SISWA',
            'tema' => 'violet',
            'nama' => $siswa->nama_peserta_didik,
            'nomor_induk_label' => 'NIS / NISN',
            'nomor_induk' => $nisText,
            'unit_label' => 'Kelas / Rombel',
            'unit' => $siswa->rombel?->nama_rombel ?? $siswa->kelas ?? '-',
            'info_tambahan_label' => 'Jenis Kelamin',
            'info_tambahan' => $jkLabel,
            'foto_url' => $siswa->foto ? asset('storage/' . ltrim($siswa->foto, '/')) : null,
            'inisial' => mb_strtoupper(mb_substr((string) $siswa->nama_peserta_didik, 0, 2)),
            'kode_kartu' => 'SIS-' . str_pad((string) $siswa->id, 5, '0', STR_PAD_LEFT)
                . ((int) $siswa->qr_card_version > 0 ? '-V' . $siswa->qr_card_version : '-EXT'),
            'qr_svg' => $payload ? $this->svgQr($payload, 152) : null,
            'qr_aktif' => filled($siswa->qr_token_hash),
            'eksternal_terhubung' => $payload === null && filled($siswa->qr_token_hash),
            'instruksi_scan' => 'Tunjukkan kartu ini kepada Guru pada jadwal masuk / mata pelajaran hari ini.',
            'sekolah' => $this->sekolahInfo($sekolah),
        ];
    }

    public function buildPegawaiCardViewModel(
        User $user,
        ?SekolahSettings $sekolah = null,
        ?Ptk $ptk = null,
        bool $autoIssue = true,
    ): array {
        if ($ptk === null) {
            $ptk = $this->resolvePtk($user);
        }

        $payload = $autoIssue
            ? $this->ensurePegawaiCard($user)
            : ((filled($user->qr_token_hash) && (int) $user->qr_card_version > 0)
                ? $this->payloadForPegawai($user, (int) $user->qr_card_version)
                : null);

        $peranLabel = match ($user->peran) {
            'kepsek' => 'Kepala Sekolah',
            'guru' => 'Guru Pendidik',
            'tendik' => 'Tenaga Kependidikan',
            default => ucfirst((string) $user->peran),
        };

        $tema = match ($user->peran) {
            'kepsek' => 'emerald',
            'guru' => 'blue',
            default => 'amber',
        };

        $badge = match ($user->peran) {
            'kepsek' => 'KARTU IDENTITAS KEPALA SEKOLAH',
            'guru' => 'KARTU IDENTITAS GURU',
            default => 'KARTU IDENTITAS PEGAWAI',
        };

        $nomorInduk = $user->nip
            ?: ($ptk?->nip ?: ($ptk?->nuptk ?: $user->nama_pengguna));
        $nomorLabel = ($user->nip || $ptk?->nip)
            ? 'NIP'
            : ($ptk?->nuptk ? 'NUPTK' : 'ID Pengguna');

        $jabatan = $ptk?->jabatan ?: $peranLabel;
        if ($user->peran === 'guru' && filled($user->kelas_mata_pelajaran)) {
            $jabatan .= ' · ' . $user->kelas_mata_pelajaran;
        }

        return [
            'jenis' => 'pegawai',
            'badge' => $badge,
            'tema' => $tema,
            'nama' => $ptk?->nama_gelar ?: $user->nama_lengkap,
            'nomor_induk_label' => $nomorLabel,
            'nomor_induk' => $nomorInduk ?: '-',
            'unit_label' => 'Jabatan / Tugas',
            'unit' => $jabatan,
            'info_tambahan_label' => 'Peran Sistem',
            'info_tambahan' => $peranLabel,
            'foto_url' => $user->foto ? asset('storage/' . ltrim($user->foto, '/')) : null,
            'inisial' => mb_strtoupper(mb_substr((string) $user->nama_lengkap, 0, 2)),
            'kode_kartu' => 'PEG-' . str_pad((string) $user->id, 4, '0', STR_PAD_LEFT)
                . ((int) $user->qr_card_version > 0 ? '-V' . $user->qr_card_version : '-EXT'),
            'qr_svg' => $payload ? $this->svgQr($payload, 152) : null,
            'qr_aktif' => filled($user->qr_token_hash),
            'eksternal_terhubung' => $payload === null && filled($user->qr_token_hash),
            'instruksi_scan' => 'Pindai mandiri kartu ini pada menu Presensi Saya saat jam masuk & pulang.',
            'sekolah' => $this->sekolahInfo($sekolah),
        ];
    }

    public function resolvePtk(User $user): ?Ptk
    {
        if (filled($user->dapodik_id)) {
            $byDapodik = Ptk::query()->where('dapodik_id', $user->dapodik_id)->first();
            if ($byDapodik) {
                return $byDapodik;
            }
        }

        return Ptk::query()
            ->whereRaw('LOWER(nama) = ?', [mb_strtolower(trim((string) $user->nama_lengkap))])
            ->first();
    }
}
