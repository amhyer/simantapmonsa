<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SchoolLookupTokenTest extends TestCase
{
    public function test_school_lookup_returns_friendly_message_when_dapodik_token_is_empty(): void
    {
        Cache::forget('sekolah_npsn_v3_40313912');
        config(['dapodik.token' => null]);

        $response = $this->postJson('/api/sekolah/lookup', ['npsn' => '40313912']);

        $response->assertStatus(503)
            ->assertJson([
                'success' => false,
                'message' => 'Token Dapodik belum dikonfigurasi.',
            ]);
    }

    public function test_school_lookup_uses_configured_dapodik_token(): void
    {
        Cache::forget('sekolah_npsn_v3_40313912');
        config(['dapodik.token' => 'test-configured-token']);
        Http::preventStrayRequests();

        Http::fake([
            'dapo.kemendikdasmen.go.id/*' => Http::response([
                'data' => [[
                    'nama' => 'SDN Uji Token',
                    'bentuk_pendidikan' => 'SD',
                    'status_sekolah' => 'N',
                    'alamat_jalan' => 'Jl. Pendidikan',
                ]],
            ], 200),
            '*' => Http::response('', 404),
        ]);

        $response = $this->postJson('/api/sekolah/lookup', ['npsn' => '40313912']);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nama_sekolah', 'SDN Uji Token');

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-configured-token'));
    }
}
