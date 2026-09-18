@extends('layouts.app')

@section('title', 'Catatan Siswa - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Catatan Siswa')
@section('page_subtitle', 'Catatan perkembangan dan perilaku siswa')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="grid grid-3" style="margin-bottom:16px">
    <div class="stat-card primary"><div class="stat-value">{{ $siswa->count() }}</div><div class="stat-label">Total Siswa</div></div>
    <div class="stat-card gold"><div class="stat-value">{{ $catatan->count() }}</div><div class="stat-label">Total Catatan</div></div>
                <div class="stat-card ok"><div class="stat-value">{{ $catatan->where('jenis', 'apresiasi')->count() }}</div><div class="stat-label">Catatan Apresiasi</div></div>
</div>

<div class="card" style="margin-bottom:16px">
    <div class="card-header"><h3>📝 Tambah Catatan</h3></div>
    <div class="card-body">
        <form id="formCatatan">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px">
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
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Tanggal</label>
                    <input type="date" name="tanggal" value="{{ date('Y-m-d') }}" required style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div>
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Jenis</label>
                    <select name="jenis" style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                        <option value="apresiasi">Apresiasi</option>
                        <option value="perhatian">Perlu Perhatian</option>
                        <option value="umum">Umum</option>
                    </select>
                </div>
            </div>
            <div style="margin-bottom:12px">
                <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Catatan</label>
                <textarea name="catatan" rows="3" required placeholder="Tuliskan catatan tentang siswa..." style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px;resize:vertical"></textarea>
            </div>
            <button type="submit" class="btn btn-sm" style="background:#1F3864;color:#fff">💾 Simpan Catatan</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>📋 Riwayat Catatan</h3></div>
    <div class="card-body tight">
        @if($catatan->count())
            @foreach($catatan->take(30) as $c)
                @php $jenisColor = match($c->jenis){'apresiasi'=>'#D1FAE5,#065F46','perhatian'=>'#FEE2E2,#991B1B',default=>'#F3F4F6,#6B7280'}; @endphp
                @php [$bg,$fg] = explode(',', $jenisColor); @endphp
                <div style="padding:12px 16px;border-bottom:1px solid #F2F4F7;display:flex;gap:12px;align-items:start">
                    <div style="width:8px;height:8px;border-radius:50%;background:{{ $bg }};margin-top:6px;flex-shrink:0"></div>
                    <div style="flex:1">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                            <span style="font-weight:600;font-size:13px">{{ $c->nama_siswa ?? $c->siswa?->nama_peserta_didik ?? '-' }}</span>
                            <span style="background:{{ $bg }};color:{{ $fg }};padding:1px 8px;border-radius:12px;font-size:10px;font-weight:600">{{ $c->jenis ?? '-' }}</span>
                            <span style="font-size:11px;color:#888">{{ \Carbon\Carbon::parse($c->tanggal)->format('d/m/Y') }}</span>
                        </div>
                        <div style="font-size:13px;color:#333;line-height:1.5">{{ $c->catatan }}</div>
                    </div>
                    <form action="{{ route('guru.catatan.destroy', $c->id) }}" method="POST" onsubmit="return confirm('Hapus?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-ghost btn-sm" style="color:#DC2626">🗑</button>
                    </form>
                </div>
            @endforeach
        @else
            <div style="text-align:center;padding:40px;color:#888">
                <div style="font-size:48px;margin-bottom:12px">🗒️</div>
                <h4>Belum ada catatan</h4>
                <p style="font-size:13px">Buat catatan pertama tentang siswa.</p>
            </div>
        @endif
    </div>
</div>

<script>
document.getElementById('formCatatan').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    try {
        const r = await fetch('{{ route("guru.catatan.store") }}', {
            method: 'POST', headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, body: fd
        });
        if (r.redirected || r.ok) { window.location.reload(); }
    } catch(e) { alert('Gagal menyimpan'); }
});
</script>
@endsection
