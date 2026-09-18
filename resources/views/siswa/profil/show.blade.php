@extends('layouts.app')

@section('title', 'Profil Saya - SIMANTAP')

@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection

@section('page_title', 'Profil Saya')
@section('page_subtitle', 'Data lengkap dari Dapodik')

@section('content')
    @if(session('success'))
        <div class="note note-ok" style="margin-bottom:16px">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif

    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <h3><i class="fas fa-user" style="margin-right:8px;color:var(--navy)"></i>Data Diri</h3>
        </div>
        <div class="card-body">
            <div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap">
                <div style="text-align:center;flex:0 0 140px">
                    @if($siswa->foto)
                        <img src="{{ asset('storage/' . $siswa->foto) }}" alt="Foto" style="width:120px;height:120px;border-radius:50%;object-fit:cover;border:3px solid var(--navy)">
                    @else
                        <div style="width:120px;height:120px;border-radius:50%;background:var(--navy);color:#fff;display:flex;align-items:center;justify-content:center;font-size:42px;margin:0 auto;font-weight:700">
                            {{ substr($siswa->nama_peserta_didik, 0, 1) }}
                        </div>
                    @endif
                </div>
                <div style="flex:1;min-width:0">
                    <div style="font-size:18px;font-weight:700;margin-bottom:4px">{{ $siswa->nama_peserta_didik }}</div>
                    <div style="font-size:13px;color:var(--muted);margin-bottom:12px">{{ $siswa->kelas }} &middot; {{ $siswa->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }}</div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:8px;font-size:13px">
                        <div><span style="color:var(--muted)">NIS:</span> <b>{{ $siswa->nis ?: '-' }}</b></div>
                        <div><span style="color:var(--muted)">NISN:</span> <b>{{ $siswa->nisn ?: '-' }}</b></div>
                        <div><span style="color:var(--muted)">NIK:</span> <b>{{ $siswa->nik ?: '-' }}</b></div>
                        <div><span style="color:var(--muted)">No. KK:</span> <b>{{ $siswa->no_kk ?: '-' }}</b></div>
                        <div><span style="color:var(--muted)">No. Akta Lahir:</span> <b>{{ $siswa->no_akta_lahir ?: '-' }}</b></div>
                        <div><span style="color:var(--muted)">Agama:</span> <b>{{ $siswa->agama ?: '-' }}</b></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-2" style="margin-bottom:20px">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-birthday-cake" style="margin-right:8px;color:var(--gold)"></i>Kelahiran</h3>
            </div>
            <div class="card-body" style="font-size:13px">
                <p><span style="color:var(--muted);display:inline-block;width:120px">Tempat Lahir</span> <b>{{ $siswa->tempat_lahir ?: '-' }}</b></p>
                <p><span style="color:var(--muted);display:inline-block;width:120px">Tanggal Lahir</span> <b>{{ $siswa->tanggal_lahir ? \Carbon\Carbon::parse($siswa->tanggal_lahir)->translatedFormat('d M Y') : '-' }}</b></p>
                <p><span style="color:var(--muted);display:inline-block;width:120px">Jenis Kelamin</span> <b>{{ $siswa->jenis_kelamin }}</b></p>
                <p><span style="color:var(--muted);display:inline-block;width:120px">Gol. Darah</span> <b>{{ $siswa->golongan_darah ?: '-' }}</b></p>
                <p><span style="color:var(--muted);display:inline-block;width:120px">Anak ke-</span> <b>{{ $siswa->anak_ke ?: '-' }}</b></p>
                <p><span style="color:var(--muted);display:inline-block;width:120px">Jml. Saudara</span> <b>{{ $siswa->jumlah_saudara ?: '-' }}</b></p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-ruler-vertical" style="margin-right:8px;color:var(--ok)"></i>Fisik</h3>
            </div>
            <div class="card-body" style="font-size:13px">
                <p><span style="color:var(--muted);display:inline-block;width:120px">Tinggi Badan</span> <b>{{ $siswa->tinggi_badan ? $siswa->tinggi_badan . ' cm' : '-' }}</b></p>
                <p><span style="color:var(--muted);display:inline-block;width:120px">Berat Badan</span> <b>{{ $siswa->berat_badan ? $siswa->berat_badan . ' kg' : '-' }}</b></p>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <h3><i class="fas fa-map-marker-alt" style="margin-right:8px;color:var(--bad)"></i>Alamat</h3>
        </div>
        <div class="card-body" style="font-size:13px">
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:8px">
                <p><span style="color:var(--muted);display:inline-block;width:100px">Alamat</span> <b>{{ $siswa->alamat ?: '-' }}</b></p>
                <p><span style="color:var(--muted);display:inline-block;width:100px">RT</span> <b>{{ $siswa->rt ?: '-' }}</b></p>
                <p><span style="color:var(--muted);display:inline-block;width:100px">RW</span> <b>{{ $siswa->rw ?: '-' }}</b></p>
                <p><span style="color:var(--muted);display:inline-block;width:100px">Dusun</span> <b>{{ $siswa->dusun ?: '-' }}</b></p>
                <p><span style="color:var(--muted);display:inline-block;width:100px">Desa/Kel</span> <b>{{ $siswa->desa ?: '-' }}</b></p>
                <p><span style="color:var(--muted);display:inline-block;width:100px">Kecamatan</span> <b>{{ $siswa->kecamatan ?: '-' }}</b></p>
                <p><span style="color:var(--muted);display:inline-block;width:100px">Kabupaten</span> <b>{{ $siswa->kabupaten ?: '-' }}</b></p>
                <p><span style="color:var(--muted);display:inline-block;width:100px">Provinsi</span> <b>{{ $siswa->provinsi ?: '-' }}</b></p>
                <p><span style="color:var(--muted);display:inline-block;width:100px">Kode Pos</span> <b>{{ $siswa->kode_pos ?: '-' }}</b></p>
            </div>
        </div>
    </div>

    <div class="grid grid-2" style="margin-bottom:20px">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-phone" style="margin-right:8px;color:var(--navy)"></i>Kontak</h3>
            </div>
            <div class="card-body" style="font-size:13px">
                <p><span style="color:var(--muted);display:inline-block;width:100px">Telepon</span> <b>{{ $siswa->telepon ?: '-' }}</b></p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-graduation-cap" style="margin-right:8px;color:var(--gold)"></i>Pendidikan</h3>
            </div>
            <div class="card-body" style="font-size:13px">
                <p><span style="color:var(--muted);display:inline-block;width:120px">Status Siswa</span> <b>{{ $siswa->status_siswa ?: '-' }}</b></p>
                <p><span style="color:var(--muted);display:inline-block;width:120px">Penerima KIP</span> <b>{{ $siswa->penerima_kip ? 'Ya' : 'Tidak' }}</b></p>
                <p><span style="color:var(--muted);display:inline-block;width:120px">No. KIP</span> <b>{{ $siswa->no_kip ?: '-' }}</b></p>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <h3><i class="fas fa-users" style="margin-right:8px;color:var(--ok)"></i>Orang Tua / Wali</h3>
        </div>
        <div class="card-body" style="font-size:13px">
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:8px">
                <p><span style="color:var(--muted);display:inline-block;width:120px">Nama Orang Tua</span> <b>{{ $siswa->nama_orang_tua ?: '-' }}</b></p>
            </div>
        </div>
    </div>

    <div style="text-align:center;margin-top:8px">
        <a href="{{ route('siswa.profil.edit') }}" class="btn" style="background:var(--navy);color:#fff">
            <i class="fas fa-edit"></i> Edit Profil
        </a>
        <a href="{{ route('siswa.dashboard') }}" class="btn btn-ghost">Kembali ke Beranda</a>
    </div>
@endsection
