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
        $updates = [
            'qr_card_version' => $nextVersion,
            'qr_token_hash' => $this->hasher->hash($payload),
        ];

        if ($rotate && filled($siswa->qr_token_hash)) {
            $updates['qr_previous_token_hash'] = $siswa->qr_token_hash;
        }

        $siswa->forceFill($updates)->save();

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
        $updates = [
            'qr_card_version' => $nextVersion,
            'qr_token_hash' => $this->hasher->hash($payload),
        ];

        if ($rotate && filled($user->qr_token_hash)) {
            $updates['qr_previous_token_hash'] = $user->qr_token_hash;
        }

        $user->forceFill($updates)->save();

        return $payload;
    }

    public function markSiswaAsPrinted(Siswa $siswa, ?int $version = null): void
    {
        $ver = $version ?? max(1, (int) $siswa->qr_card_version);
        $siswa->forceFill([
            'printed_at' => now(),
            'printed_version' => $ver,
        ])->save();
    }

    public function markPegawaiAsPrinted(User $user, ?int $version = null): void
    {
        $ver = $version ?? max(1, (int) $user->qr_card_version);
        $user->forceFill([
            'printed_at' => now(),
            'printed_version' => $ver,
        ])->save();
    }

    public function rollbackSiswaToPrintedVersion(Siswa $siswa): bool
    {
        $printedVer = (int) $siswa->printed_version;
        if ($printedVer <= 0) {
            return false;
        }

        $payload = $this->payloadForSiswa($siswa, $printedVer);
        $siswa->forceFill([
            'qr_card_version' => $printedVer,
            'qr_token_hash' => $this->hasher->hash($payload),
            'qr_previous_token_hash' => null,
        ])->save();

        return true;
    }

    public function rollbackPegawaiToPrintedVersion(User $user): bool
    {
        $printedVer = (int) $user->printed_version;
        if ($printedVer <= 0) {
            return false;
        }

        $payload = $this->payloadForPegawai($user, $printedVer);
        $user->forceFill([
            'qr_card_version' => $printedVer,
            'qr_token_hash' => $this->hasher->hash($payload),
            'qr_previous_token_hash' => null,
        ])->save();

        return true;
    }

    /**
     * Cari siswa dari raw payload QR dengan toleransi mundur multi-versi:
     * 1. Hash cocok dengan qr_token_hash aktif.
     * 2. Hash cocok dengan qr_previous_token_hash (kartu fisik versi sebelumnya yang belum ditarik).
     * 3. HMAC valid dan versi kartu <= qr_card_version (kartu sah yang diterbitkan resmi sistem).
     */
    public function resolveSiswaFromPayload(string $rawPayload): ?Siswa
    {
        $trimmed = trim($rawPayload);
        if ($trimmed === '') {
            return null;
        }

        $digest = $this->hasher->hash($trimmed);

        // 1. Cek token aktif saat ini
        $siswa = Siswa::where('qr_token_hash', $digest)->where('aktif', true)->first();
        if ($siswa) {
            return $siswa;
        }

        // 2. Cek token versi sebelumnya (sebelum admin klik ganti QR)
        $siswa = Siswa::where('qr_previous_token_hash', $digest)->where('aktif', true)->first();
        if ($siswa) {
            return $siswa;
        }

        // 3. Verifikasi payload sistem bertanda tangan HMAC (SIMANTAP:SISWA:{uuid}:v{ver}:{sig})
        if (str_starts_with($trimmed, 'SIMANTAP:SISWA:')) {
            $parts = explode(':', $trimmed);
            if (count($parts) >= 5) {
                $uuid = $parts[2];
                $verStr = $parts[3];
                $sig = $parts[4];
                $ver = (int) ltrim($verStr, 'v');

                if ($ver > 0) {
                    $found = Siswa::where('uuid', $uuid)->where('aktif', true)->first();
                    if ($found) {
                        $expectedSig = substr(
                            hash_hmac(
                                'sha256',
                                "simantap-id:siswa:{$found->id}:{$found->uuid}:v{$ver}",
                                (string) config('app.key')
                            ),
                            0,
                            28
                        );

                        if (hash_equals($expectedSig, $sig) && $ver <= (int) $found->qr_card_version) {
                            return $found;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Cari pegawai dari raw payload QR dengan toleransi mundur multi-versi:
     * 1. Hash cocok dengan qr_token_hash aktif.
     * 2. Hash cocok dengan qr_previous_token_hash.
     * 3. HMAC valid dan versi kartu <= qr_card_version.
     */
    public function resolvePegawaiFromPayload(string $rawPayload): ?User
    {
        $trimmed = trim($rawPayload);
        if ($trimmed === '') {
            return null;
        }

        $digest = $this->hasher->hash($trimmed);

        // 1. Cek token aktif saat ini
        $user = User::where('qr_token_hash', $digest)->where('aktif', true)->first();
        if ($user) {
            return $user;
        }

        // 2. Cek token versi sebelumnya
        $user = User::where('qr_previous_token_hash', $digest)->where('aktif', true)->first();
        if ($user) {
            return $user;
        }

        // 3. Verifikasi payload sistem bertanda tangan HMAC (SIMANTAP:PEGAWAI:{uuid}:v{ver}:{sig})
        if (str_starts_with($trimmed, 'SIMANTAP:PEGAWAI:')) {
            $parts = explode(':', $trimmed);
            if (count($parts) >= 5) {
                $uuid = $parts[2];
                $verStr = $parts[3];
                $sig = $parts[4];
                $ver = (int) ltrim($verStr, 'v');

                if ($ver > 0) {
                    $found = User::where('uuid', $uuid)->where('aktif', true)->first();
                    if ($found) {
                        $expectedSig = substr(
                            hash_hmac(
                                'sha256',
                                "simantap-id:pegawai:{$found->id}:{$found->uuid}:v{$ver}",
                                (string) config('app.key')
                            ),
                            0,
                            28
                        );

                        if (hash_equals($expectedSig, $sig) && $ver <= (int) $found->qr_card_version) {
                            return $found;
                        }
                    }
                }
            }
        }

        return null;
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
            'printed_version' => (int) $siswa->printed_version,
            'printed_at' => $siswa->printed_at,
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
            'printed_version' => (int) $user->printed_version,
            'printed_at' => $user->printed_at,
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
