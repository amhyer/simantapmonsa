<?php

namespace App\Services;

use App\Models\Aktivitas;
use Illuminate\Support\Str;

class AktivitasService
{
    public static function log(
        string $jenis,
        ?string $judul = null,
        ?string $deskripsi = null,
        ?string $tabel = null,
        $recordId = null,
        $dataLama = null,
        $dataBaru = null,
    ): ?Aktivitas {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        return Aktivitas::create([
            'uuid' => Str::uuid(),
            'guru_id' => $user->id,
            'nama_guru' => $user->nama_lengkap,
            'jenis' => $jenis,
            'judul' => $judul,
            'deskripsi' => $deskripsi,
            'tabel_terkait' => $tabel,
            'record_id' => $recordId,
            'data_lama' => $dataLama,
            'data_baru' => $dataBaru,
        ]);
    }

    public static function login(?string $detail = null): ?Aktivitas
    {
        return self::log(
            jenis: 'login',
            judul: 'Login',
            deskripsi: $detail ?? 'Berhasil login ke sistem',
            tabel: 'users',
            recordId: auth()->id(),
        );
    }

    public static function tambah(string $judul, ?string $deskripsi = null, ?string $tabel = null, $recordId = null, $dataBaru = null): ?Aktivitas
    {
        return self::log('tambah', $judul, $deskripsi, $tabel, $recordId, null, $dataBaru);
    }

    public static function ubah(string $judul, ?string $deskripsi = null, ?string $tabel = null, $recordId = null, $dataLama = null, $dataBaru = null): ?Aktivitas
    {
        return self::log('ubah', $judul, $deskripsi, $tabel, $recordId, $dataLama, $dataBaru);
    }

    public static function hapus(string $judul, ?string $deskripsi = null, ?string $tabel = null, $recordId = null, $dataLama = null): ?Aktivitas
    {
        return self::log('hapus', $judul, $deskripsi, $tabel, $recordId, $dataLama, null);
    }

    public static function lihat(string $judul, ?string $deskripsi = null, ?string $tabel = null, $recordId = null): ?Aktivitas
    {
        return self::log('lihat', $judul, $deskripsi, $tabel, $recordId);
    }
}
