<?php

namespace App\Services\Elearning;

use App\Modules\Tenant\Contexts\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pengelolaan unggahan berkas E-Learning yang terisolasi pada disk `local`
 * (bukan publik) dengan validasi server-side terhadap ekstensi dan MIME nyata.
 */
class ElearningFileService
{
    public const DISK = 'local';

    public const MAX_KB_GURU = 10240; // 10 MB

    public const MAX_KB_SISWA = 5120; // 5 MB

    /** @var array<int, string> */
    public const ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt',
        'jpg', 'jpeg', 'png', 'webp', 'mp3', 'mp4',
    ];

    /** @var array<int, string> */
    private const BLOCKED_EXTENSIONS = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'js', 'mjs',
        'html', 'htm', 'svg', 'exe', 'bat', 'cmd', 'sh', 'bash', 'dll',
    ];

    /** @var array<int, string> */
    private const ALLOWED_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip', // sebagian berkas docx/pptx/xlsx terdeteksi sebagai zip oleh libmagic
        'text/plain',
        'image/jpeg',
        'image/png',
        'image/webp',
        'audio/mpeg',
        'audio/mp3',
        'video/mp4',
    ];

    public static function validationRuleGuru(): array
    {
        return ['nullable', 'file', 'max:'.self::MAX_KB_GURU, 'mimes:'.implode(',', self::ALLOWED_EXTENSIONS)];
    }

    public static function validationRuleSiswa(): array
    {
        return ['nullable', 'file', 'max:'.self::MAX_KB_SISWA, 'mimes:'.implode(',', self::ALLOWED_EXTENSIONS)];
    }

    /**
     * Validasi tambahan berbasis isi file (MIME server-side) lalu simpan ke disk privat.
     *
     * @return array{path: string, original_name: string, mime: string, size: int}
     */
    public function store(UploadedFile $file, string $kategori, ?int $tenantId = null, string $fieldName = 'file'): array
    {
        $originalName = $this->sanitizeOriginalName($file->getClientOriginalName());
        $clientExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($clientExt !== '' && in_array($clientExt, self::BLOCKED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                $fieldName => 'Tipe berkas tersebut tidak diizinkan demi keamanan.',
            ]);
        }

        // Gunakan MIME dari pemeriksaan server-side (finfo), bukan header client.
        $detectedMime = strtolower((string) ($file->getMimeType() ?: 'application/octet-stream'));
        if (! in_array($detectedMime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([
                $fieldName => "Format berkas ({$detectedMime}) tidak didukung. Unggah PDF, dokumen Office, gambar, audio MP3, video MP4, atau TXT.",
            ]);
        }

        $guessedExt = strtolower((string) ($file->guessExtension() ?: $clientExt));
        $finalExt = in_array($clientExt, self::ALLOWED_EXTENSIONS, true)
            ? $clientExt
            : (in_array($guessedExt, self::ALLOWED_EXTENSIONS, true) ? $guessedExt : 'bin');

        if ($finalExt === 'bin' || in_array($finalExt, self::BLOCKED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                $fieldName => 'Ekstensi berkas tidak valid.',
            ]);
        }

        $resolvedTenant = $tenantId ?? TenantContext::id();
        $tenantSeg = $resolvedTenant !== null ? 'tenant-'.$resolvedTenant : 'tenant-default';
        $safeKategori = preg_replace('/[^a-z0-9_-]/', '', strtolower($kategori)) ?: 'umum';
        $dir = sprintf('elearning/%s/%s/%s', $tenantSeg, $safeKategori, now()->format('Y/m'));
        $filename = Str::uuid().'.'.$finalExt;

        $storedPath = $file->storeAs($dir, $filename, self::DISK);
        if (! $storedPath) {
            throw ValidationException::withMessages([
                $fieldName => 'Gagal menyimpan berkas lampiran.',
            ]);
        }

        return [
            'path' => $storedPath,
            'original_name' => $originalName,
            'mime' => $detectedMime,
            'size' => (int) ($file->getSize() ?: 0),
        ];
    }

    public function deleteQuietly(?string $path): void
    {
        if (! $path) {
            return;
        }

        try {
            if (Storage::disk(self::DISK)->exists($path)) {
                Storage::disk(self::DISK)->delete($path);
            }
        } catch (\Throwable) {
            // Jangan gagal-total saat pembersihan berkas lama.
        }
    }

    public function download(?string $path, ?string $originalName = null): StreamedResponse|BinaryFileResponse
    {
        if (! $path || ! Storage::disk(self::DISK)->exists($path)) {
            abort(404, 'Berkas lampiran tidak ditemukan.');
        }

        $safeName = $this->sanitizeOriginalName($originalName ?: basename($path));

        return Storage::disk(self::DISK)->download($path, $safeName, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function sanitizeOriginalName(string $name): string
    {
        $base = basename(str_replace('\\', '/', $name));
        $clean = preg_replace('/[^\p{L}\p{N}._\-\s()]/u', '_', $base) ?: 'lampiran';
        $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? 'lampiran');

        return Str::limit($clean !== '' ? $clean : 'lampiran', 200, '');
    }
}
