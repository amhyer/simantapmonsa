<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Siswa;
use App\Models\OrangTuaSiswa;
use App\Models\Semester;
use App\Models\Ptk;
use Illuminate\Support\Facades\Log;

class ImportDapodikJob implements ShouldQueue
{
    use Queueable;

    protected $data;
    protected $sekolahNpsn;
    protected $tahunAjaran;
    protected $semester;

    public function __construct($data, $sekolahNpsn, $tahunAjaran, $semester)
    {
        $this->data = $data;
        $this->sekolahNpsn = $sekolahNpsn;
        $this->tahunAjaran = $tahunAjaran;
        $this->semester = $semester;
    }

    public function handle(): void
    {
        $berhasil = 0;
        $gagal = 0;
        $diperbarui = 0;
        $dilewati = 0;
        $errors = [];

        $semesterObj = Semester::where('tahun_ajaran', $this->tahunAjaran)
            ->where('semester', $this->semester)
            ->first();

        if (!$semesterObj) {
            Log::error("Import Dapodik: Semester {$this->tahunAjaran}/{$this->semester} tidak ditemukan");
            return;
        }

        foreach ($this->data['siswa'] as $index => $s) {
            try {
                $nisn = $s['nisn'] ?? null;
                $nama = $s['nama'] ?? null;
                $jenis_kelamin = $s['jenis_kelamin'] ?? 'L';
                $tmp_lahir = $s['tmp_lahir'] ?? null;
                $tgl_lahir = $s['tgl_lahir'] ?? null;
                $agama = $s['agama'] ?? 'Islam';
                $alamat = $s['alamat'] ?? null;
                $kd_dusun = $s['kd_dusun'] ?? null;
                $kd_desa = $s['kd_desa'] ?? null;
                $kd_kecamatan = $s['kd_kecamatan'] ?? null;
                $kd_kabupaten = $s['kd_kabupaten'] ?? null;
                $kd_provinsi = $s['kd_provinsi'] ?? null;
                $kode_pos = $s['kode_pos'] ?? null;
                $telepon = $s['telepon'] ?? null;
                $nama_orang_tua = $s['nama_orang_tua'] ?? null;
                $no_telepon = $s['no_telepon'] ?? null;
                $email = $s['email'] ?? null;
                $no_kk = $s['no_kk'] ?? null;
                $agama_ortu = $s['agama_ortu'] ?? 'Islam';
                $pendidikan_ortu = $s['pendidikan_ortu'] ?? null;
                $pekerjaan_ortu = $s['pekerjaan_ortu'] ?? null;
                $no_telepon_ortu = $s['no_telepon_ortu'] ?? null;
                $status_hidup = $s['status_hidup'] ?? '1';
                $anak_ke = $s['anak_ke'] ?? 1;
                $jumlah_saudara = $s['jumlah_saudara'] ?? 0;
                $tinggi_badan = $s['tinggi_badan'] ?? null;
                $berat_badan = $s['berat_badan'] ?? null;
                $gol_darah = $s['gol_darah'] ?? 'O';
                $foto = $s['foto'] ?? null;

                $existing = null;
                if ($s['peserta_didik_id'] ?? $s['dapodik_id']) {
                    $dapodikId = $s['peserta_didik_id'] ?? $s['dapodik_id'];
                    $existing = Siswa::where('dapodik_id', $dapodikId)
                        ->where('semester_id', $semesterObj->id)
                        ->first();
                }
                if (!$existing && $nisn) {
                    $existing = Siswa::where('nisn', $nisn)
                        ->where('semester_id', $semesterObj->id)
                        ->first();
                }

                $rombelId = null;
                $guruId = null;
                $rombelName = $s['nama_rombel'] ?? $s['rombel'] ?? $s['kelas'] ?? null;
                if ($rombelName) {
                    $rombel = Rombel::where('nama_rombel', $rombelName)
                        ->where('semester_id', $semesterObj->id)
                        ->first();
                    if ($rombel) {
                        $rombelId = $rombel->id;
                    }
                }

                $guruId = null;
                if (isset($s['guru_id'])) {
                    $guruId = $s['guru_id'];
                }

                if ($existing) {
                    $existing->update([
                        'nama_peserta_didik' => $nama,
                        'jenis_kelamin' => $jenis_kelamin,
                        'tmp_lahir' => $tmp_lahir,
                        'tanggal_lahir' => $tgl_lahir,
                        'agama' => $agama,
                        'alamat' => $alamat,
                        'kd_dusun' => $kd_dusun,
                        'kd_desa' => $kd_desa,
                        'kd_kecamatan' => $kd_kecamatan,
                        'kd_kabupaten' => $kd_kabupaten,
                        'kd_provinsi' => $kd_provinsi,
                        'kode_pos' => $kode_pos,
                        'telepon' => $telepon,
                        'nama_orang_tua' => $nama_orang_tua,
                        'no_kk' => $no_kk,
                        'agama_orang_tua' => $agama_ortu,
                        'pendidikan_orang_tua' => $pendidikan_ortu,
                        'pekerjaan_orang_tua' => $pekerjaan_ortu,
                        'no_telepon_orang_tua' => $no_telepon_ortu,
                        'status_hidup' => $status_hidup,
                        'anak_ke' => $anak_ke,
                        'jumlah_saudara' => $jumlah_saudara,
                        'tinggi_badan' => $tinggi_badan,
                        'berat_badan' => $berat_badan,
                        'golongan_darah' => $gol_darah,
                        'foto' => $foto,
                        'rombel_id' => $rombelId,
                        'guru_id' => $guruId,
                    ]);
                    $diperbarui++;
                } else {
                    $siswaData = [
                        'uuid' => Str::uuid(),
                        'dapodik_id' => $s['peserta_didik_id'] ?? $s['dapodik_id'] ?? null,
                        'guru_id' => $guruId,
                        'nama_guru' => null,
                        'semester_id' => $semesterObj->id,
                        'nisn' => $nisn,
                        'nama_peserta_didik' => $nama,
                        'jenis_kelamin' => $jenis_kelamin,
                        'tmp_lahir' => $tmp_lahir,
                        'tanggal_lahir' => $tgl_lahir,
                        'agama' => $agama,
                        'alamat' => $alamat,
                        'kd_dusun' => $kd_dusun,
                        'kd_desa' => $kd_desa,
                        'kd_kecamatan' => $kd_kecamatan,
                        'kd_kabupaten' => $kd_kabupaten,
                        'kd_provinsi' => $kd_provinsi,
                        'kode_pos' => $kode_pos,
                        'telepon' => $telepon,
                        'nama_orang_tua' => $nama_orang_tua,
                        'no_kk' => $no_kk,
                        'agama_orang_tua' => $agama_ortu,
                        'pendidikan_orang_tua' => $pendidikan_ortu,
                        'pekerjaan_orang_tua' => $pekerjaan_ortu,
                        'no_telepon_orang_tua' => $no_telepon_ortu,
                        'status_hidup' => $status_hidup,
                        'anak_ke' => $anak_ke,
                        'jumlah_saudara' => $jumlah_saudara,
                        'tinggi_badan' => $tinggi_badan,
                        'berat_badan' => $berat_badan,
                        'golongan_darah' => $gol_darah,
                        'foto' => $foto,
                        'rombel_id' => $rombelId,
                    ];
                    Siswa::create($siswaData);
                    $berhasil++;
                }

                // Save orang tua
                if ($nisn) {
                    $orangTua = OrangTuaSiswa::where('siswa_id', fn($q) => $q->where('nisn', $nisn)->first()?->id)
                        ->first();
                    if (!$orangTua) {
                        $orangTua = new OrangTuaSiswa();
                    }
                    $orangTua->siswa_id = Siswa::where('nisn', $nisn)->first()?->id ?? Siswa::latest('id')->first()?->id;
                    $orangTua->tipe = 'ayah';
                    $orangTua->nama = $nama_orang_tua;
                    $orangTua->nik = $no_kk;
                    $orangTua->tahun_lahir = null;
                    $orangTua->pendidikan = pendidikan_ortu;
                    $orangTua->pekerjaan = pekerjaan_ortu;
                    $orangTua->no_telepon = $no_telepon_ortu;
                    $orangTua->email = $email;
                    $orangTua->alamat = $alamat;
                    $orangTua->status_hidup = $status_hidup === '1';
                    $orangTua->save();
                }

            } catch (\Exception $e) {
                $gagal++;
                $errorMsg = "Gagal import baris " . ($index + 1) . ": " . $e->getMessage();
                Log::error($errorMsg);
            }
        }

        // Catat aktivitas
        try {
            $guru = auth()->user();
            Aktivitas::create([
                'uuid' => Str::uuid(),
                'guru_id' => $guru?->id,
                'nama_guru' => $guru?->nama_lengkap ?? 'Dapodik Bridge',
                'jenis' => 'pengaturan',
                'judul' => 'Import Dapodik',
                'deskripsi' => sprintf(
                    'Sekolah %s: %d baru, %d diperbarui, %d dilewati, %d gagal',
                    $this->sekolahNpsn, $berhasil, $diperbarui, $dilewati, $gagal
                ),
            ]);
        } catch (\Exception $e) {
            // Aktivitas gagal tapi jangan ganggu import
            Log::warning("Gagal catat aktivitas import: " . $e->getMessage());
        }
    }
}
