<?php

namespace App\Services\Tka\Kesiapan;

use App\Models\Siswa;
use App\Models\Tka\Kesiapan\TindakLanjut;
use App\Models\Tka\Kesiapan\TindakLanjutRevisi;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Rekomendasi tindak lanjut: disusun dari kesenjangan atau tren menurun yang teramati,
 * SELALU mencantumkan alasan & referensi bukti asal, dapat ditinjau/disunting guru,
 * serta tidak pernah mengubah nilai sumber.
 */
class RekomendasiService
{
    /**
     * @param  array<string, mixed>  $profil  keluaran KesiapanService::profil()
     * @return int jumlah rekomendasi baru
     */
    public function susun(Siswa $siswa, array $profil, User $guru, string $tahunAjaran, string $semester): int
    {
        $baru = 0;
        $bukti = collect($profil['bukti']);

        foreach ($profil['indikator'] as $kode => $i) {
            $perluSaran = $i['bukti_status'] === 'cukup' && (
                in_array($i['kecenderungan'], ['perlu_dukungan', 'berkembang'], true)
                || ($i['tren']['arah'] ?? null) === 'menurun'
            );
            if (! $perluSaran) {
                continue;
            }

            $acuan = $bukti->whereIn('id', $i['bukti_ids'])->sortBy('skor')->take(5)->map(fn ($b) => [
                'id' => $b['id'],
                'sumber' => $b['sumber_label'],
                'judul' => $b['judul'],
                'tanggal' => $b['tanggal'],
                'nilai_asli' => $b['nilai_asli'],
                'skala' => $b['skala'],
                'skor' => $b['skor'],
            ])->values()->all();

            $teks = $this->teks($kode, $i, $acuan);

            $tl = TindakLanjut::query()->firstOrNew([
                'siswa_id' => $siswa->id,
                'tahun_ajaran' => $tahunAjaran,
                'semester' => $semester,
                'indikator_kode' => $kode,
            ]);

            if (! $tl->exists) {
                $tl->fill([
                    'tenant_id' => $siswa->tenant_id,
                    'rekomendasi' => $teks,
                    'bukti_ref' => $acuan,
                    'status' => 'disarankan',
                    'dibuat_oleh' => $guru->id,
                    'diubah_oleh' => $guru->id,
                ])->save();
                $baru++;
            } elseif (! $tl->disunting_guru && $tl->status === 'disarankan') {
                $tl->update(['rekomendasi' => $teks, 'bukti_ref' => $acuan]);
            }
        }

        return $baru;
    }

    /**
     * @param  array<string, mixed>  $i
     * @param  array<int, array<string, mixed>>  $acuan
     */
    private function teks(string $kode, array $i, array $acuan): string
    {
        $katalog = (array) config("tka_kesiapan.rekomendasi.$kode", []);
        $dasar = $katalog[0] ?? 'Tinjau kembali capaian pada indikator ini bersama siswa dan rencanakan latihan terarah; evaluasi setelah asesmen berikutnya.';
        $nama = (string) config("tka_kesiapan.indikator.$kode.nama", $kode);

        $pemicu = match (true) {
            $i['kecenderungan'] === 'perlu_dukungan' => 'kecenderungan berada di kategori perlu dukungan',
            ($i['tren']['arah'] ?? null) === 'menurun' => 'terlihat penurunan skor tervalidasi antar-periode',
            default => 'indikator masih berada pada tahap berkembang',
        };

        $contohBukti = isset($acuan[0])
            ? sprintf(' (contoh bukti: %s — %s, %s)', $acuan[0]['judul'], $acuan[0]['nilai_asli'], $acuan[0]['tanggal'])
            : '';

        return sprintf(
            'Alasan: %s (%s; estimasi internal %.1f dari %d bukti%s). Saran tindak lanjut: %s',
            $nama,
            $pemicu,
            (float) ($i['estimasi_indeks_internal'] ?? $i['skor_rata']),
            (int) $i['jumlah_bukti'],
            $contohBukti,
            $dasar
        );
    }

    /**
     * @param  array{status?: string, rekomendasi?: string|null, catatan_guru?: string|null, alasan?: string|null}  $data
     */
    public function perbarui(TindakLanjut $tl, User $guru, array $data): TindakLanjut
    {
        return DB::transaction(function () use ($tl, $guru, $data) {
            $statusBaru = $data['status'] ?? $tl->status;
            $teksBaru = array_key_exists('rekomendasi', $data) && $data['rekomendasi'] !== null && trim($data['rekomendasi']) !== ''
                ? trim($data['rekomendasi']) : $tl->rekomendasi;
            $catatan = array_key_exists('catatan_guru', $data) ? $data['catatan_guru'] : $tl->catatan_guru;
            $alasan = isset($data['alasan']) && trim((string) $data['alasan']) !== '' ? trim((string) $data['alasan']) : null;

            TindakLanjutRevisi::create([
                'tenant_id' => $tl->tenant_id,
                'tindak_lanjut_id' => $tl->id,
                'user_id' => $guru->id,
                'status_sebelum' => $tl->status,
                'status_sesudah' => $statusBaru,
                'rekomendasi_sebelum' => $tl->rekomendasi,
                'rekomendasi_sesudah' => $teksBaru,
                'catatan' => $catatan,
                'alasan' => $alasan,
            ]);

            $tl->update([
                'status' => $statusBaru,
                'rekomendasi' => $teksBaru,
                'catatan_guru' => $catatan,
                'disunting_guru' => $tl->disunting_guru || $teksBaru !== $tl->rekomendasi,
                'diubah_oleh' => $guru->id,
            ]);

            return $tl->refresh();
        });
    }
}
