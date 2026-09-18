@extends('layouts.app')

@section('title', 'Penyimpanan Data - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Penyimpanan Data')
@section('page_subtitle', 'Koneksi Google Sheet untuk penyimpanan data')

@section('content')
    <div class="grid grid-2" style="margin-bottom:20px">
        <div class="stat {{ $sheet['terhubung'] ? 'ok' : 'bad' }}">
            <div class="label">Status Koneksi</div>
            <div class="value" style="font-size:20px">{{ $sheet['terhubung'] ? 'Terhubung' : 'Tidak Terhubung' }}</div>
            <div class="desc">{{ $sheet['terhubung'] ? 'Google Sheet aktif' : 'Belum dikonfigurasi' }}</div>
        </div>
        <div class="stat">
            <div class="label">Spreadsheet ID</div>
            <div class="value" style="font-size:14px;word-break:break-all">{{ $sheet['spreadsheet_id'] ?? '-' }}</div>
        </div>
    </div>

    <div class="grid grid-2">
        <div class="card">
            <div class="card-header">
                <h3>Koneksi Google Sheet</h3>
            </div>
            <div class="card-body">
                @if($sheet['terhubung'])
                    <div class="note note-ok" style="margin-bottom:16px">
                        <i class="fas fa-check-circle"></i> Google Sheet sudah terhubung. Data akan otomatis tersinkronisasi.
                    </div>
                    <div style="margin-bottom:16px">
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">URL Spreadsheet</label>
                        <a href="{{ $sheet['spreadsheet_url'] ?? '#' }}" target="_blank" style="color:var(--navy);font-size:13px;word-break:break-all">
                            {{ $sheet['spreadsheet_url'] ?? '-' }}
                        </a>
                    </div>
                    <form action="{{ route('admin.sheet.disconnect') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Putuskan koneksi Google Sheet?')">
                            <i class="fas fa-unlink"></i> Putuskan Koneksi
                        </button>
                    </form>
                @else
                    <div class="note note-warn" style="margin-bottom:16px">
                        <i class="fas fa-exclamation-triangle"></i> Belum terhubung ke Google Sheet. Isi form berikut untuk menghubungkan.
                    </div>
                    <form action="{{ route('admin.sheet.connect') }}" method="POST">
                        @csrf
                        <div style="margin-bottom:14px">
                            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Spreadsheet ID *</label>
                            <input type="text" name="spreadsheet_id" required placeholder="Contoh: 1AbCdEfGhIjKlMnOpQrStUvWxYz" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                            <small style="color:var(--muted);font-size:12px">ID dari URL Google Sheet Anda</small>
                        </div>
                        <div style="margin-bottom:14px">
                            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">URL Spreadsheet *</label>
                            <input type="url" name="spreadsheet_url" required placeholder="https://docs.google.com/spreadsheets/d/..." style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                        </div>
                        <button type="submit" class="btn"><i class="fas fa-link"></i> Hubungkan</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Panduan Koneksi</h3>
            </div>
            <div class="card-body">
                <div style="font-size:13px;color:var(--muted);line-height:1.8">
                    <div style="margin-bottom:12px">
                        <span style="font-weight:600;color:var(--text)">1.</span> Buka Google Drive dan buat Spreadsheet baru.
                    </div>
                    <div style="margin-bottom:12px">
                        <span style="font-weight:600;color:var(--text)">2.</span> Salin URL Spreadsheet dari browser.
                    </div>
                    <div style="margin-bottom:12px">
                        <span style="font-weight:600;color:var(--text)">3.</span> Ekstrak ID Spreadsheet dari URL (bagian setelah <code style="background:#EEF2F9;padding:2px 6px;border-radius:4px;font-size:12px">/d/</code>).
                    </div>
                    <div style="margin-bottom:12px">
                        <span style="font-weight:600;color:var(--text)">4.</span> Masukkan ID dan URL ke form di sebelah kiri.
                    </div>
                    <div>
                        <span style="font-weight:600;color:var(--text)">5.</span> Klik "Hubungkan" untuk menyimpan koneksi.
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
