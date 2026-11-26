<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PegawaiFotoController extends Controller
{
    public const PATH_REGEX = '/^(foto-pegawai|foto-user|pegawai)\/[A-Za-z0-9._-]+\.(jpe?g|png|webp)$/i';

    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function show(Request $request, User $user): Response
    {
        Gate::forUser($request->user())->authorize('view-pegawai-foto', $user);

        $path = trim((string) ($user->foto ?? ''));
        if ($path === '' || str_contains($path, '..') || preg_match(self::PATH_REGEX, $path) !== 1) {
            abort(404);
        }

        $bytes = null;
        if (Storage::disk('local')->exists($path)) {
            $bytes = Storage::disk('local')->get($path);
        } elseif (config('simantap.foto_fallback_public', false) && Storage::disk('public')->exists($path)) {
            $bytes = Storage::disk('public')->get($path);
        }

        if ($bytes === null || $bytes === '') {
            abort(404);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = (string) $finfo->buffer($bytes);
        if (! in_array($detectedMime, self::ALLOWED_MIMES, true)) {
            abort(404);
        }

        $response = response($bytes, 200);
        $response->headers->set('Content-Type', $detectedMime);
        $response->headers->set('Cache-Control', 'private, max-age=300');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Disposition', 'inline');

        return $response;
    }
}
