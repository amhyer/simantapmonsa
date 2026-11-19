<?php

namespace App\Services\Media;

use App\Http\Controllers\Media\SiswaFotoController;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SiswaFotoStorage
{
    private const MIME_TO_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public const MAX_IMAGE_BYTES = 2 * 1024 * 1024;

    public function storeUploadedFile(UploadedFile $file): ?string
    {
        $bytes = @file_get_contents($file->getRealPath());
        if ($bytes === false) {
            return null;
        }

        return $this->storeRawBytes($bytes);
    }

    public function storeRawBytes(string $bytes): ?string
    {
        $length = strlen($bytes);
        if ($length === 0 || $length > self::MAX_IMAGE_BYTES) {
            return null;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->buffer($bytes);
        $ext = self::MIME_TO_EXT[$mime] ?? null;
        if ($ext === null) {
            return null;
        }

        $imageInfo = @getimagesizefromstring($bytes);
        if ($imageInfo === false || ($imageInfo[0] ?? 0) < 1 || ($imageInfo[1] ?? 0) < 1) {
            return null;
        }

        $targetPath = 'foto-siswa/'.Str::random(32).'.'.$ext;
        Storage::disk('local')->put($targetPath, $bytes);

        return $targetPath;
    }

    public function deleteFromAllDisks(?string $path): void
    {
        $clean = trim((string) ($path ?? ''));
        if ($clean === '' || preg_match(SiswaFotoController::PATH_REGEX, $clean) !== 1) {
            return;
        }

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($clean)) {
                Storage::disk($disk)->delete($clean);
            }
        }
    }
}
