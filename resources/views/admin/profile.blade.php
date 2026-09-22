@extends('layouts.app')

@section('title', 'Profile - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Profile Pengguna')
@section('page_subtitle', 'Detail akun dan ubah password')

@section('content')
    <div class="grid grid-2" style="align-items:start">
        <div class="card">
            <div class="card-body" style="text-align:center;padding:32px 20px">
                <div class="avatar avatar-lg" style="margin:0 auto 12px;background:var(--navy);color:#fff">
                    {{ substr($user->nama_lengkap, 0, 2) }}
                </div>
                <h3 style="font-size:20px;font-weight:700">{{ $user->nama_lengkap }}</h3>
                <div style="color:var(--muted);font-size:13px;margin-top:4px">
                    Username: {{ $user->nama_pengguna }}<br>
                    Level: <span class="tag tag-primary">{{ ucfirst($user->peran) }}</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Detail Pengguna</h3>
            </div>
            <div class="card-body tight">
                <div class="table-wrapper">
                    <table>
                        <tbody>
                            <tr><td style="font-weight:600;width:40%">Nama Lengkap</td><td>: {{ $user->nama_lengkap }}</td></tr>
                            <tr><td style="font-weight:600">Username</td><td>: {{ $user->nama_pengguna }}</td></tr>
                            <tr><td style="font-weight:600">Level</td><td>: {{ ucfirst($user->peran) }}</td></tr>
                            <tr><td style="font-weight:600">Status</td><td>: {!! $user->aktif ? '<span class="tag tag-ok">Aktif</span>' : '<span class="tag tag-bad">Nonaktif</span>' !!}</td></tr>
                            <tr><td style="font-weight:600">Terdaftar Sejak</td><td>: {{ $user->created_at?->translatedFormat('d F Y H:i') ?? '-' }}</td></tr>
                            @if($user->kelas_mata_pelajaran)
                                <tr><td style="font-weight:600">Kelas / Mapel</td><td>: {{ $user->kelas_mata_pelajaran }}</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-2" style="margin-top:16px;align-items:start">
        <div class="card">
            <div class="card-header">
                <h3><x-lucide-lock class="w-5 h-5" /> Ubah Password</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.profile.password') }}" method="POST" style="display:grid;gap:12px;max-width:420px">
                    @csrf
                    @method('PUT')
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Password Saat Ini</label>
                        <input type="password" name="kata_sandi_saat_ini" required
                               style="width:100%;padding:9px 12px;border:1px solid var(--line);border-radius:8px"
                               placeholder="Masukkan password saat ini">
                        @error('kata_sandi_saat_ini')<div style="color:var(--bad);font-size:12px;margin-top:4px">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Password Baru (min. 8 karakter)</label>
                        <input type="password" name="password" required
                               style="width:100%;padding:9px 12px;border:1px solid var(--line);border-radius:8px"
                               placeholder="Password baru">
                        @error('password')<div style="color:var(--bad);font-size:12px;margin-top:4px">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Konfirmasi Password Baru</label>
                        <input type="password" name="password_confirmation" required
                               style="width:100%;padding:9px 12px;border:1px solid var(--line);border-radius:8px"
                               placeholder="Ulangi password baru">
                    </div>
                    <div>
                        <button type="submit" class="btn">
                            <x-lucide-key-round class="w-4 h-4" /> Simpan Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><x-lucide-history class="w-5 h-5" /> Aktivitas Terakhir Saya</h3>
            </div>
            <div class="card-body tight">
                @if($aktivitasTerakhir->count())
                    <div class="table-wrapper">
                        <table>
                            <tbody>
                                @foreach($aktivitasTerakhir as $a)
                                    <tr>
                                        <td>
                                            <b>{{ $a->judul }}</b><br>
                                            <span style="font-size:12px;color:var(--muted)">{{ $a->jenis }} · {{ $a->created_at->diffForHumans() }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty">
                        <div class="icon">📋</div>
                        <h4>Belum ada aktivitas tercatat</h4>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
