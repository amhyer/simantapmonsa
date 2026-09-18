@extends('layouts.app')

@section('title', 'Ambil dari Kelas Lain - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Ambil dari Kelas Lain')
@section('page_subtitle', 'Pungut siswa dari kelas wali lain (tanpa ketik ulang)')

@section('content')
@if(session('success'))
    <div class="toast toast-success" style="position:static;margin-bottom:16px">{{ session('success') }}</div>
@endif

<div class="card" style="margin-bottom:16px">
    <div class="card-header">
        <h3><i class="fas fa-file-import"></i> Pilih Siswa dari Kelas Lain</h3>
        <a href="{{ route('guru.siswa.index') }}" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>
    <div class="card-body">
        <p style="font-size:13px;color:#667085;margin:0 0 16px 0">
            Pilih kelas, centang siswa yang ingin diambil. ID siswa dipertahankan (data tidak duplikat).
        </p>

        <div style="display:flex;gap:12px;align-items:end;margin-bottom:16px">
            <div style="flex:1">
                <label style="font-size:12px;color:#667085;display:block;margin-bottom:4px">Pilih Kelas</label>
                <select id="kelasSelect" style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px;font-size:13px">
                    <option value="">-- Pilih Kelas --</option>
                    @foreach($kelasList as $k)
                        <option value="{{ $k }}">{{ $k }}</option>
                    @endforeach
                </select>
            </div>
            <button onclick="loadSiswa()" class="btn btn-primary" id="btnLoad"><i class="fas fa-search"></i> Cari</button>
        </div>

        <div id="hasilPungut" style="display:none">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                <h4 style="margin:0;font-size:14px">Daftar Siswa</h4>
                <div style="display:flex;gap:8px">
                    <button onclick="centangSemua()" class="btn btn-ghost btn-sm"><i class="fas fa-check-double"></i> Centang Semua</button>
                    <button onclick="batalSemua()" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i> Batal Semua</button>
                    <button onclick="simpanPungut()" class="btn btn-primary btn-sm" id="btnSimpan" disabled><i class="fas fa-save"></i> Ambil yang Dipilih</button>
                </div>
            </div>
            <div id="daftarSiswa" style="overflow-x:auto"></div>
        </div>
    </div>
</div>

<script>
function loadSiswa() {
    const kelas = document.getElementById('kelasSelect').value;
    if (!kelas) return;

    document.getElementById('btnLoad').disabled = true;
    document.getElementById('btnLoad').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memuat...';

    fetch('{{ route("guru.siswa.pungut.kelas") }}?kelas=' + encodeURIComponent(kelas))
        .then(r => r.json())
        .then(data => {
            document.getElementById('hasilPungut').style.display = 'block';
            renderSiswa(data);
            document.getElementById('btnLoad').disabled = false;
            document.getElementById('btnLoad').innerHTML = '<i class="fas fa-search"></i> Cari';
        });
}

function renderSiswa(list) {
    if (list.length === 0) {
        document.getElementById('daftarSiswa').innerHTML = '<div style="text-align:center;padding:20px;color:#888">Tidak ada siswa tersedia di kelas ini.</div>';
        return;
    }

    let html = '<table style="width:100%;border-collapse:collapse">';
    html += '<thead><tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">';
    html += '<th style="padding:8px 12px;text-align:left;width:40px"><input type="checkbox" id="cekSemua" onchange="toggleSemua(this)"></th>';
    html += '<th style="padding:8px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Nama</th>';
    html += '<th style="padding:8px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">NIS</th>';
    html += '<th style="padding:8px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">JK</th>';
    html += '<th style="padding:8px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Kelas</th>';
    html += '<th style="padding:8px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Orang Tua</th>';
    html += '</tr></thead><tbody>';

    list.forEach(s => {
        html += '<tr style="border-bottom:1px solid #F2F4F7">';
        html += '<td style="padding:8px 12px"><input type="checkbox" class="cek-siswa" value="' + s.id + '" onchange="updateBtn()"></td>';
        html += '<td style="padding:8px 12px;font-size:13px"><b>' + esc(s.nama_peserta_didik) + '</b></td>';
        html += '<td style="padding:8px 12px;font-size:12px;color:#667085">' + esc(s.nis) + '</td>';
        html += '<td style="padding:8px 12px"><span class="tag ' + (s.jenis_kelamin === 'L' ? 'tag-primary' : 'tag-gold') + '">' + s.jenis_kelamin + '</span></td>';
        html += '<td style="padding:8px 12px;font-size:12px">' + esc(s.kelas) + '</td>';
        html += '<td style="padding:8px 12px;font-size:12px;color:#667085">' + esc(s.nama_orang_tua || '-') + '</td>';
        html += '</tr>';
    });

    html += '</tbody></table>';
    document.getElementById('daftarSiswa').innerHTML = html;
}

function esc(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

function toggleSemua(el) {
    document.querySelectorAll('.cek-siswa').forEach(c => c.checked = el.checked);
    updateBtn();
}

function centangSemua() {
    document.querySelectorAll('.cek-siswa').forEach(c => c.checked = true);
    document.getElementById('cekSemua').checked = true;
    updateBtn();
}

function batalSemua() {
    document.querySelectorAll('.cek-siswa').forEach(c => c.checked = false);
    document.getElementById('cekSemua').checked = false;
    updateBtn();
}

function updateBtn() {
    const count = document.querySelectorAll('.cek-siswa:checked').length;
    const btn = document.getElementById('btnSimpan');
    btn.disabled = count === 0;
    btn.innerHTML = '<i class="fas fa-save"></i> Ambil ' + count + ' Siswa';
}

function simpanPungut() {
    const ids = [...document.querySelectorAll('.cek-siswa:checked')].map(c => c.value);
    if (ids.length === 0) return;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route("guru.siswa.pungut.simpan") }}';

    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value = '{{ csrf_token() }}';
    form.appendChild(csrf);

    ids.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'siswa_ids[]';
        input.value = id;
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
}
</script>
@endsection
