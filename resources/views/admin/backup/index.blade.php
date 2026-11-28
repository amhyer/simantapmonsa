@extends('layouts.admin-with-tabs')

@section('title', 'Data & Pemulihan - SIMANTAP')

@section('page_title', 'Data & Pemulihan')
@section('page_subtitle', 'Ekspor, impor, dan pemulihan data sekolah Anda')

@section('content')
    @if(!empty($isMultiTenant))
        <div class="note" style="margin-bottom:18px;border:1px solid #C7D7FE;background:#EEF4FF;border-radius:10px;padding:12px 14px;color:#1D2939">
            <i class="fas fa-shield-alt" style="color:var(--navy);margin-right:6px"></i>
            <b>Isolasi Data Sekolah Aktif:</b> Seluruh proses backup, riwayat file, restore, dan hapus data pada halaman ini hanya mencakup data milik <b>{{ $namaSekolah ?? 'sekolah Anda' }}</b>. Data sekolah lain tetap terpisah dan aman.
        </div>
    @endif

    <div class="grid grid-2" style="margin-bottom:20px">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-download" style="color:var(--ok)"></i> Backup Data (SIMANTAP &amp; e-Rapor)</h3>
            </div>
            <div class="card-body">
                <p style="color:var(--muted);font-size:13px;margin-bottom:16px">
                    Buat cadangan penuh seluruh data sekolah Anda dalam format <b>JSON SIMANTAP</b> atau format terenkripsi <b>e-Rapor SD (<code>.xsp</code>)</b>.
                </p>
                <div class="note note" style="margin-bottom:16px">
                    <i class="fas fa-info-circle"></i> Backup JSON disimpan ke daftar Riwayat Backup di bawah dan dapat diunduh kapan saja, sedangkan Backup <code>.xsp</code> langsung mengunduh arsip terenkripsi AES-256-CTR.
                </div>
                <div style="display:flex;flex-direction:column;gap:10px">
                    <form action="{{ route('admin.backup.export') }}" method="POST" onsubmit="return confirm('Buat backup seluruh data sekolah Anda (JSON)?')">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-block" id="btnBackupJson">
                            <i class="fas fa-database"></i> Backup Data SIMANTAP (.json)
                        </button>
                    </form>
                    <form action="{{ route('admin.backup.export-erapor-xsp') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-block" id="btnBackupXsp" style="width:100%;justify-content:center;border:1px solid var(--navy);color:var(--navy);font-weight:600">
                            <i class="fas fa-file-archive"></i> Backup &amp; Unduh Format e-Rapor (.xsp)
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-upload" style="color:var(--warn)"></i> Restore / Pulihkan Data (<code>.xsp</code> / <code>.json</code>)</h3>
            </div>
            <div class="card-body">
                <p style="color:var(--muted);font-size:13px;margin-bottom:16px">
                    Pulihkan database dari file backup <b>Aplikasi e-Rapor Kemendikdasmen (<code>.xsp</code>)</b> maupun file backup <b>SIMANTAP (<code>.json</code>)</b>.
                </p>
                <div class="note note-warn" style="margin-bottom:16px">
                    <i class="fas fa-exclamation-triangle"></i> File <code>.xsp</code> akan didekripsi otomatis (67 tabel e-Rapor: Siswa, PTK, Rombel, Mapel, Pembelajaran, TP, Nilai &amp; Deskripsi Rapor, Transkrip, Ekskul, Catatan Walas, dan P5).
                </div>
                <form action="{{ route('admin.backup.import') }}" method="POST" enctype="multipart/form-data" id="importForm">
                    @csrf
                    <div style="margin-bottom:14px">
                        <label for="fld-admin-backup-backup-file" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">File Backup (<code>.xsp</code> e-Rapor atau <code>.json</code> SIMANTAP, maks {{ $maxUploadLabel ?? '100 MB' }})</label>
                        <input id="fld-admin-backup-backup-file" type="file" name="backup_file" accept=".xsp,.json" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px" onchange="previewFile(this)">
                    </div>
                    <div id="filePreview" style="display:none;margin-bottom:14px;padding:10px;background:#EEF2F9;border-radius:8px;font-size:13px">
                        <i class="fas fa-file-code" style="color:var(--navy)"></i> <span id="fileName"></span>
                        <span id="fileSize" style="color:var(--muted);margin-left:8px"></span>
                    </div>
                    <div id="fileSizeWarn" class="note note-warn" style="display:none;margin-bottom:14px;border:1px solid #FECACA;background:#FEF2F2;border-radius:8px;padding:10px 12px;color:#B42318"></div>
                    <button type="submit" class="btn btn-gold btn-block" id="importBtn" onclick="return confirm('Pulihkan (Restore) data dari file backup yang dipilih?')">
                        <i class="fas fa-upload"></i> Restore / Impor Data Sekarang
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
                <div class="table-wrapper">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Nama File</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Ukuran</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Tanggal</th>
                                <th style="padding:11px 14px;text-align:right;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($backupList as $backup)
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        <i class="fas {{ str_ends_with($backup['name'], '.xsp') ? 'fa-file-archive' : 'fa-file-code' }}" style="color:var(--navy);margin-right:8px"></i>
                                        <code style="font-size:13px">{{ $backup['name'] }}</code>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        {{ number_format($backup['size'] / 1024, 1) }} KB
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        {{ \Carbon\Carbon::parse($backup['date'])->format('d M Y, H:i') }}
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:right">
                                        <a href="{{ route('admin.backup.download', $backup['name']) }}" class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-download"></i> Unduh
                                        </a>
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

    <div class="card" style="margin-top:20px;border:2px solid var(--bad)">
        <div class="card-header" style="background:#FEE9E7">
            <h3 style="color:var(--bad)"><i class="fas fa-exclamation-triangle"></i> Zona Berbahaya — Hapus Semua Data</h3>
        </div>
        <div class="card-body">
            @if($errors->any())
                <div class="note note-warn" style="margin-bottom:12px;border:1px solid #FECACA;background:#FEF2F2;border-radius:8px;padding:10px 12px">
                    <b><i class="fas fa-exclamation-triangle"></i> Gagal menghapus data:</b>
                    <ul style="margin:6px 0 0 18px;padding:0">
                        @foreach($errors->all() as $err)
                            <li style="font-size:13px">{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <p style="color:var(--muted);font-size:13px;margin-bottom:12px">
                Mengosongkan <b>seluruh tabel data</b> (siswa, nilai, kehadiran, materi, kuis, rombel, PTK, mapel, kokurikuler, log, dsb).
                <b>Akun pengguna, semester, identitas sekolah, dan API key dipertahankan</b> agar Anda tetap bisa login.
            </p>
            <div class="note note-warn" style="margin-bottom:16px">
                <i class="fas fa-exclamation-triangle"></i> Wajib <b>unduh backup dulu</b> (tombol Ekspor di atas). Tindakan ini <b>tidak bisa dibatalkan</b>.
            </div>
            <form action="{{ route('admin.backup.wipe') }}" method="POST" onsubmit="return confirm('YAKIN menghapus SEMUA data? Tindakan ini permanen!')">
                @csrf
                <div style="margin-bottom:12px">
                    <label for="fld-admin-backup-konfirmasi" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Ketik persis: <code>HAPUS SEMUA DATA</code></label>
                    <input id="fld-admin-backup-konfirmasi" type="text" name="konfirmasi" required autocomplete="off" placeholder="HAPUS SEMUA DATA" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div style="margin-bottom:14px">
                    <label for="fld-admin-backup-password" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Kata sandi Anda (konfirmasi identitas)</label>
                    <input id="fld-admin-backup-password" type="password" name="password" required autocomplete="current-password" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <button type="submit" class="btn btn-block" style="background:var(--bad);color:#fff">
                    <i class="fas fa-trash"></i> Hapus Semua Data Sekarang
                </button>
            </form>
        </div>
    </div>

    <script>
        var maxBackupUploadBytes = {{ (int) ($maxUploadBytes ?? (100 * 1024 * 1024)) }};
        var maxBackupUploadLabel = @json($maxUploadLabel ?? '100 MB');

        function previewFile(input) {
            var preview = document.getElementById('filePreview');
            var nameEl = document.getElementById('fileName');
            var sizeEl = document.getElementById('fileSize');
            var warnEl = document.getElementById('fileSizeWarn');
            var importBtn = document.getElementById('importBtn');
            if (input.files && input.files[0]) {
                var file = input.files[0];
                var sizeMb = (file.size / 1024 / 1024).toFixed(2);
                nameEl.textContent = file.name;
                sizeEl.textContent = sizeMb + ' MB';
                preview.style.display = 'block';
                if (maxBackupUploadBytes > 0 && file.size > maxBackupUploadBytes) {
                    warnEl.textContent = 'Ukuran file (' + sizeMb + ' MB) melebihi batas unggah server (' + maxBackupUploadLabel + ').';
                    warnEl.style.display = 'block';
                    if (importBtn) importBtn.disabled = true;
                } else {
                    warnEl.style.display = 'none';
                    if (importBtn) importBtn.disabled = false;
                }
            } else {
                preview.style.display = 'none';
                warnEl.style.display = 'none';
                if (importBtn) importBtn.disabled = false;
            }
        }
    </script>
@endsection

