@extends('layouts.admin-with-tabs')

@section('title', 'Import Presensi Mesin - SIMANTAP')
@section('page_title', 'Import Presensi Mesin')
@section('page_subtitle', 'Tarik data dari mesin fingerprint melalui file Excel/CSV')

@section('content')
    <div class="grid grid-4" style="margin-bottom:16px">
        <div class="stat"><div class="label">Pegawai Aktif</div><div class="value">{{ $jumlahPegawai }}</div></div>
        <div class="stat ok"><div class="label">Sudah Punya NIK</div><div class="value">{{ $sudahAdaNik }}</div></div>
        <div class="stat gold"><div class="label">Belum Punya NIK</div><div class="value">{{ max($jumlahPegawai - $sudahAdaNik, 0) }}</div></div>
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="card-header"><h3>Alur Import</h3></div>
        <div class="card-body">
            <ol style="margin:0;padding-left:20px;font-size:14px;color:#344054;line-height:1.8">
                <li><strong>Import data karyawan</strong> — unggah file berisi <code>NIK</code> dan <code>Nama</code>. Sistem mencocokkan nama ke akun guru/kepsek/tendik lalu menyimpan NIK.</li>
                <li><strong>Import log mesin</strong> — unggah export mesin (mis. <code>InOutData.xls</code>) atau template CSV. Sistem mengambil scan masuk paling awal dan pulang paling akhir per pegawai per hari.</li>
                <li>Data hasil koreksi manual, serta status <code>izin</code>/<code>sakit</code>, tidak akan ditimpa.</li>
            </ol>
            <p style="margin:12px 0 0;font-size:13px;color:#667085">Batas jam masuk untuk status terlambat saat ini: <strong>{{ config('presensi.mesin_jam_masuk', '07:15') }}</strong>. Dapat diubah melalui <code>PRESENSI_MESIN_JAM_MASUK</code> di file <code>.env</code>.</p>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px">
        <div class="card">
            <div class="card-header">
                <h3>A. Import Data Karyawan (NIK)</h3>
                <a href="{{ route('admin.presensi.mesin.template.karyawan') }}" class="btn btn-sm">Unduh Template</a>
            </div>
            <div class="card-body">
                <p style="font-size:13px;color:#344054;margin:0 0 10px">Header wajib: <code>nik,nama</code>. Format yang didukung: XLS, XLSX, CSV. Nama yang tidak cocok atau ambigu akan dilewati dan dilaporkan.</p>
                <form action="{{ route('admin.presensi.mesin.karyawan.import') }}" method="POST" enctype="multipart/form-data" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
                    @csrf
                    <div style="flex:1;min-width:220px">
                        <label class="form-label">File data karyawan (maks 10MB)</label>
                        <input type="file" name="file" accept=".xls,.xlsx,.csv,.txt" required class="form-control">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-sm">Import NIK</button>
                    </div>
                </form>
                @error('file')
                    <div style="color:#B42318;font-size:13px;margin-top:8px">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>B. Import Log Mesin</h3>
                <a href="{{ route('admin.presensi.mesin.template.log') }}" class="btn btn-sm">Unduh Template</a>
            </div>
            <div class="card-body">
                <p style="font-size:13px;color:#344054;margin:0 0 10px">Mendukung export mesin (<code>Nama</code>, <code>No.PIN</code>, <code>Tgl/Waktu</code>, <code>Status</code>) maupun template <code>nik,tanggal,jam_masuk,jam_pulang</code>.</p>
                <form action="{{ route('admin.presensi.mesin.log.import') }}" method="POST" enctype="multipart/form-data" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
                    @csrf
                    <div style="flex:1;min-width:220px">
                        <label class="form-label">File log mesin (maks 10MB)</label>
                        <input type="file" name="file" accept=".xls,.xlsx,.csv,.txt" required class="form-control">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-sm">Import Log</button>
                    </div>
                </form>
                @error('file')
                    <div style="color:#B42318;font-size:13px;margin-top:8px">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
@endsection
