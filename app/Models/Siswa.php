<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Siswa extends Model
{
    use SoftDeletes;
    protected $table = 'siswa';
    protected $hidden = ['rekaman', 'nik', 'alamat'];
    protected $fillable = [
        'uuid',
        'dapodik_id',
        'guru_id',
        'rombel_id',
        'nama_guru',
        'semester_id',
        'nis',
        'nisn',
        'nomor_ijazah',
        'nama_peserta_didik',
        'kelas',
        'jenis_kelamin',
        'nama_orang_tua',
        'aktif',
        'status_siswa',
        'rekaman',
        'nik',
        'no_kk',
        'no_akta_lahir',
        'agama',
        'tempat_lahir',
        'tanggal_lahir',
        'alamat',
        'rt',
        'rw',
        'dusun',
        'desa',
        'kecamatan',
        'kabupaten',
        'provinsi',
        'kode_pos',
        'telepon',
        'anak_ke',
        'jumlah_saudara',
        'tinggi_badan',
        'berat_badan',
        'golongan_darah',
        'foto',
        'penerima_kip',
        'no_kip',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'rekaman' => 'array',
            'tanggal_lahir' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (!$model->uuid) $model->uuid = Str::uuid();
        });
    }

    protected static function booted(): void
    {
        // Bust cache foto layout saat foto berubah agar sidebar/topbar langsung update.
        $flush = function (Siswa $siswa) {
            $ids = User::where('nama_pengguna', $siswa->nis)->pluck('id');
            try {
                $linked = User::whereJsonContains('terhubung_dengan', $siswa->id)->pluck('id');
                $ids = $ids->merge($linked);
            } catch (\Throwable) {
                // Driver DB tanpa dukungan JSON contains (abaikan relasi ortu di sini).
            }
            foreach ($ids as $id) {
                Cache::forget('layout-foto-' . $id);
            }
        };

        static::saved(function (Siswa $siswa) use ($flush) {
            if ($siswa->wasChanged('foto') || $siswa->wasRecentlyCreated) {
                $flush($siswa);
            }
        });
        static::deleted($flush);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function guru()
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function rombel()
    {
        return $this->belongsTo(Rombel::class, 'rombel_id');
    }

    public function nilai()
    {
        return $this->hasMany(Nilai::class, 'siswa_id');
    }

    public function kehadiran()
    {
        return $this->hasMany(Kehadiran::class, 'siswa_id');
    }

    public function hasilKuis()
    {
        return $this->hasMany(HasilKuis::class, 'siswa_id');
    }

    public function catatan()
    {
        return $this->hasMany(Catatan::class, 'siswa_id');
    }

    public function dimensi()
    {
        return $this->hasMany(Dimensi::class, 'siswa_id');
    }

    public function kebiasaan()
    {
        return $this->hasMany(Kebiasaan::class, 'siswa_id');
    }

    public function orangTua()
    {
        return $this->hasMany(OrangTuaSiswa::class, 'siswa_id');
    }

    public function nilaiErapot()
    {
        return $this->hasMany(NilaiErapot::class, 'siswa_id');
    }

    public function transkripNilai()
    {
        return $this->hasMany(TranskripNilai::class, 'siswa_id');
    }
}
