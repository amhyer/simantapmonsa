@extends('layouts.app')

@section('title', 'Push GTK ke Dapodik - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Push GTK (Guru/Tendik) ke Dapodik')
@section('page_subtitle', 'Kirim data Guru/Tendik ke Dapodik')

@section('content')
    <div class="card" style="margin-bottom:24px">
        <div class="card-header">
            <h3><x-lucide-users class="w-5 h-5 mr-2" /> Push GTK (Guru/Tendik) ke Dapodik</h3>
        </div>
        <div class="card-body">
            <div class="alert alert-info" style="margin-bottom:16px">
                <x-lucide-info class="w-5 h-5 mr-2" />
                <strong>Informasi:</strong> Push ini akan mengirim data Guru/Tendik yang aktif dan memiliki dapodik_id ke server Dapodik.
            </div>

            <form action="{{ route('admin.dapodik.push.gtk') }}" method="POST">
                @csrf
                <div class="alert alert-info" style="margin-bottom:16px">
                    <x-lucide-info class="w-5 h-5 mr-2" />
                    <strong>Informasi:</strong> Push ini akan mengirim data Guru/Tendik yang aktif dan memiliki dapodik_id ke server Dapodik.
                </div>

                <div class="card" style="margin-bottom:16px">
                    <div class="card-header">
                        <h4>Data GTK yang Akan Dikirim</h4>
                    </div>
                    <div class="card-body">
                        @if($gtkList->count())
                            <div style="overflow-x:auto">
                                <table style="width:100%;border-collapse:collapse">
                                    <thead>
                                        <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                            <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Nama</th>
                                            <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">NIP/NUPTK</th>
                                            <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Peran</th>
                                            <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Kelas/Mapel</th>
                                            <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">DAPODIK ID</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($gtkList as $gtk)
                                            <tr>
                                                <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC"><b>{{ $gtk->nama_lengkap }}</b></td>
                                                <td style="padding:11px 14px;text-align:center">{{ $gtk->nama_pengguna }}</td>
                                                <td style="padding:11px 14px;text-align:center">
                                                    <span class="tag {{ $gtk->peran === 'admin' ? 'tag-primary' : 'tag-ok' }}">{{ $gtk->peran === 'admin' ? 'Tendik' : 'Guru' }}</span>
                                                </td>
                                                <td style="padding:11px 14px">{{ $gtk->kelas_mata_pelajaran ?? '-' }}</td>
                                                <td style="padding:11px 14px;text-align:center">{{ $gtk->dapodik_id ?? '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="empty-state">
                                <div class="icon">👨‍🏫</div>
                                <h4>Tidak ada GTK dengan Dapodik ID</h4>
                                <p>Pastikan guru/tendik sudah memiliki dapodik_id di menu Data Pengguna.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <form action="{{ route('admin.dapodik.push.gtk') }}" method="POST">
                @csrf
                <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:16px">
                    <a href="{{ route('admin.dapodik.index') }}" class="btn btn-ghost btn-sm">Batal</a>
                    <button type="submit" class="btn" onclick="return confirm('Yakin ingin push data GTK ke Dapodik?')">
                        <x-lucide-upload class="w-4 h-4 mr-2" /> Push GTK ke Dapodik
                    </button>
                </div>
            </form>

            @if(session('success'))
                <div class="alert alert-success" style="margin-top:16px">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger" style="margin-top:16px">{{ session('error') }}</div>
            @endif

            @if(isset($result))
                <div class="card" style="margin-top:24px">
                    <div class="card-header">
                        <h4>Hasil Push</h4>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-3" style="margin-bottom:16px">
                            <div class="stat-card"><div class="stat-value">{{ $result['berhasil'] ?? 0 }}</div><div class="stat-label">Berhasil</div></div>
                            <div class="stat-card"><div class="stat-value">{{ $result['gagal'] ?? 0 }}</div><div class="stat-label">Gagal</div></div>
                        </div>
                        @if(!empty($result['errors']))
                            <div class="alert alert-danger" style="margin-top:16px">
                                <strong>Error:</strong>
                                <ul style="margin:8px 0 0 16px">
                                    @foreach($result['errors'] as $err)
                                        <li>{{ $err }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</section>