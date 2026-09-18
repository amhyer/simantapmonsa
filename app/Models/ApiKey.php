<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $table = 'api_keys';
    protected $fillable = [
        'user_id', 'name', 'key', 'key_hash', 'abilities',
        'last_used_at', 'expires_at', 'active',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'active' => 'boolean',
        'abilities' => 'array',
    ];

    protected $hidden = [
        'key', 'key_hash',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a new API key and return the plaintext key (shown once).
     * The hash is stored in key_hash column.
     */
    public static function generate($userId, $description = 'Dapodik Bridge'): array
    {
        $plaintextKey = Str::random(64);
        $keyHash = hash('sha256', $plaintextKey);

        $apiKey = self::create([
            'user_id' => $userId,
            'name' => $description,
            'key' => $plaintextKey,
            'key_hash' => $keyHash,
            'abilities' => ['dapodik:import'],
            'active' => true,
        ]);

        return [
            'model' => $apiKey,
            'plaintext_key' => $plaintextKey,
        ];
    }

    /**
     * Verify a provided key against the stored hash.
     */
    public function verifyKey(string $providedKey): bool
    {
        if (!$this->key_hash) {
            // Fallback for legacy keys without hash
            return hash_equals($this->key, $providedKey);
        }

        return hash_equals($this->key_hash, hash('sha256', $providedKey));
    }

    public function isValid(): bool
    {
        if (!$this->active) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        return true;
    }

    public function hasAbility(string $ability): bool
    {
        return in_array($ability, $this->abilities ?? []);
    }
}
