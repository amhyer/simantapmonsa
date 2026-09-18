@extends('layouts.app')

@section('title', 'Input Nilai - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Input Nilai Cepat')
@section('page_subtitle', 'Input semua nilai siswa dalam satu halaman')

@section('content')
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
        <div class="card-header">
            <h3>⚙️ Pengaturan Penilaian</h3>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:12px;align-items:end">
                <div>
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Jenis Penilaian</label>
                    <select id="jenisNilai" onchange="loadNilai()" style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                        <option value="UH">Ulangan Harian</option>
                        <option value="Tugas">Tugas</option>
                        <option value="PTS">PTS</option>
                        <option value="PAS">PAS</option>
                        <option value="Praktik">Praktik</option>
                    </select>
                </div>
                <div>
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Judul / Nomor</label>
                    <input type="text" id="judulPenilaian" placeholder="Contoh: UH 1, Tugas Bab 1" style="width:100%;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div>
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">KKM</label>
                    <input type="number" id="kkm" value="70" style="width:80px;padding:9px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div>
                    <button class="btn btn-primary" onclick="loadNilai()">🔄 Muat</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>📝 Tabel Input Nilai</h3>
            <div style="display:flex;gap:8px">
                <button class="btn btn-sm btn-secondary" onclick="bukaPaste()">📋 Paste dari Excel</button>
                <button class="btn btn-sm btn-secondary" onclick="isiSemua()">🔢 Isi Semua</button>
                <button class="btn btn-sm" onclick="simpanSemua()" style="background:#059669;color:#fff">💾 Simpan Semua</button>
            </div>
        </div>
        <div class="card-body tight">
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse" id="tabelNilai">
                    <thead>
                        <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                            <th style="padding:10px 14px;text-align:left;font-size:11px;text-transform:uppercase;color:#667085;width:50px">No</th>
                            <th style="padding:10px 14px;text-align:left;font-size:11px;text-transform:uppercase;color:#667085">Nama Siswa</th>
                            <th style="padding:10px 14px;text-align:left;font-size:11px;text-transform:uppercase;color:#667085;width:120px">NIS</th>
                            <th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#667085;width:100px">Nilai</th>
                            <th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#667085;width:80px">Predikat</th>
                            <th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#667085;width:100px">Status</th>
                            <th style="padding:10px 14px;text-align:left;font-size:11px;text-transform:uppercase;color:#667085;width:150px">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody id="bodyNilai">
                        <tr><td colspan="7" style="text-align:center;padding:30px;color:#888">Klik "Muat" untuk memuat data siswa</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="modalPaste" style="display:none" onclick="if(event.target===this)this.style.display='none'">
        <div class="modal">
            <div class="modal-header"><h3>📋 Paste dari Excel</h3><button class="modal-close" onclick="tutupPaste()">&times;</button></div>
            <div class="modal-body">
                <p style="font-size:13px;color:#666;margin-bottom:10px">Copy kolom nama + nilai dari Excel, lalu paste di bawah ini:</p>
                <textarea id="pasteData" rows="10" placeholder="Andi Nurul Fadhilah	85&#10;Muh. Rizky R.	92&#10;Siti Aisyah P.	65" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px;font-family:monospace;font-size:13px"></textarea>
                <div style="margin-top:10px;font-size:12px;color:#888">Format: Nama [Tab] Nilai (per baris)</div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="tutupPaste()">Batal</button>
                <button class="btn btn-primary" onclick="applyPaste()">✅ Terapkan</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="modalIsiSemua" style="display:none" onclick="if(event.target===this)this.style.display='none'">
        <div class="modal">
            <div class="modal-header"><h3>🔢 Isi Semua Siswa</h3><button class="modal-close" onclick="document.getElementById('modalIsiSemua').style.display='none'">&times;</button></div>
            <div class="modal-body">
                <p style="font-size:13px;color:#666;margin-bottom:10px">Masukkan nilai yang sama untuk semua siswa yang belum diisi:</p>
                <input type="number" id="isiSemuaNilai" min="0" max="100" placeholder="Nilai" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px;font-size:16px">
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="document.getElementById('modalIsiSemua').style.display='none'">Batal</button>
                <button class="btn btn-primary" onclick="applyIsiSemua()">✅ Terapkan</button>
            </div>
        </div>
    </div>

    <style>
    .input-nilai {
        width: 80px; padding: 7px 8px; text-align: center; border: 1px solid #E4E7EC;
        border-radius: 6px; font-size: 13px; font-weight: 600;
    }
    .input-nilai:focus { border-color: #B8860B; outline: none; box-shadow: 0 0 0 2px rgba(184,134,11,.15); }
    .input-nilai.below-kkm { border-color: #B42318; background: #FEF2F2; }
    .predikat-badge {
        display: inline-block; width: 28px; height: 28px; line-height: 28px;
        text-align: center; border-radius: 50%; font-weight: 700; font-size: 12px;
    }
    .predikat-A { background: #D1FAE5; color: #065F46; }
    .predikat-B { background: #DBEAFE; color: #1E40AF; }
    .predikat-C { background: #FEF3C7; color: #92400E; }
    .predikat-D { background: #FEE2E2; color: #991B1B; }
    .status-tuntas { color: #059669; font-weight: 600; }
    .status-remidi { color: #B42318; font-weight: 600; }
    </style>

    <script>
    const CSRF = '{{ csrf_token() }}';
    let siswaData = [];

    function loadNilai() {
        const jenis = document.getElementById('jenisNilai').value;
        const judul = document.getElementById('judulPenilaian').value;
        fetch(`/guru/input-nilai/data?jenis=${jenis}&judul=${encodeURIComponent(judul)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(r => r.json()).then(data => {
            siswaData = data.siswa;
            renderTabel();
        });
    }

    function renderTabel() {
        const kkm = parseInt(document.getElementById('kkm').value) || 70;
        const tbody = document.getElementById('bodyNilai');
        let terisi = 0;

        tbody.innerHTML = siswaData.map((s, i) => {
            const val = s.nilai ?? '';
            const isFilled = val !== '' && val !== null;
            if (isFilled) terisi++;
            const predikat = getPredikat(val);
            const status = isFilled ? (val >= kkm ? 'Tuntas' : 'Remidi') : '-';
            const below = isFilled && val < kkm;
            return `<tr style="border-bottom:1px solid #F2F4F7" data-siswa="${s.siswa_id}">
                <td style="padding:8px 14px;font-size:12px;color:#888">${i + 1}</td>
                <td style="padding:8px 14px;font-weight:600;font-size:13px">${s.nama}</td>
                <td style="padding:8px 14px;font-size:12px;color:#888">${s.nis}</td>
                <td style="padding:8px 14px;text-align:center">
                    <input type="number" class="input-nilai ${below ? 'below-kkm' : ''}"
                           min="0" max="100" value="${val}"
                           data-siswa="${s.siswa_id}"
                           onchange="updateRow(this)" onkeydown="navKey(event, this)">
                </td>
                <td style="padding:8px 14px;text-align:center">
                    <span class="predikat-badge predikat-${predikat}">${predikat}</span>
                </td>
                <td style="padding:8px 14px;text-align:center">
                    <span class="status-${status.toLowerCase()}">${status}</span>
                </td>
                <td style="padding:8px 14px;font-size:12px;color:#888">${below ? '⚠️ Perlu remidi' : ''}</td>
            </tr>`;
        }).join('');

        updateStats(terisi);
    }

    function updateRow(input) {
        const kkm = parseInt(document.getElementById('kkm').value) || 70;
        const val = input.value ? parseFloat(input.value) : null;
        const row = input.closest('tr');
        const predikatEl = row.querySelectorAll('td')[3].querySelector('.predikat-badge');
        const statusEl = row.querySelectorAll('td')[4];

        const p = getPredikat(val);
        predikatEl.textContent = p;
        predikatEl.className = `predikat-badge predikat-${p}`;

        const status = val !== null ? (val >= kkm ? 'Tuntas' : 'Remidi') : '-';
        statusEl.innerHTML = `<span class="status-${status.toLowerCase()}">${status}</span>`;

        input.className = `input-nilai ${val !== null && val < kkm ? 'below-kkm' : ''}`;

        let terisi = 0;
        document.querySelectorAll('.input-nilai').forEach(inp => {
            if (inp.value !== '' && inp.value !== null) terisi++;
        });
        updateStats(terisi);
    }

    function updateStats(terisi) {
        const total = siswaData.length;
        const belum = total - terisi;
        const pct = total > 0 ? Math.round(terisi / total * 100) : 0;
        document.getElementById('terisi').textContent = terisi;
        document.getElementById('belum').textContent = belum;
        document.getElementById('progressPct').textContent = pct + '%';
    }

    function getPredikat(val) {
        if (val === null || val === '' || val === undefined) return '-';
        if (val >= 90) return 'A';
        if (val >= 80) return 'B';
        if (val >= 70) return 'C';
        return 'D';
    }

    function navKey(e, input) {
        if (e.key === 'Enter' || e.key === 'Tab') {
            e.preventDefault();
            const inputs = [...document.querySelectorAll('.input-nilai')];
            const idx = inputs.indexOf(input);
            if (e.key === 'Enter' && idx < inputs.length - 1) {
                inputs[idx + 1].focus();
            } else if (e.key === 'Tab') {
                inputs[e.shiftKey ? idx - 1 : idx + 1]?.focus();
            }
        }
    }

    function simpanSemua() {
        const jenis = document.getElementById('jenisNilai').value;
        const judul = document.getElementById('judulPenilaian').value;
        const nilai = [];
        document.querySelectorAll('.input-nilai').forEach(input => {
            const sid = input.dataset.siswa;
            const val = input.value !== '' ? parseFloat(input.value) : null;
            nilai.push({ siswa_id: parseInt(sid), nilai: val });
        });

        fetch('/guru/input-nilai/simpan', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ jenis, judul_penilaian: judul, nilai })
        }).then(r => r.json()).then(d => {
            if (d.success) {
                toast(d.message, 'success');
                loadNilai();
            } else {
                toast(d.message || 'Gagal menyimpan', 'error');
            }
        }).catch(() => toast('Error jaringan', 'error'));
    }

    function bukaPaste() { document.getElementById('modalPaste').style.display = 'flex'; }
    function tutupPaste() { document.getElementById('modalPaste').style.display = 'none'; }

    function applyPaste() {
        const raw = document.getElementById('pasteData').value;
        fetch('/guru/input-nilai/paste', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ data: raw })
        }).then(r => r.json()).then(d => {
            d.parsed.forEach(item => {
                const match = siswaData.find(s => s.nama.toLowerCase().includes(item.nama.toLowerCase()));
                if (match && item.nilai !== null) {
                    const input = document.querySelector(`.input-nilai[data-siswa="${match.siswa_id}"]`);
                    if (input) {
                        input.value = item.nilai;
                        updateRow(input);
                    }
                }
            });
            tutupPaste();
            toast('Data dari Excel diterapkan', 'success');
        });
    }

    function isiSemua() { document.getElementById('modalIsiSemua').style.display = 'flex'; }

    function applyIsiSemua() {
        const val = parseFloat(document.getElementById('isiSemuaNilai').value);
        if (isNaN(val)) return toast('Masukkan nilai', 'error');
        document.querySelectorAll('.input-nilai').forEach(input => {
            if (input.value === '' || input.value === null) {
                input.value = val;
                updateRow(input);
            }
        });
        document.getElementById('modalIsiSemua').style.display = 'none';
        toast(`${val} diterapkan ke semua siswa yang kosong`, 'success');
    }

    function toast(msg, type) {
        const t = document.createElement('div');
        t.className = `toast toast-${type}`;
        t.textContent = msg;
        t.style.cssText = 'position:fixed;bottom:20px;right:20px;padding:12px 18px;border-radius:8px;color:#fff;z-index:9999;font-size:13px';
        if (type === 'success') t.style.background = '#22c55e';
        if (type === 'error') t.style.background = '#ef4444';
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 3000);
    }

    loadNilai();
    </script>
@endsection
