<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->string('key_hash')->nullable()->after('key');
        });

        // Migrate existing plaintext keys to hashes
        $apiKeys = DB::table('api_keys')->get();
        foreach ($apiKeys as $apiKey) {
            DB::table('api_keys')
                ->where('id', $apiKey->id)
                ->update(['key_hash' => hash('sha256', $apiKey->key)]);
        }
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn('key_hash');
        });
    }
};
