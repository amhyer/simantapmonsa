@extends('layouts.app')

@section('title', 'Generate e-Rapor - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Generate e-Rapor')
@section('page_subtitle', 'Download rapor siswa dalam format PDF atau Excel')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="grid grid-3" style="margin-bottom:16px">
    <div class="stat-card primary"><div class="stat-value">{{ $siswa->count() }}</div><div class="stat-label">Total Siswa</div></div>
    <div class="stat-card ok"><div class="stat-value">{{ $siswa->count() }}</div><div class="stat-label">Siap Generate</div></div>
    <div class="stat-card gold"><div class="stat-value">{{ $pengaturan->semester ?? 'Ganjil' }}</div><div class="stat-label">{{ $pengaturan->tahun_pelajaran ?? date('Y') . '/' . (date('Y')+1) }}</div></div>
</div>

<div class="card" style="margin-bottom:16px">
    <div class="card-header"><h3>📄 Generate PDF Rapor</h3>
        <div style="display:flex;gap:8px">
            <button class="btn btn-sm" style="background:#1F3864;color:#fff" onclick="bulkGenerate()">📥 Generate Semua (PDF)</button>
            <button class="btn btn-sm" style="background:#B8860B;color:#fff" onclick="exportExcel()">📊 Export Excel</button>
        </div>
    </div>
    <div class="card-body tight">
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                        <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085;width:40px"><input type="checkbox" id="checkAll" onclick="toggleAll(this)"></th>
                        <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">No</th>
                        <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085;width:90px">NISN</th>
                        <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Nama Siswa</th>
                        <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085;width:80px">Kelas</th>
                        <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085;width:80px">Rata Nilai</th>
                        <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085;width:70px">Predikat</th>
                        <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085;width:80px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($siswa as $i => $s)
                        @php $nilai = $s->nilaiErapot()->first(); @endphp
                        <tr style="border-bottom:1px solid #F2F4F7">
                            <td style="padding:8px 12px"><input type="checkbox" class="siswa-check" value="{{ $s->id }}"></td>
                            <td style="padding:8px 12px;font-size:12px;color:#888">{{ $i + 1 }}</td>
                            <td style="padding:8px 12px;font-size:11px;color:#888">{{ $s->nisn ?? $s->nis }}</td>
                            <td style="padding:8px 12px">
                                <div style="display:flex;align-items:center;gap:8px">
                                    <span style="width:28px;height:28px;border-radius:50%;background:#EEF2FF;color:#4338CA;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;flex-shrink:0">{{ substr($s->nama_peserta_didik, 0, 2) }}</span>
                                    <span style="font-weight:600;font-size:12px">{{ $s->nama_peserta_didik }}</span>
                                </div>
                            </td>
                            <td style="padding:8px 12px;text-align:center;font-size:12px">{{ $s->kelas }}</td>
                            <td style="padding:8px 12px;text-align:center;font-weight:600;font-size:12px">{{ $nilai ? number_format($nilai->nilai_akhir, 1) : '-' }}</td>
                            <td style="padding:8px 12px;text-align:center">
                                @if($nilai && $nilai->predikat)
                                    @php $pc = match($nilai->predikat){'A'=>'#D1FAE5,#065F46','B'=>'#DBEAFE,#1E40AF','C'=>'#FEF3C7,#92400E','D'=>'#FEE2E2,#991B1B',default=>'#F3F4F6,#6B7280'}; @endphp
                                    @php [$bg, $fg] = explode(',', $pc); @endphp
                                    <span style="display:inline-block;width:26px;height:26px;line-height:26px;text-align:center;border-radius:50%;font-weight:700;font-size:11px;background:{{ $bg }};color:{{ $fg }}">{{ $nilai->predikat }}</span>
                                @else
                                    <span style="color:#888">—</span>
                                @endif
                            </td>
                            <td style="padding:8px 12px;text-align:center">
                                <a href="{{ route('guru.erapor.pdf', $s->id) }}" class="btn btn-sm" style="background:#1F3864;color:#fff" title="Download PDF">📥 PDF</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" style="text-align:center;padding:30px;color:#888">Belum ada siswa</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div style="padding:10px 16px;border-top:1px solid #E4E7EC;font-size:12px;color:#888">
        💡 Centang siswa lalu klik "Generate Semua" untuk download PDF bulk
    </div>
</div>

@push('scripts')
<script>
function toggleAll(el) {
    document.querySelectorAll('.siswa-check').forEach(c => c.checked = el.checked);
}

function getSelectedIds() {
    return [...document.querySelectorAll('.siswa-check:checked')].map(c => c.value);
}

function bulkGenerate() {
    const ids = getSelectedIds();
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route("guru.erapor.pdf-bulk") }}';
    form.innerHTML = '@csrf <input type="hidden" name="_method" value="POST">';
    ids.forEach(id => {
        form.innerHTML += `<input type="hidden" name="siswa_ids[]" value="${id}">`;
    });
    document.body.appendChild(form);
    form.submit();
    form.remove();
}

function exportExcel() {
    window.location.href = '{{ route("guru.erapor.excel") }}';
}
</script>
@endpush
@endsection
