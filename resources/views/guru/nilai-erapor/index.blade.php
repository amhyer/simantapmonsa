@extends('layouts.app')

@section('title', 'Input Nilai e-Rapor - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Input Nilai e-Rapor')
@section('page_subtitle', 'Input nilai formatif, sumatif, dan sumatif akhir')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

@if(session('success'))
    <div class="toast toast-success" style="position:static;margin-bottom:16px">✅ {{ session('success') }}</div>
@endif

<div class="grid grid-4" style="margin-bottom:16px">
    <div class="stat-card primary"><div class="stat-value" id="totalSiswa">{{ $siswa->count() }}</div><div class="stat-label">Total Siswa</div></div>
    <div class="stat-card ok"><div class="stat-value" id="terisi">0</div><div class="stat-label">Sudah Diisi</div></div>
    <div class="stat-card bad"><div class="stat-value" id="belum">0</div><div class="stat-label">Belum Diisi</div></div>
    <div class="stat-card gold"><div class="stat-value" id="progressPct">0%</div><div class="stat-label">Progress</div></div>
</div>

<div class="card" style="margin-bottom:16px">
    <div class="card-header"><h3>⚙️ Filter</h3></div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto;gap:12px;align-items:end">
            <div>
                <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Kelas</label>
                <select id="filterKelas" style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px" onchange="loadData()">
                    @foreach(['I A','II A','III A','IV A','V A','VI A'] as $k)
                        <option value="{{ $k }}" {{ $kelas == $k ? 'selected' : '' }}>{{ $k }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Mata Pelajaran</label>
                <select id="filterMapel" style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px" onchange="loadData()">
                    @foreach(['Matematika','Bahasa Indonesia','IPA','IPS','PKn'] as $m)
                        <option value="{{ $m }}" {{ $mapel == $m ? 'selected' : '' }}>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Semester</label>
                <select id="filterSemester" style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px" onchange="loadData()">
                    <option value="Ganjil" {{ $semester == 'Ganjil' ? 'selected' : '' }}>Ganjil</option>
                    <option value="Genap" {{ $semester == 'Genap' ? 'selected' : '' }}>Genap</option>
                </select>
            </div>
            <div>
                <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Tahun Ajaran</label>
                <select id="filterTahun" style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px" onchange="loadData()">
                    <option value="2025/2026" {{ $tahunAjaran == '2025/2026' ? 'selected' : '' }}>2025/2026</option>
                    <option value="2026/2027" {{ $tahunAjaran == '2026/2027' ? 'selected' : '' }}>2026/2027</option>
                </select>
            </div>
            <div style="display:flex;gap:6px">
                <button class="btn btn-secondary btn-sm" onclick="downloadTemplate()">📥 Template</button>
                <button class="btn btn-secondary btn-sm" onclick="bukaImport()">📤 Import</button>
                <button class="btn btn-secondary btn-sm" onclick="exportNilai()">📊 Export</button>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>📝 Daftar Nilai e-Rapor</h3>
        <div style="display:flex;gap:8px;align-items:center">
            <span style="background:#EEF2FF;color:#4338CA;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600">KKM: {{ $pengaturan->kkm ?? 70 }}</span>
            <button class="btn btn-sm" style="background:#059669;color:#fff" onclick="simpanSemua()">💾 Simpan Semua</button>
        </div>
    </div>
    <div class="card-body tight">
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                        <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085;width:40px">No</th>
                        <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085;width:90px">NISN</th>
                        <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Nama Siswa</th>
                        <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085;width:90px">Formatif<div style="font-size:8px;font-weight:400;opacity:.6">(30%)</div></th>
                        <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085;width:90px">Sumatif<div style="font-size:8px;font-weight:400;opacity:.6">(40%)</div></th>
                        <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085;width:100px">Sumatif Akhir<div style="font-size:8px;font-weight:400;opacity:.6">(30%)</div></th>
                        <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085;width:80px">Akhir</th>
                        <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085;width:60px">Pred</th>
                        <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085;width:50px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($siswa as $i => $s)
                        @php $n = $nilaiExisting[$s->id] ?? null; @endphp
                        <tr style="border-bottom:1px solid #F2F4F7" data-siswa-id="{{ $s->id }}">
                            <td style="padding:8px 12px;font-size:12px;color:#888">{{ $i + 1 }}</td>
                            <td style="padding:8px 12px;font-size:11px;color:#888">{{ $s->nisn ?? $s->nis }}</td>
                            <td style="padding:8px 12px">
                                <div style="display:flex;align-items:center;gap:8px">
                                    <span style="width:28px;height:28px;border-radius:50%;background:#EEF2FF;color:#4338CA;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;flex-shrink:0">{{ substr($s->nama_peserta_didik, 0, 2) }}</span>
                                    <span style="font-weight:600;font-size:12px">{{ $s->nama_peserta_didik }}</span>
                                </div>
                            </td>
                            <td style="padding:8px 12px;text-align:center">
                                <input type="number" class="nilai-input" data-field="formatif" data-siswa="{{ $s->id }}" value="{{ $n->nilai_formatif ?? '' }}" min="0" max="100" placeholder="—" style="width:70px;text-align:center;padding:6px;border:1px solid #E4E7EC;border-radius:6px;font-weight:600;font-size:12px">
                            </td>
                            <td style="padding:8px 12px;text-align:center">
                                <input type="number" class="nilai-input" data-field="sumatif" data-siswa="{{ $s->id }}" value="{{ $n->nilai_sumatif ?? '' }}" min="0" max="100" placeholder="—" style="width:70px;text-align:center;padding:6px;border:1px solid #E4E7EC;border-radius:6px;font-weight:600;font-size:12px">
                            </td>
                            <td style="padding:8px 12px;text-align:center">
                                <input type="number" class="nilai-input" data-field="sumatif_akhir" data-siswa="{{ $s->id }}" value="{{ $n->nilai_sumatif_akhir ?? '' }}" min="0" max="100" placeholder="—" style="width:70px;text-align:center;padding:6px;border:1px solid #E4E7EC;border-radius:6px;font-weight:600;font-size:12px">
                            </td>
                            <td style="padding:8px 12px;text-align:center" data-siswa="{{ $s->id }}" class="col-akhir">
                                @if($n && $n->nilai_akhir > 0)
                                    <span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;{{ $n->nilai_akhir >= ($pengaturan->kkm ?? 70) ? 'background:#D1FAE5;color:#065F46' : 'background:#FEE2E2;color:#991B1B' }}">{{ number_format($n->nilai_akhir, 1) }}</span>
                                @else
                                    <span style="color:#888">—</span>
                                @endif
                            </td>
                            <td style="padding:8px 12px;text-align:center" data-siswa="{{ $s->id }}" class="col-predikat">
                                @if($n && $n->predikat)
                                    @php $pc = match($n->predikat){'A'=>'#D1FAE5,#065F46','B'=>'#DBEAFE,#1E40AF','C'=>'#FEF3C7,#92400E','D'=>'#FEE2E2,#991B1B',default=>'#F3F4F6,#6B7280'}; @endphp
                                    @php [$bg, $fg] = explode(',', $pc); @endphp
                                    <span style="display:inline-block;width:26px;height:26px;line-height:26px;text-align:center;border-radius:50%;font-weight:700;font-size:11px;background:{{ $bg }};color:{{ $fg }}">{{ $n->predikat }}</span>
                                @else
                                    <span style="color:#888">—</span>
                                @endif
                            </td>
                            <td style="padding:8px 12px;text-align:center">
                                <button class="btn btn-ghost btn-sm" onclick="lihatDeskripsi({{ $s->id }}, '{{ addslashes($s->nama_peserta_didik) }}')" title="Deskripsi">👁</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" style="text-align:center;padding:30px;color:#888">Belum ada siswa di kelas ini</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div style="padding:10px 16px;border-top:1px solid #E4E7EC;display:flex;justify-content:space-between;align-items:center;font-size:12px;color:#888">
        <span>💡 <kbd style="background:#F2F4F7;padding:1px 5px;border-radius:3px">Tab</kbd> pindah, <kbd style="background:#F2F4F7;padding:1px 5px;border-radius:3px">Enter</kbd> simpan & lanjut</span>
        <span>Auto-save aktif</span>
    </div>
</div>

<div class="modal-overlay" id="modalDeskripsi" style="display:none" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal">
        <div class="modal-header"><h3>📝 Deskripsi Capaian</h3><button class="modal-close" onclick="document.getElementById('modalDeskripsi').style.display='none'">&times;</button></div>
        <div class="modal-body" id="deskripsiBody"><div style="text-align:center;padding:20px">⏳ Memuat...</div></div>
        <div class="modal-footer"><button class="btn btn-primary" onclick="document.getElementById('modalDeskripsi').style.display='none'">Tutup</button></div>
    </div>
</div>

<div class="modal-overlay" id="modalImport" style="display:none" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal">
        <div class="modal-header"><h3>📤 Import dari Excel</h3><button class="modal-close" onclick="document.getElementById('modalImport').style.display='none'">&times;</button></div>
        <form id="formImport" enctype="multipart/form-data">
            @csrf
            <div class="modal-body">
                <div style="background:#EEF2FF;border-radius:8px;padding:12px;margin-bottom:14px;font-size:13px;color:#4338CA">
                    <b>💡 Cara:</b> Download template → Isi nilai → Upload kembali → Import
                </div>
                <div style="margin-bottom:14px">
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">File Excel (.xlsx)</label>
                    <input type="file" name="file" accept=".xlsx,.xls" required style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalImport').style.display='none'">Batal</button>
                <button type="submit" class="btn btn-primary">📤 Import</button>
            </div>
        </form>
    </div>
</div>

<script>
const CSRF = '{{ csrf_token() }}';
let siswaData = @json($siswa->map(fn($s) => ['id'=>$s->id,'nama'=>$s->nama_peserta_didik,'nis'=>$s->nis]));

document.querySelectorAll('.nilai-input').forEach(input => {
    input.addEventListener('blur', function() { autoSave(this); });
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            this.blur();
            const inputs = [...document.querySelectorAll('.nilai-input')];
            const idx = inputs.indexOf(this);
            if (idx < inputs.length - 1) inputs[idx + 1].focus();
        }
    });
});

async function autoSave(input) {
    const val = input.value;
    if (val === '') return;
    try {
        const r = await fetch('{{ route("guru.nilai-erapor.auto-save") }}', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
            body: JSON.stringify({
                siswa_id: input.dataset.siswa, field: input.dataset.field, value: val,
                kelas: document.getElementById('filterKelas').value,
                mapel: document.getElementById('filterMapel').value,
                semester: document.getElementById('filterSemester').value,
                tahun_ajaran: document.getElementById('filterTahun').value,
            })
        });
        const d = await r.json();
        if (d.success) {
            const sid = input.dataset.siswa;
            const kkm = {{ $pengaturan->kkm ?? 70 }};
            const akhirEl = document.querySelector(`.col-akhir[data-siswa="${sid}"]`);
            const predEl = document.querySelector(`.col-predikat[data-siswa="${sid}"]`);
            if (akhirEl && d.nilai_akhir > 0) {
                const bg = d.nilai_akhir >= kkm ? '#D1FAE5' : '#FEE2E2';
                const fg = d.nilai_akhir >= kkm ? '#065F46' : '#991B1B';
                akhirEl.innerHTML = `<span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:${bg};color:${fg}">${d.nilai_akhir.toFixed(1)}</span>`;
            }
            if (predEl && d.predikat) {
                const colors = {A:['#D1FAE5','#065F46'],B:['#DBEAFE','#1E40AF'],C:['#FEF3C7','#92400E'],D:['#FEE2E2','#991B1B']};
                const [bg,fg] = colors[d.predikat] || ['#F3F4F6','#6B7280'];
                predEl.innerHTML = `<span style="display:inline-block;width:26px;height:26px;line-height:26px;text-align:center;border-radius:50%;font-weight:700;font-size:11px;background:${bg};color:${fg}">${d.predikat}</span>`;
            }
            updateProgress();
        }
    } catch(e) { console.error(e); }
}

function updateProgress() {
    const total = siswaData.length;
    let terisi = 0;
    document.querySelectorAll('tr[data-siswa-id]').forEach(row => {
        const inputs = row.querySelectorAll('.nilai-input');
        if ([...inputs].some(i => i.value !== '')) terisi++;
    });
    const pct = total > 0 ? Math.round(terisi/total*100) : 0;
    document.getElementById('terisi').textContent = terisi;
    document.getElementById('belum').textContent = total - terisi;
    document.getElementById('progressPct').textContent = pct + '%';
}

function loadData() {
    const k = document.getElementById('filterKelas').value;
    const m = document.getElementById('filterMapel').value;
    const s = document.getElementById('filterSemester').value;
    const t = document.getElementById('filterTahun').value;
    window.location.href = `{{ route('guru.nilai-erapor.index') }}?kelas=${k}&mapel=${m}&semester=${s}&tahun_ajaran=${t}`;
}

function downloadTemplate() {
    const k = document.getElementById('filterKelas').value;
    const m = document.getElementById('filterMapel').value;
    window.location.href = `{{ route('guru.nilai-erapor.template') }}?kelas=${k}&mapel=${m}`;
}

function exportNilai() {
    const k = document.getElementById('filterKelas').value;
    const m = document.getElementById('filterMapel').value;
    const s = document.getElementById('filterSemester').value;
    const t = document.getElementById('filterTahun').value;
    window.location.href = `{{ route('guru.nilai-erapor.export') }}?kelas=${k}&mapel=${m}&semester=${s}&tahun_ajaran=${t}`;
}

function bukaImport() { document.getElementById('modalImport').style.display = 'flex'; }

document.getElementById('formImport').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('kelas', document.getElementById('filterKelas').value);
    fd.append('mapel', document.getElementById('filterMapel').value);
    fd.append('semester', document.getElementById('filterSemester').value);
    fd.append('tahun_ajaran', document.getElementById('filterTahun').value);
    try {
        const r = await fetch('{{ route("guru.nilai-erapor.import") }}', { method:'POST', headers:{'X-CSRF-TOKEN':CSRF}, body:fd });
        const d = await r.json();
        if (d.success) { toast(d.message,'success'); setTimeout(()=>location.reload(),800); }
        else toast(d.message||'Gagal','error');
    } catch(e) { toast('Error','error'); }
});

async function lihatDeskripsi(sid, nama) {
    document.getElementById('modalDeskripsi').style.display = 'flex';
    document.getElementById('deskripsiBody').innerHTML = '<div style="text-align:center;padding:20px">⏳ Memuat...</div>';
    const m = document.getElementById('filterMapel').value;
    const s = document.getElementById('filterSemester').value;
    try {
        const r = await fetch(`/guru/nilai-erapor/deskripsi/${sid}?mapel=${m}&semester=${s}`, {headers:{'X-Requested-With':'XMLHttpRequest'}});
        const d = await r.json();
        if (d.success) {
            const colors = {A:'#065F46',B:'#1E40AF',C:'#92400E',D:'#991B1B'};
            document.getElementById('deskripsiBody').innerHTML = `
                <div style="margin-bottom:12px"><b>${d.nama}</b> <span style="color:${colors[d.predikat]||'#666'};font-weight:700">${d.predikat} (${d.nilai_akhir})</span></div>
                <div style="background:#F9FAFB;border:1px solid #E4E7EC;border-radius:8px;padding:14px;font-size:13.5px;line-height:1.7">${d.deskripsi}</div>`;
        } else {
            document.getElementById('deskripsiBody').innerHTML = '<div style="text-align:center;padding:20px;color:#888">Belum ada deskripsi. Isi nilai terlebih dahulu.</div>';
        }
    } catch(e) { document.getElementById('deskripsiBody').innerHTML = '<div style="text-align:center;padding:20px;color:#888">Gagal memuat.</div>'; }
}

async function simpanSemua() {
    const values = [];
    document.querySelectorAll('tr[data-siswa-id]').forEach(row => {
        const sid = row.dataset.siswaId;
        const f = row.querySelector('[data-field="formatif"]').value;
        const s = row.querySelector('[data-field="sumatif"]').value;
        const sa = row.querySelector('[data-field="sumatif_akhir"]').value;
        if (f || s || sa) values.push({siswa_id:sid, formatif:f||null, sumatif:s||null, sumatif_akhir:sa||null});
    });
    if (!values.length) return toast('Belum ada nilai','error');
    try {
        const r = await fetch('{{ route("guru.nilai-erapor.simpan") }}', {
            method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
            body: JSON.stringify({
                nilai: values,
                kelas: document.getElementById('filterKelas').value,
                mapel: document.getElementById('filterMapel').value,
                semester: document.getElementById('filterSemester').value,
                tahun_ajaran: document.getElementById('filterTahun').value,
            })
        });
        const d = await r.json();
        if (d.success) { toast(d.message,'success'); setTimeout(()=>location.reload(),800); }
        else toast(d.message||'Gagal','error');
    } catch(e) { toast('Error','error'); }
}

function toast(msg, type) {
    const t = document.createElement('div');
    t.style.cssText = `position:fixed;bottom:20px;right:20px;padding:12px 18px;border-radius:8px;color:#fff;z-index:9999;font-size:13px;background:${type==='success'?'#22c55e':'#ef4444'}`;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(()=>t.remove(),3000);
}

updateProgress();
</script>
@endsection
