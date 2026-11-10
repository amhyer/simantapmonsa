<?php

namespace Tests\Feature;

use App\Models\SekolahSettings;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SchoolLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_sekolah_kemendikdasmen_mengembalikan_kepala_sekolah_dan_operator(): void
    {
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
                    ]
                ]
            ], 200),
            'https://referensi.data.kemendikdasmen.go.id/pendidikan/npsn/*' => Http::response(
                '<tr><td>Operator</td><td>:</td><td>AMIRULLAH</td></tr><tr><td>Email</td><td>:</td><td>sdnungmongisidi1@gmail.com</td></tr><tr><td>Telepon</td><td>:</td><td>04118918116</td></tr>',
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
                'operator' => 'AMIRULLAH',
                'email' => 'sdnungmongisidi1@gmail.com',
                'telepon' => '04118918116',
            ],
        ]);
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
