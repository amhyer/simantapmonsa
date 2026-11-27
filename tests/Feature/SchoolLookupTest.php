<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SchoolLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_sekolah_kemendikdasmen_mengembalikan_kepala_sekolah_dan_operator(): void
    {
        config(['dapodik.token' => 'test-token']);
        Http::preventStrayRequests();
        Http::fake([
            'https://dapo.kemendikdasmen.go.id/api/detail-sekolah*' => Http::response([
                'data' => [
                    [
                        'nama' => 'UPT SPF SD NEGERI UNGGULAN MONGISIDI 1',
                        'bentuk_pendidikan' => 'SD',
                        'status_sekolah' => 'Negeri',
                        'alamat_jalan' => 'Jln. R.W. Monginsidi No.13',
                        'kecamatan' => 'Kec. Makasar',
                        'kabupaten' => 'Kota Makassar',
                        'provinsi' => 'Prov. Sulawesi Selatan',
                        'akreditasi' => 'A',
                        'nama_kepsek' => 'ANDI WINDAYANI',
                    ],
                ],
            ], 200),
            'https://referensi.data.kemendikdasmen.go.id/satpen/detail/*' => Http::response(
                '<div class="detail-row"><div class="detail-label">Nama</div><div class="detail-value">UPT SPF SD NEGERI UNGGULAN MONGISIDI 1</div></div>'
                .'<div class="detail-row"><div class="detail-label">NPSN</div><div class="detail-value">40313912 <a href="#">↗</a></div></div>'
                .'<div class="detail-row"><div class="detail-label">Email</div><div class="detail-value">sdnungmongisidi1@gmail.com</div></div>'
                .'<div class="detail-row"><div class="detail-label">Telepon</div><div class="detail-value">04118918116</div></div>',
                200
            ),
        ]);

        $response = $this->postJson('/api/sekolah/lookup', [
            'npsn' => '40313912',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'npsn' => '40313912',
                'nama_sekolah' => 'UPT SPF SD NEGERI UNGGULAN MONGISIDI 1',
                'jenjang' => 'SD',
                'status' => 'NEGERI',
                'kepala_sekolah' => 'ANDI WINDAYANI',
                'email' => 'sdnungmongisidi1@gmail.com',
                'telepon' => '04118918116',
            ],
        ]);
    }

    public function test_lookup_sekolah_mengembalikan_429_pada_permintaan_ke_11_dalam_satu_menit(): void
    {
        config(['dapodik.token' => null]);

        for ($i = 1; $i <= 10; $i++) {
            $res = $this->postJson('/api/sekolah/lookup', ['npsn' => '40313912']);
            $this->assertNotSame(429, $res->getStatusCode(), "Permintaan ke-{$i} tidak boleh 429.");
        }

        $this->postJson('/api/sekolah/lookup', ['npsn' => '40313912'])->assertStatus(429);
    }

    public function test_lookup_dengan_npsn_tidak_valid_ditolak(): void
    {
        $response = $this->postJson('/api/sekolah/lookup', [
            'npsn' => '123',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
    }
}
