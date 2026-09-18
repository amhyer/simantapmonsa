<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;

/**
 * Middleware untuk autentikasi API key.
 *
 * Digunakan oleh Dapodik Bridge untuk mengirim data sync.
 * Header: X-API-Key: <your-key>
 *
 * Flow:
 * 1. Validasi key ada di database
 * 2. Cek isValid() (active + tidak expired)
 * 3. Cek ability jika diperlukan
 * 4. Login user terkait ke session
 * 5. Attach key object ke request sebagai _api_key
 *
 * Catatan: Middleware ini mengembalikan JSON response (bukan redirect)
 * karena dikhususkan untuk API endpoints.
 */
class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next, ?string $ability = null)
    {
        $apiKey = $request->header('X-API-Key');

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'API Key diperlukan.',
            ], 401);
        }

        // Find key by hash lookup
        $keyHash = hash('sha256', $apiKey);
        $key = ApiKey::where('key_hash', $keyHash)->first();

        // Fallback: try plaintext lookup for legacy keys
        if (!$key) {
            $key = ApiKey::where('key', $apiKey)->first();
        }

        if (!$key || !$key->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'API Key tidak valid atau sudah expired.',
            ], 401);
        }

        if ($ability && !$key->hasAbility($ability)) {
            return response()->json([
                'success' => false,
                'message' => "API Key tidak memiliki akses: {$ability}",
            ], 403);
        }

        $key->update(['last_used_at' => now()]);

        if ($key->user) {
            auth()->login($key->user);
        }
        $request->merge(['_api_key' => $key]);

        return $next($request);
    }
}
