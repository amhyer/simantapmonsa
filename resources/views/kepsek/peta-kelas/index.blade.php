@extends('layouts.app')

@section('title', 'Peta Kelas - SIMANTAP')

@section('sidebar')
    @include('kepsek.partials.sidebar')
@endsection

@section('page_title', 'Peta Kelas')
@section('page_subtitle', 'Overview seluruh kelas, wali, siswa, dan akun ortu')

@section('content')
<div class="grid grid-4" style="margin-bottom:16px">
    <div class="stat-card primary"><div class="stat-value">{{ $stats['total_kelas'] }}</div><div class="stat-label">Total Kelas</div></div>
    <div class="stat-card gold"><div class="stat-value">{{ $stats['total_guru'] }}</div><div class="stat-label">Total Guru</div></div>
    <div class="stat-card ok"><div class="stat-value">{{ $stats['total_siswa'] }}</div><div class="stat-label">Total Siswa</div></div>
    <div class="stat-card"><div class="stat-value">{{ $peta->sum('akun_ortu') }}</div><div class="stat-label">Akun Ortu Aktif</div></div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-map" style="margin-right:8px;color:var(--navy)"></i>Peta Kelas</h3>
        <input type="text" id="searchKelas" placeholder="Cari kelas/guru..." class="btn btn-ghost btn-sm" style="width:200px;text-align:left;padding:6px 12px">
    </div>
    <div class="card-body tight">
        @if($peta->count())
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Wali Kelas</th>
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Kelas</th>
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Mata Pelajaran</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">KKM</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Jumlah Siswa</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Akun Ortu</th>
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Terakhir Masuk</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tabelPeta">
                        @foreach($peta as $p)
                        <tr style="border-bottom:1px solid #F2F4F7">
                            <td style="padding:10px 12px">
                                <div style="display:flex;align-items:center;gap:8px">
                                    <span style="width:32px;height:32px;background:#1F3864;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600">{{ substr($p['guru']->nama_lengkap, 0, 2) }}</span>
                                    <div>
                                        <b style="font-size:13px">{{ $p['guru']->nama_lengkap }}</b>
                                        <div style="font-size:11px;color:#667085">{{ $p['guru']->nama_pengguna }}</div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding:10px 12px"><span class="tag tag-primary">{{ $p['kelas'] ?: '-' }}</span></td>
                            <td style="padding:10px 12px;font-size:12px">{{ $p['mapel'] }}</td>
                            <td style="padding:10px 12px;text-align:center;font-weight:600">{{ $p['kkm'] }}</td>
                            <td style="padding:10px 12px;text-align:center;font-weight:700;font-size:15px">{{ $p['jumlah_siswa'] }}</td>
                            <td style="padding:10px 12px;text-align:center">
                                @if($p['akun_ortu'] > 0)
                                    <span class="tag tag-ok">{{ $p['akun_ortu'] }} aktif</span>
                                @else
                                    <span class="tag tag-secondary">0</span>
                                @endif
                            </td>
                            <td style="padding:10px 12px;font-size:12px;color:#667085">
                                {{ $p['terakhir_masuk'] ? \Carbon\Carbon::parse($p['terakhir_masuk'])->diffForHumans() : 'Belum pernah' }}
                            </td>
                            <td style="padding:10px 12px;text-align:center">
                                <a href="{{ route('kepsek.peta-kelas.detail', $p['guru']->id) }}" class="btn btn-ghost btn-sm"><i class="fas fa-eye"></i> Detail</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div style="text-align:center;padding:40px;color:#888">
                <div style="font-size:48px;margin-bottom:12px"><i class="fas fa-school"></i></div>
                <h4>Belum ada data kelas</h4>
                <p style="font-size:13px">Data kelas akan muncul setelah guru mengisi data siswa.</p>
            </div>
        @endif
    </div>
</div>

<script>
document.getElementById('searchKelas').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#tabelPeta tr').forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>
@endsection
