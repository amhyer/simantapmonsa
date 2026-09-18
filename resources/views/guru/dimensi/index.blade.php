@extends('layouts.app')

@section('title', 'Profil Lulusan - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Profil Lulusan')
@section('page_subtitle', 'Dimensi Profil Pelajar Pancasila')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="grid grid-3" style="margin-bottom:16px">
    <div class="stat-card primary"><div class="stat-value">{{ $siswa->count() }}</div><div class="stat-label">Total Siswa</div></div>
    <div class="stat-card gold"><div class="stat-value">{{ $dimensi->count() }}</div><div class="stat-label">Total Dimensi</div></div>
    <div class="stat-card ok"><div class="stat-value">{{ $dimensi->count() > 0 ? number_format($dimensi->avg('skor'), 1) : '0' }}</div><div class="stat-label">Rata-rata Skor</div></div>
</div>

<div class="card" style="margin-bottom:16px">
    <div class="card-header">
        <h3>📝 Input Dimensi</h3>
    </div>
    <div class="card-body">
        <form id="formDimensi">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                <div>
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Siswa</label>
                    <select name="siswa_id" required style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                        <option value="">Pilih Siswa</option>
                        @foreach($siswa as $s)
                            <option value="{{ $s->id }}">{{ $s->nama_peserta_didik }} ({{ $s->kelas }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">No Dimensi (1-8)</label>
                    <select name="no_dimensi" required style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                        <option value="">Pilih No</option>
                        @for($i = 1; $i <= 8; $i++)
                            <option value="{{ $i }}">{{ $i }}</option>
                        @endfor
                    </select>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                <div>
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Dimensi</label>
                    <select name="dimensi" required style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                        <option value="">Pilih Dimensi</option>
                        @foreach(['Beriman & Bertakwa','Mandiri','Bergotong Royong','Bernalar Kritis','Kreatif','Berkebinekaan Global','Bernegara Pancasila','Majemuk'] as $d)
                            <option value="{{ $d }}">{{ $d }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Skor (1-4)</label>
                    <input type="number" name="skor" min="1" max="4" step="0.1" required style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
            </div>
            <button type="submit" class="btn btn-sm" style="background:#1F3864;color:#fff">💾 Simpan Dimensi</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>📋 Daftar Dimensi</h3></div>
    <div class="card-body tight">
        @if($dimensi->count())
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">No</th>
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Siswa</th>
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Dimensi</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Skor</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Predikat</th>
                            <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Catatan</th>
                            <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($dimensi as $i => $d)
                            <tr style="border-bottom:1px solid #F2F4F7">
                                <td style="padding:8px 12px;font-size:12px;color:#888">{{ $i + 1 }}</td>
                                <td style="padding:8px 12px;font-weight:600;font-size:12px">{{ $d->nama_siswa ?? $d->siswa?->nama_peserta_didik ?? '-' }}</td>
                                <td style="padding:8px 12px;font-size:12px">{{ $d->dimensi }}</td>
                                <td style="padding:8px 12px;text-align:center"><span style="background:#EEF2FF;color:#4338CA;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">{{ $d->skor }}</span></td>
                                <td style="padding:8px 12px;text-align:center;font-size:12px;font-weight:600">{{ $d->predikat ?? '-' }}</td>
                                <td style="padding:8px 12px;font-size:12px;color:#667085">{{ $d->catatan ?? '-' }}</td>
                                <td style="padding:8px 12px;text-align:center">
                                    <form action="{{ route('guru.dimensi.destroy', $d->id) }}" method="POST" style="display:inline" onsubmit="return confirm('Hapus?')">
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
                <div style="font-size:48px;margin-bottom:12px">🌱</div>
                <h4>Belum ada dimensi profil lulusan</h4>
                <p style="font-size:13px">Isi form di atas untuk menambahkan dimensi.</p>
            </div>
        @endif
    </div>
</div>

<script>
document.getElementById('formDimensi').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    try {
        const r = await fetch('{{ route("guru.dimensi.store") }}', {
            method: 'POST', headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, body: fd
        });
        if (r.redirected || r.ok) { window.location.reload(); }
    } catch(e) { alert('Gagal menyimpan'); }
});
</script>
@endsection
