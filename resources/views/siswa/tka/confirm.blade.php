@extends('layouts.app')
@section('title', 'Konfirmasi Peserta TKA - ' . $paket['judul'])

@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection

@section('page_title', 'Konfirmasi Data Peserta TKA')
@section('page_subtitle', 'Pusat Asesmen Pendidikan (Pusmendik) • Simulasi Resmi')

@section('content')
@include('cbt.feedback')

<div style="max-width:760px;margin:0 auto">
    <div class="card" style="box-shadow:0 4px 20px rgba(0,0,0,0.06);border-radius:16px;overflow:hidden">
        {{-- Header Card --}}
        <div style="background:linear-gradient(135deg, var(--navy-dark) 0%, var(--navy) 100%);color:#fff;padding:26px 30px">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                <span class="tag tag-gold" style="font-weight:700">SIMULASI TKA PUSMENDIK</span>
                <span style="font-size:12.5px;opacity:0.85">{{ $paket['kode'] }}</span>
            </div>
            <h2 style="font-size:22px;font-weight:800;letter-spacing:-0.4px;margin:0 0 6px">{{ $paket['judul'] }}</h2>
            <p style="font-size:13px;opacity:0.85;margin:0">
                Silakan periksa kembali data peserta dan masukkan token ujian untuk memulai simulasi.
            </p>
        </div>

        {{-- Token Banner --}}
        <div style="background:#FAF5FF;border-bottom:1px solid #E9D5FF;padding:16px 30px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div>
                <span style="font-size:11.5px;font-weight:700;color:var(--violet);text-transform:uppercase;letter-spacing:0.5px">Token Ujian Simulasi</span>
                <div style="font-size:12px;color:var(--muted)">Salin kode token di samping ke formulir di bawah</div>
            </div>
            <div style="font-family:monospace;font-size:20px;font-weight:900;letter-spacing:2px;color:var(--violet);background:#fff;border:1.5px dashed var(--violet);padding:6px 16px;border-radius:8px">
                {{ $token }}
            </div>
        </div>

        {{-- Confirmation Form --}}
        <div class="card-body" style="padding:28px 30px">
            <form method="POST" action="{{ route('siswa.tka-simulasi.start', $paket['id']) }}">
                @csrf

                <div class="table-wrapper" style="margin-bottom:24px">
                    <table style="width:100%">
                        <tbody>
                            <tr>
                                <td style="width:35%;font-weight:600;color:var(--muted)">Nama Lengkap Peserta</td>
                                <td style="font-weight:700;color:var(--text);font-size:14px">
                                    {{ $siswa?->nama_peserta_didik ?? auth()->user()->nama_lengkap }}
                                </td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;color:var(--muted)">NISN / NIS Peserta</td>
                                <td style="color:var(--text);font-weight:600">
                                    {{ $siswa?->nisn ?? '-' }} / {{ $siswa?->nis ?? auth()->user()->nama_pengguna }}
                                </td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;color:var(--muted)">Rombongan Belajar (Kelas)</td>
                                <td>
                                    <span class="tag tag-primary">Kelas {{ $siswa?->kelas ?? 'Siswa' }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;color:var(--muted)">Subtes Asesmen</td>
                                <td style="font-weight:700;color:var(--navy)">
                                    {{ $paket['kategori'] }} ({{ count($paket['soal']) }} Butir Soal Wacana)
                                </td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;color:var(--muted)">Alokasi Waktu Pengerjaan</td>
                                <td>
                                    <span class="tag tag-gold">{{ $paket['durasi'] }} Menit</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Input Token Verification --}}
                <div style="background:#FAFBFD;border:1px solid var(--line);border-radius:10px;padding:18px 20px;margin-bottom:24px">
                    <label for="token" class="form-label" style="font-size:13.5px;font-weight:700;display:flex;align-items:center;gap:8px">
                        <i class="fas fa-key" style="color:var(--violet)"></i> Masukkan Kode Token Ujian <span style="color:var(--bad)">*</span>
                    </label>
                    <div style="display:flex;gap:10px;margin-top:6px;max-width:320px">
                        <input id="token" 
                               type="text" 
                               name="token" 
                               class="form-control" 
                               style="font-family:monospace;font-weight:800;font-size:16px;letter-spacing:1.5px;text-transform:uppercase" 
                               required 
                               placeholder="{{ $token }}" 
                               value="{{ old('token', $token) }}">
                    </div>
                    <small style="color:var(--muted);font-size:11.5px;margin-top:6px;display:block">
                        Format token otomatis terisi sesuai sesi simulasi aktif Anda.
                    </small>
                </div>

                {{-- Bottom Actions --}}
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;border-top:1px solid var(--line);padding-top:20px;flex-wrap:wrap">
                    <a href="{{ route('siswa.tka-simulasi.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Pilih Subtes Lain
                    </a>

                    <button type="submit" class="btn btn-primary" style="padding:10px 30px;font-size:14px;box-shadow:0 4px 12px rgba(124,58,237,0.25)">
                        <i class="fas fa-play"></i> Mulai Ujian Simulasi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
