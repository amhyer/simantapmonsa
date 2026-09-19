@extends('layouts.app')

@section('title', 'Push Rombel ke Dapodik - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Push Rombel ke Dapodik')
@section('page_subtitle', 'Kirim data rombongan belajar ke server Dapodik')

@section('content')
    <div class="card" style="margin-bottom:24px">
        <div class="card-header">
            <h3><x-lucide-users class="w-5 h-5 mr-2" /> Push Rombongan Belajar ke Dapodik</h3>
        </div>
        <div class="card-body">
            <div class="alert alert-info" style="margin-bottom:16px">
                <x-lucide-info class="w-5 h-5 mr-2" />
                <strong>Informasi:</strong> Push ini akan mengirim data rombongan belajar (kelas/rombel) beserta wali kelasnya ke server Dapodik pusat.
            </div>

            <form action="{{ route('admin.dapodik.push.rombel') }}" method="POST">
                @csrf
                <div class="alert alert-info" style="margin-bottom:16px">
                    <x-lucide-info class="w-5 h-5 mr-2" />
                    <strong>Informasi:</strong> Push ini akan mengirim data rombongan belajar (kelas/rombel) beserta wali kelasnya ke server Dapodik pusat.
                </div>

                <div class="card" style="margin-bottom:16px">
                    <div class="card-header">
                        <h4>Data Rombel yang Akan Dikirim</h4>
                    </div>
                    <div class="card-body">
                        @if($rombels->count())
                            <div style="overflow-x:auto">
                                <table style="width:100%;border-collapse:collapse">
                                    <thead>
                                        <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                            <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Nama Rombel</th>
                                            <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Tingkat</th>
                                            <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Wali Kelas</th>
                                            <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">DAPODIK ID</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($rombels as $r)
                                            <tr>
                                                <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC"><b>{{ $r->nama_rombel }}</b></td>
                                                <td style="padding:11px 14px;text-align:center">{{ $r->tingkat ?? '-' }}</td>
                                                <td style="padding:11px 14px">{{ $r->guru?->nama_lengkap ?? '-' }}</td>
                                                <td style="padding:11px 14px;text-align:center">{{ $r->dapodik_id ?? '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="empty-state">
                                <div class="icon">🏫</div>
                                <h4>Tidak ada rombel untuk di-push</h4>
                                <p>Pastikan rombel sudah memiliki dapodik_id di menu Peta Kelas.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <form action="{{ route('admin.dapodik.push.rombel') }}" method="POST">
                @csrf
                <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:16px">
                    <a href="{{ route('admin.dapodik.index') }}" class="btn btn-ghost btn-sm">Batal</a>
                    <button type="submit" class="btn" onclick="return confirm('Yakin ingin push data rombel ke Dapodik?')">
                        <x-lucide-users class="w-4 h-4 mr-2" /> Push Rombel ke Dapodik
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
                        <div class="grid grid-4" style="margin-bottom:16px">
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