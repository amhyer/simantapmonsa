@extends('layouts.app')

@section('title', 'Daftar Nilai - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Daftar Nilai')
@section('page_subtitle', 'Semua penilaian yang sudah diinput')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="grid grid-3" style="margin-bottom:16px">
    <div class="stat-card primary"><div class="stat-value">{{ $siswa->count() }}</div><div class="stat-label">Total Siswa</div></div>
    <div class="stat-card gold"><div class="stat-value">{{ $nilai->count() }}</div><div class="stat-label">Total Penilaian</div></div>
    <div class="stat-card ok"><div class="stat-value">{{ $nilai->count() > 0 ? number_format($nilai->avg('nilai'), 1) : '0' }}</div><div class="stat-label">Rata-rata Nilai</div></div>
</div>

<div class="card" style="margin-bottom:16px">
    <div class="card-header">
        <h3>📝 Input Nilai Baru</h3>
    </div>
    <div class="card-body">
        <form id="formNilai">
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
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Jenis</label>
                    <select name="jenis" required style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                        @foreach(['Tugas','Ulangan Harian','Praktik','PTS','PAS'] as $j)
                            <option value="{{ $j }}">{{ $j }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Tanggal</label>
                    <input type="date" name="tanggal" value="{{ date('Y-m-d') }}" required style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px;margin-bottom:12px">
                <div>
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Judul Penilaian</label>
                    <input type="text" name="judul_penilaian" placeholder="Contoh: Ulangan Bab 1" required style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div>
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Mata Pelajaran</label>
                    <input type="text" name="mata_pelajaran" placeholder="Contoh: Matematika" required style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr;gap:12px;margin-bottom:12px">
                <div>
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Nilai (0-100)</label>
                    <input type="number" name="nilai" min="0" max="100" required style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
            </div>
            <button type="submit" class="btn btn-sm" style="background:#1F3864;color:#fff">💾 Simpan Nilai</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>📋 Riwayat Penilaian</h3></div>
    <div class="card-body tight">
        @if($nilai->count())
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Tanggal</th>
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Siswa</th>
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Jenis</th>
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Judul</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Nilai</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($nilai as $n)
                            <tr style="border-bottom:1px solid #F2F4F7">
                                <td style="padding:8px 12px;font-size:12px">{{ \Carbon\Carbon::parse($n->tanggal)->format('d/m/Y') }}</td>
                                <td style="padding:8px 12px;font-weight:600;font-size:12px">{{ $n->nama_siswa ?? $n->siswa?->nama_peserta_didik ?? '-' }}</td>
                                <td style="padding:8px 12px"><span style="background:#EEF2FF;color:#4338CA;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">{{ $n->jenis }}</span></td>
                                <td style="padding:8px 12px;font-size:12px">{{ $n->judul_penilaian ?? '-' }}</td>
                                <td style="padding:8px 12px;text-align:center"><span style="background:{{ $n->nilai >= 70 ? '#D1FAE5;color:#065F46' : '#FEE2E2;color:#991B1B' }};padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">{{ $n->nilai }}</span></td>
                                <td style="padding:8px 12px;text-align:center">
                                    <a href="{{ route('guru.nilai.edit', $n->id) }}" class="btn btn-ghost btn-sm">✏️</a>
                                    <form action="{{ route('guru.nilai.destroy', $n->id) }}" method="POST" style="display:inline" onsubmit="return confirm('Hapus?')">
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
                <div style="font-size:48px;margin-bottom:12px">📊</div>
                <h4>Belum ada penilaian</h4>
                <p style="font-size:13px">Isi form di atas untuk menambahkan nilai.</p>
            </div>
        @endif
    </div>
</div>

<script>
document.getElementById('formNilai').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    try {
        const r = await fetch('{{ route("guru.nilai.store") }}', {
            method: 'POST', headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, body: fd
        });
        if (r.redirected || r.ok) { window.location.reload(); }
    } catch(e) { alert('Gagal menyimpan'); }
});
</script>
@endsection
