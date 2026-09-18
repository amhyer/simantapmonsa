@extends('layouts.app')

@section('title', 'Kehadiran - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Kehadiran Siswa')
@section('page_subtitle', 'Input dan lihat kehadiran siswa')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="grid grid-4" style="margin-bottom:16px">
    @php
        $totalHadir = $kehadiran->where('status', 'H')->count();
        $totalSakit = $kehadiran->where('status', 'S')->count();
        $totalIzin = $kehadiran->where('status', 'I')->count();
        $totalAlpa = $kehadiran->where('status', 'A')->count();
    @endphp
    <div class="stat-card ok"><div class="stat-value">{{ $totalHadir }}</div><div class="stat-label">Hadir</div></div>
    <div class="stat-card gold"><div class="stat-value">{{ $totalSakit }}</div><div class="stat-label">Sakit</div></div>
    <div class="stat-card primary"><div class="stat-value">{{ $totalIzin }}</div><div class="stat-label">Izin</div></div>
    <div class="stat-card bad"><div class="stat-value">{{ $totalAlpa }}</div><div class="stat-label">Alpa</div></div>
</div>

<div class="card" style="margin-bottom:16px">
    <div class="card-header"><h3>📝 Input Kehadiran</h3></div>
    <div class="card-body">
        <form id="formKehadiran">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:12px;align-items:end">
                <div>
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Tanggal</label>
                    <input type="date" name="tanggal" value="{{ date('Y-m-d') }}" required style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div>
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Siswa</label>
                    <select name="siswa_id" required style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                        <option value="">Pilih Siswa</option>
                        @foreach($siswa as $s)
                            <option value="{{ $s->id }}">{{ $s->nama_peserta_didik }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Status</label>
                    <select name="status" required style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                        @foreach(['H' => 'Hadir','S' => 'Sakit','I' => 'Izin','A' => 'Alpa'] as $v => $l)
                            <option value="{{ $v }}">{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-sm" style="background:#1F3864;color:#fff;height:38px">💾 Simpan</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>📋 Riwayat Kehadiran</h3></div>
    <div class="card-body tight">
        @if($kehadiran->count())
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Tanggal</th>
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Siswa</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Status</th>
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Keterangan</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($kehadiran->take(50) as $k)
                            @php $statusMap = ['H'=>['Hadir','#D1FAE5','#065F46'],'S'=>['Sakit','#FEF3C7','#92400E'],'I'=>['Izin','#DBEAFE','#1E40AF'],'A'=>['Alpa','#FEE2E2','#991B1B']]; @endphp
                            @php [$label,$bg,$fg] = $statusMap[$k->status] ?? ['?','#F3F4F6','#6B7280']; @endphp
                            <tr style="border-bottom:1px solid #F2F4F7">
                                <td style="padding:8px 12px;font-size:12px">{{ \Carbon\Carbon::parse($k->tanggal)->format('d/m/Y') }}</td>
                                <td style="padding:8px 12px;font-weight:600;font-size:12px">{{ $k->nama_siswa ?? $k->siswa?->nama_peserta_didik ?? '-' }}</td>
                                <td style="padding:8px 12px;text-align:center"><span style="background:{{ $bg }};color:{{ $fg }};padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">{{ $label }}</span></td>
                                <td style="padding:8px 12px;font-size:12px;color:#667085">{{ $k->keterangan ?? '-' }}</td>
                                <td style="padding:8px 12px;text-align:center">
                                    <form action="{{ route('guru.kehadiran.destroy', $k->id) }}" method="POST" style="display:inline" onsubmit="return confirm('Hapus?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-ghost btn-sm" style="color:#DC2626">🗑</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div style="text-align:center;padding:40px;color:#888">
                <div style="font-size:48px;margin-bottom:12px">🗓️</div>
                <h4>Belum ada data kehadiran</h4>
            </div>
        @endif
    </div>
</div>

<script>
document.getElementById('formKehadiran').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    try {
        const r = await fetch('{{ route("guru.kehadiran.store") }}', {
            method: 'POST', headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, body: fd
        });
        if (r.redirected || r.ok) { window.location.reload(); }
    } catch(e) { alert('Gagal menyimpan'); }
});
</script>
@endsection
