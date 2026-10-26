<?php

namespace Tests\Feature;

use App\Models\NilaiErapot;
use App\Models\NilaiErapotAudit;
use App\Models\Siswa;
use App\Models\User;
use App\Services\NilaiErapotAuditService;
use App\Support\BackupTables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NilaiErapotAuditTest extends TestCase
{
    use RefreshDatabase;

    private function buatNilai(): NilaiErapot
    {
        $guru = User::factory()->guru()->create(['aktif' => true]);
        $siswa = Siswa::create([
            'uuid' => (string) Str::uuid(),
            'guru_id' => $guru->id,
            'nama_guru' => $guru->nama_lengkap,
            'nis' => 'AUD-' . $guru->id,
            'nama_peserta_didik' => 'Siswa Audit',
            'kelas' => '6.A',
            'jenis_kelamin' => 'L',
        ]);

        return NilaiErapot::create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'mata_pelajaran' => 'Matematika',
            'kelas' => '6.A',
            'semester' => 'Ganjil',
            'tahun_ajaran' => '2025/2026',
            'nilai_akhir' => 80,
            'predikat' => 'B',
        ]);
    }

    public function test_perubahan_nilai_membentuk_rantai_hash(): void
    {
        $nilai = $this->buatNilai();

        $pertama = NilaiErapotAudit::firstOrFail();
        $this->assertSame('dibuat', $pertama->aksi);
        $this->assertNull($pertama->prev_hash);
        $this->assertSame($nilai->id, (int) $pertama->nilai_erapor_id);
        $this->assertSame(64, strlen($pertama->hash));

        $nilai->update(['nilai_akhir' => 91, 'predikat' => 'A']);

        $kedua = NilaiErapotAudit::orderByDesc('id')->firstOrFail();
        $this->assertSame('diperbarui', $kedua->aksi);
        $this->assertSame($pertama->hash, $kedua->prev_hash);
        $this->assertNotSame($pertama->hash, $kedua->hash);
        $this->assertSame('91.00', (string) $kedua->snapshot['nilai_akhir']);

        $hasil = app(NilaiErapotAuditService::class)->verifikasi($nilai->id);
        $this->assertTrue($hasil['ok']);
        $this->assertSame(2, $hasil['diperiksa']);
    }

    public function test_perubahan_langsung_snapshot_terdeteksi(): void
    {
        $nilai = $this->buatNilai();
        $audit = NilaiErapotAudit::firstOrFail();

        $snapshot = $audit->snapshot;
        $snapshot['nilai_akhir'] = '100.00';
        DB::table('nilai_erapor_audit_logs')->where('id', $audit->id)->update([
            'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
        ]);

        $hasil = app(NilaiErapotAuditService::class)->verifikasi($nilai->id);

        $this->assertFalse($hasil['ok']);
        $this->assertSame('hash_tidak_cocok', $hasil['masalah'][0]['jenis']);
    }

    public function test_perintah_verifikasi_gagal_saat_rantai_rusak(): void
    {
        $nilai = $this->buatNilai();
        $audit = NilaiErapotAudit::firstOrFail();
        $audit->forceFill(['prev_hash' => str_repeat('0', 64)])->save();

        $this->artisan('nilai:audit:verifikasi', ['--nilai' => $nilai->id])->assertFailed();
    }

    public function test_penghapusan_tetap_meninggalkan_audit_dan_tabel_dicadangkan(): void
    {
        $nilai = $this->buatNilai();
        $id = $nilai->id;
        $nilai->delete();

        $this->assertDatabaseMissing('nilai_erapor', ['id' => $id]);
        $this->assertDatabaseHas('nilai_erapor_audit_logs', [
            'nilai_erapor_id' => $id,
            'aksi' => 'dihapus',
        ]);
        $this->assertContains('nilai_erapor_audit_logs', BackupTables::tabel());
        $this->assertTrue(app(NilaiErapotAuditService::class)->verifikasi($id)['ok']);
    }
}
