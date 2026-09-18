@extends('layouts.app')

@section('title', 'Data & Pemulihan - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Data & Pemulihan')
@section('page_subtitle', 'Ekspor dan impor data sistem')

@section('content')
    <div class="grid grid-2" style="margin-bottom:20px">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-download" style="color:var(--ok)"></i> Ekspor Data</h3>
            </div>
            <div class="card-body">
                <p style="color:var(--muted);font-size:13px;margin-bottom:16px">
                    Unduh seluruh data sistem dalam format JSON. File backup mencakup pengguna, siswa, materi, kuis, nilai, kehadiran, dan pengaturan.
                </p>
                <div class="note note" style="margin-bottom:16px">
                    <i class="fas fa-info-circle"></i> File backup akan diunduh secara otomatis setelah proses selesai.
                </div>
                <a href="{{ route('admin.backup.export') }}" class="btn btn-primary btn-block" onclick="return confirm('Ekspor seluruh data sistem?')">
                    <i class="fas fa-download"></i> Ekspor Data Sekarang
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-upload" style="color:var(--warn)"></i> Impor Data</h3>
            </div>
            <div class="card-body">
                <p style="color:var(--muted);font-size:13px;margin-bottom:16px">
                    Pulihkan data dari file backup JSON. Data yang ada akan diperbarui berdasarkan ID record.
                </p>
                <div class="note note-warn" style="margin-bottom:16px">
                    <i class="fas fa-exclamation-triangle"></i> Proses impor akan menimpa data yang sesuai. Pastikan file backup valid.
                </div>
                <form action="{{ route('admin.backup.import') }}" method="POST" enctype="multipart/form-data" id="importForm">
                    @csrf
                    <div style="margin-bottom:14px">
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">File Backup (JSON, maks 10MB)</label>
                        <input type="file" name="backup_file" accept=".json" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px" onchange="previewFile(this)">
                    </div>
                    <div id="filePreview" style="display:none;margin-bottom:14px;padding:10px;background:#EEF2F9;border-radius:8px;font-size:13px">
                        <i class="fas fa-file-code" style="color:var(--navy)"></i> <span id="fileName"></span>
                        <span id="fileSize" style="color:var(--muted);margin-left:8px"></span>
                    </div>
                    <button type="submit" class="btn btn-gold btn-block" id="importBtn" onclick="return confirm('Impor data dari file backup?')">
                        <i class="fas fa-upload"></i> Impor Data
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Riwayat Backup</h3>
        </div>
        <div class="card-body tight">
            @if($backupList->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Nama File</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Ukuran</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($backupList as $backup)
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        <i class="fas fa-file-code" style="color:var(--navy);margin-right:8px"></i>
                                        <code style="font-size:13px">{{ $backup['name'] }}</code>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        {{ number_format($backup['size'] / 1024, 1) }} KB
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        {{ \Carbon\Carbon::parse($backup['date'])->format('d M Y, H:i') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty"><div class="icon">💾</div><b>Belum ada backup</b><p>Backup akan muncul di sini setelah Anda melakukan ekspor pertama kali.</p></div>
            @endif
        </div>
    </div>

    <script>
        function previewFile(input) {
            var preview = document.getElementById('filePreview');
            var nameEl = document.getElementById('fileName');
            var sizeEl = document.getElementById('fileSize');
            if (input.files && input.files[0]) {
                var file = input.files[0];
                nameEl.textContent = file.name;
                sizeEl.textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        }
    </script>
@endsection
