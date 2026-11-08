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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
                "simantap-id:siswa:{$siswa->id}:{$siswa->uuid}:v{$ver}" . ($siswa->qr_generation_nonce ? ":{$siswa->qr_generation_nonce}" : ''),
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
                "simantap-id:pegawai:{$user->id}:{$user->uuid}:v{$ver}" . ($user->qr_generation_nonce ? ":{$user->qr_generation_nonce}" : ''),
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
        return DB::transaction(function () use ($siswa) {
            $locked = Siswa::whereKey($siswa->id)->lockForUpdate()->firstOrFail();
            $siswa->setRawAttributes($locked->getAttributes(), true);
            if ($locked->qr_revoked || (filled($locked->qr_token_hash) && (int) $locked->qr_card_version === 0)) {
                return null;
            }
            $payload = filled($locked->qr_token_hash)
                ? $this->payloadForSiswa($locked, (int) $locked->qr_card_version)
                : $this->issueSiswaCard($locked, false);
            $siswa->setRawAttributes($locked->getAttributes(), true);
            return $payload && hash_equals((string) $locked->qr_token_hash, $this->hasher->hash($payload)) ? $payload : null;
        });
    }

    public function issueSiswaCard(Siswa $siswa, bool $rotate = true): ?string
    {
        return DB::transaction(function () use ($siswa, $rotate) {
            $locked = Siswa::whereKey($siswa->id)->lockForUpdate()->firstOrFail();
            // Jangan diam-diam mengganti kartu fisik ketika hanya diminta memastikan kartu.
            if (!$rotate && filled($locked->qr_token_hash) && (int) $locked->qr_card_version === 0) return null;
            $next = !$rotate && filled($locked->qr_token_hash)
                ? max(1, (int) $locked->qr_card_version)
                : max((int) $locked->qr_card_version, (int) $locked->qr_version_counter) + 1;
            $hadHash = filled($locked->qr_token_hash);
            if ((!$hadHash || !$locked->qr_generation_nonce) && ($rotate || !$hadHash)) {
                $locked->qr_generation_nonce = Str::random(32);
            }
            $payload = $this->payloadForSiswa($locked, $next);
            $updates = [
                'qr_generation_nonce' => $locked->qr_generation_nonce,
                'qr_card_version' => $next,
                'qr_version_counter' => max((int) $locked->qr_version_counter, $next),
                'qr_revoked' => false,
                'qr_token_hash' => $this->hasher->hash($payload),
            ];
            if ($rotate && $hadHash) $updates['qr_previous_token_hash'] = $locked->qr_token_hash;
            if (!$hadHash) $updates['qr_previous_token_hash'] = null;
            $locked->forceFill($updates)->save();
            $siswa->setRawAttributes($locked->getAttributes(), true);
            return $payload;
        });
    }

    public function ensurePegawaiCard(User $user): ?string
    {
        return DB::transaction(function () use ($user) {
            $locked = User::forSchool()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->setRawAttributes($locked->getAttributes(), true);
            if ($locked->qr_revoked || (filled($locked->qr_token_hash) && (int) $locked->qr_card_version === 0)) {
                return null;
            }
            $payload = filled($locked->qr_token_hash)
                ? $this->payloadForPegawai($locked, (int) $locked->qr_card_version)
                : $this->issuePegawaiCard($locked, false);
            $user->setRawAttributes($locked->getAttributes(), true);
            return $payload && hash_equals((string) $locked->qr_token_hash, $this->hasher->hash($payload)) ? $payload : null;
        });
    }

    public function issuePegawaiCard(User $user, bool $rotate = true): ?string
    {
        return DB::transaction(function () use ($user, $rotate) {
            $locked = User::forSchool()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (!$rotate && filled($locked->qr_token_hash) && (int) $locked->qr_card_version === 0) return null;
            $next = !$rotate && filled($locked->qr_token_hash)
                ? max(1, (int) $locked->qr_card_version)
                : max((int) $locked->qr_card_version, (int) $locked->qr_version_counter) + 1;
            $hadHash = filled($locked->qr_token_hash);
            if ((!$hadHash || !$locked->qr_generation_nonce) && ($rotate || !$hadHash)) {
                $locked->qr_generation_nonce = Str::random(32);
            }
            $payload = $this->payloadForPegawai($locked, $next);
            $updates = [
                'qr_generation_nonce' => $locked->qr_generation_nonce,
                'qr_card_version' => $next,
                'qr_version_counter' => max((int) $locked->qr_version_counter, $next),
                'qr_revoked' => false,
                'qr_token_hash' => $this->hasher->hash($payload),
            ];
            if ($rotate && $hadHash) $updates['qr_previous_token_hash'] = $locked->qr_token_hash;
            if (!$hadHash) $updates['qr_previous_token_hash'] = null;
            $locked->forceFill($updates)->save();
            $user->setRawAttributes($locked->getAttributes(), true);
            return $payload;
        });
    }

    public function markSiswaAsPrinted(Siswa $siswa, ?int $version = null): void
    {
        $ver = $version ?? (int) $siswa->qr_card_version;
        if ($ver < 1 || !$siswa->qr_token_hash || $siswa->qr_revoked) return;
        Siswa::whereKey($siswa->id)->where('qr_revoked', false)->where('qr_card_version', $ver)
            ->where('qr_token_hash', $siswa->qr_token_hash)->update(['printed_at' => now(), 'printed_version' => $ver]);
    }

    public function markManySiswaAsPrinted($siswas): void
    {
        foreach ($siswas as $siswa) $this->markSiswaAsPrinted($siswa);
    }

    public function markPegawaiAsPrinted(User $user, ?int $version = null): void
    {
        $ver = $version ?? (int) $user->qr_card_version;
        if ($ver < 1 || !$user->qr_token_hash || $user->qr_revoked) return;
        User::forSchool()->whereKey($user->id)->where('qr_revoked', false)->where('qr_card_version', $ver)
            ->where('qr_token_hash', $user->qr_token_hash)->update(['printed_at' => now(), 'printed_version' => $ver]);
    }

    public function markManyPegawaiAsPrinted($pegawais): void
    {
        foreach ($pegawais as $user) $this->markPegawaiAsPrinted($user);
    }

    public function rollbackSiswaToPrintedVersion(Siswa $siswa): bool
    {
        return DB::transaction(function () use ($siswa) {
            $locked = Siswa::whereKey($siswa->id)->lockForUpdate()->firstOrFail();
            $version = (int) $locked->printed_version;
            if ($version <= 0 || $locked->qr_revoked) return false;
            $nonce = $locked->qr_generation_nonce;
            $digest = $this->hasher->hash($this->payloadForSiswa($locked, $version));
            if (!in_array($digest, [$locked->qr_token_hash, $locked->qr_previous_token_hash], true) && $nonce) {
                // Versi transisi lama dapat memakai format sebelum nonce diperkenalkan.
                $locked->qr_generation_nonce = null;
                $digest = $this->hasher->hash($this->payloadForSiswa($locked, $version));
            }
            if (!in_array($digest, [$locked->qr_token_hash, $locked->qr_previous_token_hash], true)) return false;
            $locked->forceFill(['qr_card_version' => $version, 'qr_token_hash' => $digest, 'qr_previous_token_hash' => null])->save();
            $siswa->setRawAttributes($locked->getAttributes(), true);
            return true;
        });
    }

    public function rollbackPegawaiToPrintedVersion(User $user): bool
    {
        return DB::transaction(function () use ($user) {
            $locked = User::forSchool()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $version = (int) $locked->printed_version;
            if ($version <= 0 || $locked->qr_revoked) return false;
            $nonce = $locked->qr_generation_nonce;
            $digest = $this->hasher->hash($this->payloadForPegawai($locked, $version));
            if (!in_array($digest, [$locked->qr_token_hash, $locked->qr_previous_token_hash], true) && $nonce) {
                // Versi transisi lama dapat memakai format sebelum nonce diperkenalkan.
                $locked->qr_generation_nonce = null;
                $digest = $this->hasher->hash($this->payloadForPegawai($locked, $version));
            }
            if (!in_array($digest, [$locked->qr_token_hash, $locked->qr_previous_token_hash], true)) return false;
            $locked->forceFill(['qr_card_version' => $version, 'qr_token_hash' => $digest, 'qr_previous_token_hash' => null])->save();
            $user->setRawAttributes($locked->getAttributes(), true);
            return true;
        });
    }

    /** Hanya token aktif dan satu token transisi yang eksplisit dapat dipakai. */
    public function resolveSiswaFromPayload(string $rawPayload): ?Siswa
    {
        if (trim($rawPayload) === '') return null;
        $digest = $this->hasher->hash($rawPayload);
        return Siswa::where('aktif', true)->where('qr_revoked', false)
            ->whereNotNull('qr_token_hash')
            ->where(fn ($q) => $q->where('qr_token_hash', $digest)->orWhere('qr_previous_token_hash', $digest))
            ->first();
    }

    public function resolvePegawaiFromPayload(string $rawPayload): ?User
    {
        if (trim($rawPayload) === '') return null;
        $digest = $this->hasher->hash($rawPayload);
        return User::forSchool()->where('aktif', true)->where('qr_revoked', false)
            ->whereNotNull('qr_token_hash')
            ->where(fn ($q) => $q->where('qr_token_hash', $digest)->orWhere('qr_previous_token_hash', $digest))
            ->first();
    }

    public function svgQr(string $payload, int $size = 156): string
    {
        $cacheKey = 'simantap_qr_svg_' . md5($payload . ':' . $size);

        return Cache::rememberForever($cacheKey, function () use ($payload, $size) {
            $renderer = new ImageRenderer(
                new RendererStyle($size, 2),
                new SvgImageBackEnd()
            );
            $writer = new Writer($renderer);
            $svg = $writer->writeString($payload, 'UTF-8', ErrorCorrectionLevel::M());

            return trim((string) preg_replace('/^<\?xml[^>]*\?>\s*/i', '', $svg));
        });
    }

    public function sekolahInfo(?SekolahSettings $sekolah = null): array
    {
        $sekolah ??= SekolahSettings::query()->first();

        return [
            'nama' => $sekolah?->nama_sekolah ?: 'SIMANTAP Sekolah',
            'npsn' => $sekolah?->npsn ?: '-',
            'alamat' => $sekolah?->alamat ?: 'Alamat Sekolah',
            'kota' => $sekolah?->kota ?: ($sekolah?->kecamatan ?: 'Indonesia'),
            'kecamatan' => $sekolah?->kecamatan ?: '-',
            'provinsi' => $sekolah?->provinsi ?: '-',
            'kode_pos' => $sekolah?->kode_pos ?: '-',
            'telepon' => $sekolah?->telepon ?: '-',
            'email' => $sekolah?->email ?: '-',
            'website' => $sekolah?->website ?: '-',
            'kepala_sekolah' => $sekolah?->kepala_sekolah ?: '-',
            'akreditasi' => $sekolah?->akreditasi ?: 'A',
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
            'eksternal_terhubung' => $payload === null && filled($siswa->qr_token_hash) && (int) $siswa->qr_card_version === 0 && !$siswa->qr_revoked,
            'qr_revoked' => (bool) $siswa->qr_revoked,
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
            'eksternal_terhubung' => $payload === null && filled($user->qr_token_hash) && (int) $user->qr_card_version === 0 && !$user->qr_revoked,
            'qr_revoked' => (bool) $user->qr_revoked,
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
