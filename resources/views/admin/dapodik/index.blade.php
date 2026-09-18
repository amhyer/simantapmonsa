@extends('layouts.app')

@section('title', 'Konfigurasi & Sinkronisasi Dapodik - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Konfigurasi & Sinkronisasi Dapodik')
@section('page_subtitle', 'Pilih metode sinkronisasi data Dapodik')

@section('header_actions')
    <a href="{{ route('admin.api-keys.index') }}" class="btn btn-ghost btn-sm"><i class="fas fa-key"></i> API Keys</a>
@endsection

@section('content')

<div id="globalAlert" style="display:none;padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:16px"></div>

{{-- Stats Cards --}}
<div class="grid grid-4" style="margin-bottom:20px">
    <div class="stat-card primary"><div class="stat-value" id="statTotalSiswa">-</div><div class="stat-label">Total Siswa</div></div>
    <div class="stat-card gold"><div class="stat-value" id="statPtk">-</div><div class="stat-label">PTK (Guru/Tendik)</div></div>
    <div class="stat-card ok"><div class="stat-value" id="statRombel">-</div><div class="stat-label">Rombel</div></div>
    <div class="stat-card" id="statSyncCard"><div class="stat-value" id="statSyncTerakhir" style="font-size:16px">-</div><div class="stat-label">Sinkron Terakhir</div></div>
</div>

{{-- Method Selector --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px">
    <button type="button" onclick="switchTab('bridge')" id="tabBtnBridge"
        style="display:flex;align-items:center;gap:14px;padding:20px 24px;border-radius:12px;border:2px solid var(--navy);background:linear-gradient(135deg,#1F3864,#2d5a9e);color:#fff;cursor:pointer;transition:all .2s">
        <div style="width:48px;height:48px;background:rgba(255,255,255,.2);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0">
            <i class="fas fa-plug"></i>
        </div>
        <div style="text-align:left">
            <div style="font-size:16px;font-weight:800;letter-spacing:-.3px">Dapodik Bridge</div>
            <div style="font-size:12px;opacity:.85;margin-top:2px">Sinkron otomatis via aplikasi desktop Dapodik</div>
        </div>
    </button>
    <button type="button" onclick="switchTab('manual')" id="tabBtnManual"
        style="display:flex;align-items:center;gap:14px;padding:20px 24px;border-radius:12px;border:2px solid #E4E7EC;background:#fff;color:#101828;cursor:pointer;transition:all .2s">
        <div style="width:48px;height:48px;background:#F2F4F7;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0">
            <i class="fas fa-file-import"></i>
        </div>
        <div style="text-align:left">
            <div style="font-size:16px;font-weight:800;letter-spacing:-.3px">Kelola Langsung</div>
            <div style="font-size:12px;opacity:.65;margin-top:2px">Import data manual dari file atau input langsung</div>
        </div>
    </button>
</div>

{{-- ═══════════════ TAB: DAPODIK BRIDGE ═══════════════ --}}
<div id="tabBridge">

    {{-- Steps --}}
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px">
        <div style="padding:14px;background:#EEF2F9;border-radius:10px;border-left:3px solid #1F3864">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                <div style="width:24px;height:24px;background:#1F3864;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px">1</div>
                <b style="font-size:12px">Konfigurasi</b>
            </div>
            <p style="font-size:11px;color:#667085;margin:0">Isi NPSN, token, host/port</p>
        </div>
        <div style="padding:14px;background:#FBF4E4;border-radius:10px;border-left:3px solid #B8860B">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                <div style="width:24px;height:24px;background:#B8860B;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px">2</div>
                <b style="font-size:12px">Test Koneksi</b>
            </div>
            <p style="font-size:11px;color:#667085;margin:0">Pastikan koneksi berhasil</p>
        </div>
        <div style="padding:14px;background:#E6F5F0;border-radius:10px;border-left:3px solid #059669">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                <div style="width:24px;height:24px;background:#059669;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px">3</div>
                <b style="font-size:12px">Preview</b>
            </div>
            <p style="font-size:11px;color:#667085;margin:0">Lihat pratinjau data</p>
        </div>
        <div style="padding:14px;background:#FEE9E7;border-radius:10px;border-left:3px solid #DC2626">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                <div style="width:24px;height:24px;background:#DC2626;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px">4</div>
                <b style="font-size:12px">Sinkronisasi</b>
            </div>
            <p style="font-size:11px;color:#667085;margin:0">Mulai sinkron data</p>
        </div>
    </div>

    {{-- Config + Connection --}}
    <div class="grid grid-2" style="margin-bottom:20px">
        <div class="card" id="configCard">
            <div class="card-header">
                <h3><i class="fas fa-cog" style="color:var(--navy)"></i> Konfigurasi Dapodik</h3>
                <span class="tag" id="configStatusTag">Belum Dikonfigurasi</span>
            </div>
            <div class="card-body">
                <form id="configForm" onsubmit="return saveConfig(event)">
                    <div style="margin-bottom:14px">
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">NPSN <span style="color:#DC2626">*</span></label>
                        <input type="text" id="cfgNpsn" placeholder="8 digit NPSN sekolah" maxlength="8" required
                            style="width:100%;padding:10px 12px;border:1px solid #E4E7EC;border-radius:8px;font-size:13px">
                    </div>
                    <div style="margin-bottom:14px">
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Token Dapodik <span style="color:#DC2626">*</span></label>
                        <div style="position:relative">
                            <input type="password" id="cfgToken" placeholder="Token dari aplikasi Dapodik" required
                                style="width:100%;padding:10px 12px;border:1px solid #E4E7EC;border-radius:8px;font-size:13px;padding-right:40px">
                            <button type="button" onclick="toggleToken()" id="btnToggleToken"
                                style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#667085;font-size:14px;padding:4px">
                                <i class="fas fa-eye" id="tokenEyeIcon"></i>
                            </button>
                        </div>
                        <div style="font-size:11px;color:#667085;margin-top:4px">Token dari Dapodik → menu Web Service</div>
                    </div>
                    <div class="grid grid-3" style="margin-bottom:14px">
                        <div>
                            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Host</label>
                            <input type="text" id="cfgHost" value="localhost"
                                style="width:100%;padding:10px 12px;border:1px solid #E4E7EC;border-radius:8px;font-size:13px">
                        </div>
                        <div>
                            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Port</label>
                            <input type="text" id="cfgPort" value="5774"
                                style="width:100%;padding:10px 12px;border:1px solid #E4E7EC;border-radius:8px;font-size:13px">
                        </div>
                        <div>
                            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Protokol</label>
                            <select id="cfgProtocol"
                                style="width:100%;padding:10px 12px;border:1px solid #E4E7EC;border-radius:8px;font-size:13px">
                                <option value="http" selected>http://</option>
                                <option value="https">https://</option>
                            </select>
                        </div>
                    </div>
                    <div style="margin-bottom:16px">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:600">
                            <input type="checkbox" id="cfgArchiveUnlisted" checked
                                style="width:16px;height:16px;accent-color:var(--navy);cursor:pointer">
                            Arsipkan siswa yang tidak terdaftar di Dapodik
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block" id="btnSaveConfig">
                        <i class="fas fa-save"></i> Simpan Konfigurasi
                    </button>
                </form>
            </div>
        </div>

        <div>
            <div class="card" style="margin-bottom:16px">
                <div class="card-header">
                    <h3><i class="fas fa-plug" style="color:var(--ok)"></i> Test Koneksi</h3>
                    <span class="tag" id="connectionStatusTag">Belum Dites</span>
                </div>
                <div class="card-body">
                    <p style="font-size:13px;color:#667085;margin-bottom:12px">Uji koneksi ke Dapodik Webservice.</p>
                    <div id="testConnectionResult" style="display:none;margin-bottom:12px;padding:12px;border-radius:8px;font-size:13px"></div>
                    <button type="button" class="btn btn-primary btn-block" onclick="testConnection()" id="btnTestConnection">
                        <i class="fas fa-plug"></i> Test Koneksi
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-eye" style="color:var(--gold)"></i> Preview Data</h3>
                    <span class="tag" id="previewStatusTag">Belum Ada</span>
                </div>
                <div class="card-body">
                    <p style="font-size:13px;color:#667085;margin-bottom:12px">Pratinjau data dari Dapodik sebelum sinkron.</p>
                    <div id="previewResult" style="display:none;margin-bottom:12px"></div>
                    <button type="button" class="btn btn-gold btn-block" onclick="previewData()" id="btnPreview">
                        <i class="fas fa-eye"></i> Preview Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Sync --}}
    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <h3><i class="fas fa-arrows-rotate" style="color:var(--ok)"></i> Sinkronisasi</h3>
            <div style="display:flex;gap:8px">
                <button type="button" class="btn btn-ghost btn-sm" onclick="runSync('dry-run')" id="btnDryRun">
                    <i class="fas fa-flask"></i> Dry Run
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="runSync('commit')" id="btnSync">
                    <i class="fas fa-sync"></i> Sinkronisasi
                </button>
            </div>
        </div>
        <div class="card-body">
            <div id="syncResult" style="display:none"></div>
            <div id="syncEmptyState" class="empty-state">
                <div class="icon"><i class="fas fa-arrows-rotate"></i></div>
                <h4>Siap untuk Sinkronisasi</h4>
                <p>Klik <b>Dry Run</b> untuk simulasi atau <b>Sinkronisasi</b> untuk langsung sinkron data.</p>
            </div>
        </div>
    </div>

    {{-- Download Bridge --}}
    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <h3><i class="fas fa-download" style="color:var(--ok)"></i> Download SIMANTAP Bridge</h3>
        </div>
        <div class="card-body">
            <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
                <div style="flex:1;min-width:200px">
                    <p style="font-size:13px;color:#667085;margin:0 0 8px 0">Aplikasi desktop untuk menghubungkan Dapodik lokal dengan server SIMANTAP. Jalankan di laptop yang terinstall Dapodik.</p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <a href="{{ route('admin.dapodik.download-bridge') }}" class="btn btn-primary"><i class="fas fa-download"></i> Download Bridge v3.0</a>
                        <span class="tag tag-secondary" style="display:inline-flex;align-items:center;gap:4px"><i class="fas fa-file"></i> SIMANTAP-Bridge.exe (~41 MB)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Riwayat --}}
    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <h3><i class="fas fa-history" style="color:var(--navy)"></i> Riwayat Sinkronisasi</h3>
            <span class="tag tag-secondary">{{ $logs->count() }} log</span>
        </div>
        <div class="card-body tight">
            @if($logs->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Waktu</th>
                                <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Modul</th>
                                <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Total</th>
                                <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Baru</th>
                                <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Update</th>
                                <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Gagal</th>
                                <th style="padding:10px 12px;text-align:center;font-size:10px;text-transform:uppercase;color:#667085">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($logs as $log)
                                <tr style="border-bottom:1px solid #F2F4F7">
                                    <td style="padding:8px 12px;font-size:12px">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                                    <td style="padding:8px 12px"><span class="tag tag-primary">{{ $log->tipe ?? 'siswa' }}</span></td>
                                    <td style="padding:8px 12px;text-align:center;font-weight:700;font-size:12px">{{ $log->total_data }}</td>
                                    <td style="padding:8px 12px;text-align:center"><span class="tag tag-ok">{{ $log->berhasil }}</span></td>
                                    <td style="padding:8px 12px;text-align:center"><span class="tag tag-gold">{{ $log->diperbarui }}</span></td>
                                    <td style="padding:8px 12px;text-align:center"><span class="tag {{ $log->gagal > 0 ? 'tag-bad' : 'tag-mut' }}">{{ $log->gagal }}</span></td>
                                    <td style="padding:8px 12px;text-align:center">
                                        <a href="{{ route('admin.dapodik.log-detail', $log->id) }}" class="btn btn-ghost btn-sm"><i class="fas fa-eye"></i></a>
                                        <form action="{{ route('admin.dapodik.log-destroy', $log->id) }}" method="POST" style="display:inline" onsubmit="return confirm('Hapus log ini?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-ghost btn-sm" style="color:#DC2626"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <div class="icon"><i class="fas fa-inbox"></i></div>
                    <h4>Belum ada riwayat sinkronisasi</h4>
                    <p>Riwayat akan muncul setelah Anda melakukan sinkronisasi pertama kali.</p>
                </div>
            @endif
        </div>
    </div>

</div>

{{-- ═══════════════ TAB: KELOLA LANGSUNG ═══════════════ --}}
<div id="tabManual" style="display:none">

    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <h3><i class="fas fa-file-import" style="color:var(--gold)"></i> Import Data</h3>
        </div>
        <div class="card-body">
            <p style="font-size:13px;color:#667085;margin-bottom:16px">Gunakan Dapodik Bridge atau import manual dari file CSV/JSON untuk menambahkan data siswa, guru, dan kelas.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
                <div style="padding:16px;background:#EEF2F9;border-radius:10px;border-left:3px solid #1F3864">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                        <i class="fas fa-user-graduate" style="color:#1F3864"></i>
                        <b style="font-size:13px">Siswa</b>
                    </div>
                    <p style="font-size:11px;color:#667085;margin:0">{{ number_format($stats['total_siswa'] ?? 0) }} terdaftar, {{ $stats['dari_dapodik'] ?? 0 }} dari Dapodik</p>
                </div>
                <div style="padding:16px;background:#FBF4E4;border-radius:10px;border-left:3px solid #B8860B">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                        <i class="fas fa-chalkboard-teacher" style="color:#B8860B"></i>
                        <b style="font-size:13px">Guru / PTK</b>
                    </div>
                    <p style="font-size:11px;color:#667085;margin:0">{{ number_format($stats['ptk'] ?? 0) }} PTK, {{ $stats['guru_users'] ?? 0 }} akun aktif</p>
                </div>
                <div style="padding:16px;background:#E6F5F0;border-radius:10px;border-left:3px solid #059669">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                        <i class="fas fa-school" style="color:#059669"></i>
                        <b style="font-size:13px">Kelas / Rombel</b>
                    </div>
                    <p style="font-size:11px;color:#667085;margin:0">{{ number_format($stats['rombel'] ?? 0) }} rombel terdaftar</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-3" style="margin-bottom:20px">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-graduate" style="color:var(--navy)"></i> Data Siswa</h3>
            </div>
            <div class="card-body tight">
                <div style="padding:20px;text-align:center">
                    <div style="font-size:32px;font-weight:800;color:var(--navy)">{{ number_format($stats['total_siswa'] ?? 0) }}</div>
                    <div style="font-size:12px;color:#667085;margin-top:4px">siswa terdaftar</div>
                    <div style="font-size:11px;color:#667085;margin-top:2px">{{ $stats['dari_dapodik'] ?? 0 }} dari Dapodik</div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chalkboard-teacher" style="color:var(--gold)"></i> Data Guru</h3>
            </div>
            <div class="card-body tight">
                <div style="padding:20px;text-align:center">
                    <div style="font-size:32px;font-weight:800;color:var(--gold)">{{ number_format($stats['ptk'] ?? 0) }}</div>
                    <div style="font-size:12px;color:#667085;margin-top:4px">PTK terdaftar</div>
                    <div style="font-size:11px;color:#667085;margin-top:2px">{{ $stats['guru_users'] ?? 0 }} akun aktif</div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-school" style="color:var(--ok)"></i> Data Kelas</h3>
            </div>
            <div class="card-body tight">
                <div style="padding:20px;text-align:center">
                    <div style="font-size:32px;font-weight:800;color:var(--ok)">{{ number_format($stats['rombel'] ?? 0) }}</div>
                    <div style="font-size:12px;color:#667085;margin-top:4px">rombel terdaftar</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <h3><i class="fas fa-list" style="color:var(--navy)"></i> Daftar PTK</h3>
            <span class="tag tag-secondary">{{ $ptkList->count() }} data</span>
        </div>
        <div class="card-body tight">
            @if($ptkList->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Nama</th>
                                <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">NIP</th>
                                <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Jenis PTK</th>
                                <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Jabatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ptkList as $ptk)
                                <tr style="border-bottom:1px solid #F2F4F7">
                                    <td style="padding:8px 12px;font-size:13px;font-weight:600">{{ $ptk->nama }}</td>
                                    <td style="padding:8px 12px;font-size:12px;color:#667085">{{ $ptk->nip ?? '-' }}</td>
                                    <td style="padding:8px 12px"><span class="tag tag-primary">{{ $ptk->jenis_ptk ?? 'Guru' }}</span></td>
                                    <td style="padding:8px 12px;font-size:12px;color:#667085">{{ $ptk->jabatan ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <div class="icon"><i class="fas fa-inbox"></i></div>
                    <h4>Belum ada data PTK</h4>
                    <p>Import data guru dari Dapodik atau input manual.</p>
                </div>
            @endif
        </div>
    </div>

    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <h3><i class="fas fa-users" style="color:var(--gold)"></i> Daftar Rombel</h3>
            <span class="tag tag-secondary">{{ $rombelList->count() }} data</span>
        </div>
        <div class="card-body tight">
            @if($rombelList->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Nama Rombel</th>
                                <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Tingkat</th>
                                <th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;color:#667085">Semester</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rombelList as $rombel)
                                <tr style="border-bottom:1px solid #F2F4F7">
                                    <td style="padding:8px 12px;font-size:13px;font-weight:600">{{ $rombel->nama_rombel }}</td>
                                    <td style="padding:8px 12px;font-size:12px;color:#667085">{{ $rombel->tingkat ?? '-' }}</td>
                                    <td style="padding:8px 12px"><span class="tag tag-primary">{{ $rombel->semester?->semester_id ?? '-' }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <div class="icon"><i class="fas fa-inbox"></i></div>
                    <h4>Belum ada data rombel</h4>
                    <p>Import data kelas dari Dapodik atau input manual.</p>
                </div>
            @endif
        </div>
    </div>

</div>

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const headers = {
    'Content-Type': 'application/json',
    'X-CSRF-TOKEN': csrfToken,
    'Accept': 'application/json'
};

function switchTab(tab) {
    const bridgeTab = document.getElementById('tabBridge');
    const manualTab = document.getElementById('tabManual');
    const btnBridge = document.getElementById('tabBtnBridge');
    const btnManual = document.getElementById('tabBtnManual');

    if (tab === 'bridge') {
        bridgeTab.style.display = 'block';
        manualTab.style.display = 'none';
        btnBridge.style.borderColor = 'var(--navy)';
        btnBridge.style.background = 'linear-gradient(135deg,#1F3864,#2d5a9e)';
        btnBridge.style.color = '#fff';
        btnManual.style.borderColor = '#E4E7EC';
        btnManual.style.background = '#fff';
        btnManual.style.color = '#101828';
    } else {
        bridgeTab.style.display = 'none';
        manualTab.style.display = 'block';
        btnManual.style.borderColor = 'var(--gold)';
        btnManual.style.background = 'linear-gradient(135deg,#B8860B,#D4A017)';
        btnManual.style.color = '#fff';
        btnBridge.style.borderColor = '#E4E7EC';
        btnBridge.style.background = '#fff';
        btnBridge.style.color = '#101828';
    }
}

function showGlobalAlert(msg, type) {
    const el = document.getElementById('globalAlert');
    el.style.display = 'block';
    el.className = 'note note-' + (type === 'success' ? 'ok' : type === 'error' ? 'warn' : 'gold');
    el.innerHTML = (type === 'success' ? '<i class="fas fa-check-circle"></i> ' : type === 'error' ? '<i class="fas fa-exclamation-triangle"></i> ' : '<i class="fas fa-info-circle"></i> ') + msg;
}

function hideGlobalAlert() {
    document.getElementById('globalAlert').style.display = 'none';
}

function toggleToken() {
    const inp = document.getElementById('cfgToken');
    const icon = document.getElementById('tokenEyeIcon');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        inp.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

function setLoading(btnId, loading, originalHtml) {
    const btn = document.getElementById(btnId);
    if (loading) {
        btn.disabled = true;
        btn.dataset.originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
    } else {
        btn.disabled = false;
        btn.innerHTML = originalHtml || btn.dataset.originalHtml || btn.innerHTML;
    }
}

function fmtNumber(n) { return n != null ? Number(n).toLocaleString('id-ID') : '-'; }

async function loadConfig() {
    try {
        const resp = await fetch('{{ route("admin.dapodik.api.config") }}', { headers, method: 'GET' });
        const data = await resp.json();
        if (data.success && data.data) {
            const c = data.data;
            document.getElementById('cfgNpsn').value = c.npsn || '';
            document.getElementById('cfgToken').value = c.token || '';
            document.getElementById('cfgHost').value = c.host || 'localhost';
            document.getElementById('cfgPort').value = c.port || '5774';
            document.getElementById('cfgProtocol').value = c.protocol || 'http';
            document.getElementById('cfgArchiveUnlisted').checked = c.archive_unlisted !== false;
            document.getElementById('configStatusTag').className = 'tag tag-ok';
            document.getElementById('configStatusTag').innerHTML = '<i class="fas fa-check"></i> Terkonfigurasi';
        }
    } catch (e) {}
}

async function saveConfig(e) {
    e.preventDefault();
    hideGlobalAlert();
    setLoading('btnSaveConfig', true);
    try {
        const payload = {
            npsn: document.getElementById('cfgNpsn').value.trim(),
            token: document.getElementById('cfgToken').value.trim(),
            host: document.getElementById('cfgHost').value.trim(),
            port: document.getElementById('cfgPort').value.trim(),
            protocol: document.getElementById('cfgProtocol').value,
            archive_unlisted: document.getElementById('cfgArchiveUnlisted').checked
        };
        const resp = await fetch('{{ route("admin.dapodik.api.config") }}', {
            method: 'POST',
            headers,
            body: JSON.stringify(payload)
        });
        const data = await resp.json();
        if (data.success) {
            showGlobalAlert('Konfigurasi berhasil disimpan.', 'success');
            document.getElementById('configStatusTag').className = 'tag tag-ok';
            document.getElementById('configStatusTag').innerHTML = '<i class="fas fa-check"></i> Terkonfigurasi';
        } else {
            showGlobalAlert(data.message || 'Gagal menyimpan konfigurasi.', 'error');
        }
    } catch (err) {
        showGlobalAlert('Gagal menyimpan konfigurasi. Periksa koneksi.', 'error');
    } finally {
        setLoading('btnSaveConfig', false);
    }
}

async function testConnection() {
    hideGlobalAlert();
    const resultBox = document.getElementById('testConnectionResult');
    const statusTag = document.getElementById('connectionStatusTag');
    resultBox.style.display = 'none';
    setLoading('btnTestConnection', true);
    statusTag.className = 'tag tag-primary';
    statusTag.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menguji...';
    try {
        const resp = await fetch('{{ route("admin.dapodik.api.test-connection") }}', {
            method: 'POST',
            headers,
        });
        const data = await resp.json();
        if (data.success && data.data) {
            resultBox.style.display = 'block';
            resultBox.style.background = '#E6F5F0';
            resultBox.style.border = '1px solid #A7F3D0';
            resultBox.style.borderRadius = '8px';
            resultBox.style.padding = '12px';
            resultBox.innerHTML = '<div style="color:#065F46;font-weight:700;font-size:14px"><i class="fas fa-check-circle"></i> Koneksi Berhasil</div>' +
                '<div style="color:#065F46;font-size:13px;margin-top:4px">' + (data.data.nama_sekolah || data.data.nama || '-') + '</div>' +
                '<div style="color:#065F46;font-size:12px;margin-top:2px">NPSN: ' + (data.data.npsn || '-') + '</div>';
            statusTag.className = 'tag tag-ok';
            statusTag.innerHTML = '<i class="fas fa-check"></i> Terhubung';
        } else {
            resultBox.style.display = 'block';
            resultBox.style.background = '#FEF2F2';
            resultBox.style.border = '1px solid #FECACA';
            resultBox.style.borderRadius = '8px';
            resultBox.style.padding = '12px';
            resultBox.innerHTML = '<div style="color:#991B1B;font-weight:700;font-size:14px"><i class="fas fa-times-circle"></i> Koneksi Gagal</div>' +
                '<div style="color:#991B1B;font-size:13px;margin-top:4px">' + (data.message || 'Periksa konfigurasi host, port, dan token.') + '</div>';
            statusTag.className = 'tag tag-bad';
            statusTag.innerHTML = '<i class="fas fa-times"></i> Gagal';
        }
    } catch (err) {
        resultBox.style.display = 'block';
        resultBox.style.background = '#FEF2F2';
        resultBox.style.border = '1px solid #FECACA';
        resultBox.style.borderRadius = '8px';
        resultBox.style.padding = '12px';
        resultBox.innerHTML = '<div style="color:#991B1B;font-weight:700;font-size:14px"><i class="fas fa-times-circle"></i> Koneksi Gagal</div>' +
            '<div style="color:#991B1B;font-size:13px;margin-top:4px">Tidak dapat terhubung ke server. Pastikan Dapodik berjalan dan host/port benar.</div>';
        statusTag.className = 'tag tag-bad';
        statusTag.innerHTML = '<i class="fas fa-times"></i> Error';
    } finally {
        setLoading('btnTestConnection', false);
    }
}

async function previewData() {
    hideGlobalAlert();
    const resultBox = document.getElementById('previewResult');
    const statusTag = document.getElementById('previewStatusTag');
    resultBox.style.display = 'none';
    setLoading('btnPreview', true);
    statusTag.className = 'tag tag-primary';
    statusTag.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memuat...';
    try {
        const resp = await fetch('{{ route("admin.dapodik.api.preview") }}', {
            method: 'POST',
            headers,
        });
        const data = await resp.json();
        if (data.success && data.data) {
            const d = data.data;
            resultBox.style.display = 'block';
            let html = '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:12px">';
            const sekolah = (d.sekolah && d.sekolah.length > 0) ? d.sekolah[0] : null;
            html += '<div style="padding:14px;background:#EEF2F9;border-radius:10px;border-left:3px solid #1F3864">';
            html += '<div style="font-size:11px;text-transform:uppercase;color:#667085;font-weight:600">Sekolah</div>';
            html += '<div style="font-size:16px;font-weight:800;color:#1F3864;margin-top:4px">' + (sekolah ? (sekolah.nama || sekolah.nama_sekolah || '-') : '-') + '</div>';
            html += '</div>';
            html += '<div style="padding:14px;background:#FBF4E4;border-radius:10px;border-left:3px solid #B8860B">';
            html += '<div style="font-size:11px;text-transform:uppercase;color:#667085;font-weight:600">Total Siswa</div>';
            html += '<div style="font-size:24px;font-weight:800;color:#B8860B;margin-top:4px">' + fmtNumber(d.peserta_didik_count) + '</div>';
            html += '</div>';
            html += '<div style="padding:14px;background:#E6F5F0;border-radius:10px;border-left:3px solid #059669">';
            html += '<div style="font-size:11px;text-transform:uppercase;color:#667085;font-weight:600">Total GTK</div>';
            html += '<div style="font-size:24px;font-weight:800;color:#059669;margin-top:4px">' + fmtNumber(d.gtk_count) + '</div>';
            html += '</div>';
            html += '<div style="padding:14px;background:#FEE9E7;border-radius:10px;border-left:3px solid #DC2626">';
            html += '<div style="font-size:11px;text-transform:uppercase;color:#667085;font-weight:600">Total Rombel</div>';
            html += '<div style="font-size:24px;font-weight:800;color:#DC2626;margin-top:4px">' + fmtNumber(d.rombongan_belajar_count) + '</div>';
            html += '</div>';
            html += '</div>';
            resultBox.innerHTML = html;
            statusTag.className = 'tag tag-ok';
            statusTag.innerHTML = '<i class="fas fa-check"></i> Ada Data';
        } else {
            showGlobalAlert(data.message || 'Gagal memuat preview.', 'error');
            statusTag.className = 'tag tag-bad';
            statusTag.innerHTML = '<i class="fas fa-times"></i> Gagal';
        }
    } catch (err) {
        showGlobalAlert('Gagal mengambil data preview dari Dapodik.', 'error');
        statusTag.className = 'tag tag-bad';
        statusTag.innerHTML = '<i class="fas fa-times"></i> Error';
    } finally {
        setLoading('btnPreview', false);
    }
}

function showSyncModal() {
    document.getElementById('syncModal').style.display = 'flex';
}

function hideSyncModal() {
    document.getElementById('syncModal').style.display = 'none';
}

function setSyncModalStep(steps, currentIdx, status) {
    const body = document.getElementById('syncModalBody');
    let html = '<div style="display:flex;flex-direction:column;gap:2px">';
    for (let i = 0; i < steps.length; i++) {
        const s = steps[i];
        let icon, iconColor, bg;
        if (i < currentIdx) { icon = 'fa-check-circle'; iconColor = '#059669'; bg = '#E6F5F0'; }
        else if (i === currentIdx) {
            if (status === 'error') { icon = 'fa-times-circle'; iconColor = '#DC2626'; bg = '#FEF2F2'; }
            else if (status === 'done') { icon = 'fa-check-circle'; iconColor = '#059669'; bg = '#E6F5F0'; }
            else { icon = 'fa-spinner fa-spin'; iconColor = '#B8860B'; bg = '#FBF4E4'; }
        } else { icon = 'fa-circle'; iconColor = '#D0D5DD'; bg = '#FAFBFD'; }
        html += '<div style="display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:10px;background:' + bg + '">';
        html += '<i class="fas ' + icon + '" style="font-size:16px;color:' + iconColor + ';min-width:20px;text-align:center"></i>';
        html += '<span style="font-size:13px;font-weight:' + (i === currentIdx ? '700' : '500') + ';color:' + (i <= currentIdx ? '#101828' : '#667085') + '">' + s + '</span>';
        html += '</div>';
    }
    html += '</div>';
    body.innerHTML = html;
}

function setSyncModalResult(data, isDryRun) {
    const title = document.getElementById('syncModalTitle');
    const badge = document.getElementById('syncModalBadge');
    const body = document.getElementById('syncModalBody');
    const hasErrors = data.errors && data.errors.length > 0;
    title.innerHTML = '<i class="fas ' + (hasErrors ? 'fa-exclamation-triangle' : 'fa-check-circle') + '" style="color:' + (hasErrors ? '#B8860B' : '#059669') + ';margin-right:8px"></i>' + (isDryRun ? 'Hasil Dry Run' : 'Sinkronisasi Selesai');
    badge.className = 'tag ' + (hasErrors ? 'tag-gold' : 'tag-ok');
    badge.innerHTML = hasErrors ? 'Selesai dengan Error' : 'Berhasil';

    let html = '';
    const categories = [
        { key: 'sekolah', label: 'Sekolah', icon: 'fa-school', color: '#6B7280' },
        { key: 'rombel', label: 'Rombel', icon: 'fa-users', color: '#B8860B' },
        { key: 'gtk', label: 'GTK / Guru & Tendik', icon: 'fa-chalkboard-teacher', color: '#059669' },
        { key: 'siswa', label: 'Siswa', icon: 'fa-user-graduate', color: '#1F3864' },
        { key: 'archive', label: 'Arsip', icon: 'fa-archive', color: '#667085' },
    ];

    for (const cat of categories) {
        const info = data[cat.key];
        if (!info) continue;
        const berhasil = info.berhasil || info.created || 0;
        const diperbarui = info.diperbarui || info.updated || 0;
        const gagal = info.gagal || info.errors || 0;
        const dilewati = info.dilewati || info.skipped || 0;
        const archived = info.archived || 0;
        const total = berhasil + diperbarui + gagal + dilewati + archived;
        const statusIcon = gagal > 0 ? 'fa-exclamation-triangle' : (total > 0 ? 'fa-check-circle' : 'fa-minus-circle');
        const statusColor = gagal > 0 ? '#B8860B' : (total > 0 ? '#059669' : '#667085');
        const statusBg = gagal > 0 ? '#FBF4E4' : (total > 0 ? '#E6F5F0' : '#FAFBFD');

        html += '<div style="padding:14px;background:' + statusBg + ';border:1px solid #E4E7EC;border-radius:10px;border-left:3px solid ' + cat.color + ';margin-bottom:10px">';
        html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">';
        html += '<i class="fas ' + cat.icon + '" style="color:' + cat.color + '"></i>';
        html += '<b style="font-size:13px;flex:1">' + cat.label + '</b>';
        html += '<i class="fas ' + statusIcon + '" style="color:' + statusColor + ';font-size:14px"></i>';
        html += '</div>';
        html += '<div style="display:flex;gap:12px;flex-wrap:wrap;font-size:12px">';
        if (berhasil > 0) html += '<span><b style="color:#059669">' + fmtNumber(berhasil) + '</b> baru</span>';
        if (diperbarui > 0) html += '<span><b style="color:#B8860B">' + fmtNumber(diperbarui) + '</b> diperbarui</span>';
        if (dilewati > 0) html += '<span><b style="color:#667085">' + fmtNumber(dilewati) + '</b> dilewati</span>';
        if (gagal > 0) html += '<span><b style="color:#DC2626">' + fmtNumber(gagal) + '</b> gagal</span>';
        if (archived > 0) html += '<span><b style="color:#667085">' + fmtNumber(archived) + '</b> diarsipkan</span>';
        if (total === 0) html += '<span style="color:#667085">Tidak ada data</span>';
        html += '</div>';
        if (cat.key === 'gtk') {
            const guruCount = info.guru_count || 0;
            const tendikCount = info.tendik_count || 0;
            if (guruCount > 0 || tendikCount > 0) {
                html += '<div style="margin-top:6px;font-size:11px;color:#667085">Guru: ' + guruCount + ' · Tendik: ' + tendikCount + '</div>';
            }
        }
        html += '</div>';
    }

    if (data.errors && data.errors.length > 0) {
        html += '<div style="margin-top:12px;padding:12px;background:#FEF2F2;border:1px solid #FECACA;border-radius:8px">';
        html += '<div style="font-size:12px;font-weight:700;color:#991B1B;margin-bottom:6px"><i class="fas fa-exclamation-triangle"></i> Error Detail</div>';
        for (const err of data.errors.slice(0, 5)) {
            html += '<div style="font-size:11px;color:#991B1B;padding:2px 0">&bull; ' + err + '</div>';
        }
        if (data.errors.length > 5) {
            html += '<div style="font-size:11px;color:#991B1B;font-style:italic">...dan ' + (data.errors.length - 5) + ' error lainnya</div>';
        }
        html += '</div>';
    }

    if (data.duration_seconds != null) {
        html += '<div style="text-align:center;color:#667085;font-size:12px;margin-top:12px"><i class="fas fa-clock"></i> Durasi: ' + data.duration_seconds + ' detik</div>';
    }

    if (isDryRun) {
        html += '<div style="margin-top:12px;padding:12px;background:#FBF4E4;border-radius:8px;font-size:13px;border-left:3px solid #B8860B">';
        html += '<i class="fas fa-info-circle" style="color:#B8860B"></i> Ini simulasi. Tidak ada data yang diubah. Klik <b>"Sinkronisasi"</b> untuk menerapkan.';
        html += '</div>';
    }

    html += '<div style="text-align:center;margin-top:16px"><button onclick="hideSyncModal()" class="btn btn-primary">Tutup</button></div>';
    body.innerHTML = html;
}

async function runSync(mode) {
    hideGlobalAlert();
    const isDryRun = mode === 'dry-run';
    const btnId = isDryRun ? 'btnDryRun' : 'btnSync';
    const resultBox = document.getElementById('syncResult');
    const emptyState = document.getElementById('syncEmptyState');

    const confirmMsg = isDryRun
        ? 'Jalankan simulasi sinkronisasi (Dry Run)?'
        : 'Anda yakin ingin menjalankan sinkronisasi sekarang?';
    if (!confirm(confirmMsg)) return;

    setLoading(btnId, true);
    resultBox.style.display = 'none';
    emptyState.style.display = 'none';

    const steps = ['Mengambil data Sekolah...', 'Mengambil data Siswa...', 'Mengambil data GTK...', 'Mengambil data Rombel...', 'Menyimpan ke database...', 'Selesai'];
    showSyncModal();
    setSyncModalStep(steps, 0, 'loading');

    let stepIdx = 0;
    const stepTimer = setInterval(() => {
        if (stepIdx < steps.length - 2) { stepIdx++; setSyncModalStep(steps, stepIdx, 'loading'); }
    }, 3000);

    try {
        const resp = await fetch('{{ route("admin.dapodik.api.sync") }}?mode=' + mode, { method: 'POST', headers });
        const data = await resp.json();
        clearInterval(stepTimer);
        if (data.success && data.data) {
            setSyncModalStep(steps, steps.length - 1, 'done');
            setTimeout(() => setSyncModalResult(data.data, isDryRun), 500);
            if (!isDryRun) loadStats();
        } else {
            setSyncModalStep(steps, stepIdx, 'error');
            setTimeout(() => {
                const title = document.getElementById('syncModalTitle');
                const badge = document.getElementById('syncModalBadge');
                const body = document.getElementById('syncModalBody');
                title.innerHTML = '<i class="fas fa-exclamation-triangle" style="color:#DC2626;margin-right:8px"></i>Sinkronisasi Gagal';
                badge.className = 'tag tag-bad';
                badge.innerHTML = 'Error';
                let errHtml = '<div style="padding:16px;background:#FEF2F2;border:1px solid #FECACA;border-radius:10px">';
                errHtml += '<div style="font-size:14px;font-weight:700;color:#991B1B;margin-bottom:8px"><i class="fas fa-times-circle"></i> Gagal mengambil data</div>';
                errHtml += '<div style="font-size:13px;color:#991B1B;margin-bottom:12px">' + (data.message || 'Terjadi kesalahan yang tidak diketahui.') + '</div>';
                errHtml += '<div style="padding:10px;background:#fff;border-radius:8px;border:1px solid #FECACA">';
                errHtml += '<div style="font-size:12px;font-weight:600;color:#991B1B;margin-bottom:4px"><i class="fas fa-info-circle"></i> Yang perlu diperiksa:</div>';
                errHtml += '<ul style="font-size:12px;color:#991B1B;margin:0;padding-left:18px">';
                errHtml += '<li>Pastikan aplikasi <b>Dapodik</b> berjalan di komputer Anda</li>';
                errHtml += '<li>Pastikan menu <b>Web Service</b> di Dapodik sudah aktif</li>';
                errHtml += '<li>Periksa <b>Host</b> dan <b>Port</b> sesuai dengan Dapodik</li>';
                errHtml += '<li>Gunakan <b>Token</b> yang benar dari Dapodik</li>';
                errHtml += '</ul></div></div>';
                errHtml += '<div style="text-align:center;margin-top:16px"><button onclick="hideSyncModal()" class="btn btn-primary">Tutup</button></div>';
                body.innerHTML = errHtml;
            }, 800);
        }
    } catch (err) {
        clearInterval(stepTimer);
        setSyncModalStep(steps, stepIdx, 'error');
        setTimeout(() => {
            const title = document.getElementById('syncModalTitle');
            const badge = document.getElementById('syncModalBadge');
            const body = document.getElementById('syncModalBody');
            title.innerHTML = '<i class="fas fa-exclamation-triangle" style="color:#DC2626;margin-right:8px"></i>Koneksi Gagal';
            badge.className = 'tag tag-bad';
            badge.innerHTML = 'Error';
            let errHtml = '<div style="padding:16px;background:#FEF2F2;border:1px solid #FECACA;border-radius:10px">';
            errHtml += '<div style="font-size:14px;font-weight:700;color:#991B1B;margin-bottom:8px"><i class="fas fa-times-circle"></i> Tidak dapat terhubung ke server</div>';
            errHtml += '<div style="font-size:13px;color:#991B1B;margin-bottom:12px">Permintaan sinkronisasi gagal dijangkau. Pastikan server SIMANTAP berjalan dengan benar.</div>';
            errHtml += '</div>';
            errHtml += '<div style="text-align:center;margin-top:16px"><button onclick="hideSyncModal()" class="btn btn-primary">Tutup</button></div>';
            body.innerHTML = errHtml;
            emptyState.style.display = 'block';
        }, 800);
    } finally {
        setLoading(btnId, false);
    }
}

async function loadStats() {
    try {
        const resp = await fetch('{{ route("admin.dapodik.api.config") }}', { headers, method: 'GET' });
        const data = await resp.json();
        if (data.success && data.stats) {
            document.getElementById('statTotalSiswa').textContent = fmtNumber(data.stats.total_siswa);
            document.getElementById('statPtk').textContent = fmtNumber(data.stats.ptk);
            document.getElementById('statRombel').textContent = fmtNumber(data.stats.rombel);
            if (data.stats.sinkron_terakhir) {
                const dt = new Date(data.stats.sinkron_terakhir);
                document.getElementById('statSyncTerakhir').textContent = dt.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
            }
        }
    } catch (e) {}
}

document.addEventListener('DOMContentLoaded', function() {
    loadConfig();
    const totalSiswa = '{{ $stats["total_siswa"] ?? 0 }}';
    const ptk = '{{ $stats["ptk"] ?? 0 }}';
    const guruUsers = '{{ $stats["guru_users"] ?? 0 }}';
    const rombel = '{{ $stats["rombel"] ?? 0 }}';
    const sinkronTerakhir = '{{ $stats["sinkron_terakhir"] ?? "" }}';
    document.getElementById('statTotalSiswa').textContent = fmtNumber(parseInt(totalSiswa));
    document.getElementById('statPtk').textContent = fmtNumber(parseInt(ptk)) + ' / ' + fmtNumber(parseInt(guruUsers)) + ' akun';
    document.getElementById('statRombel').textContent = fmtNumber(parseInt(rombel));
    if (sinkronTerakhir) {
        const dt = new Date(sinkronTerakhir);
        document.getElementById('statSyncTerakhir').textContent = dt.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    } else {
        document.getElementById('statSyncTerakhir').textContent = 'Belum ada';
    }
});
</script>

<div id="syncModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;justify-content:center;align-items:center">
    <div style="background:#fff;border-radius:16px;width:90%;max-width:520px;max-height:85vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3)">
        <div style="padding:20px 24px;border-bottom:1px solid #E4E7EC;display:flex;align-items:center;justify-content:space-between">
            <h3 id="syncModalTitle" style="font-size:16px;font-weight:700;margin:0"><i class="fas fa-sync fa-spin" style="color:var(--navy);margin-right:8px"></i>Sinkronisasi Dapodik</h3>
            <span id="syncModalBadge" class="tag tag-primary">Berjalan</span>
        </div>
        <div style="padding:20px 24px" id="syncModalBody"></div>
    </div>
</div>
@endpush
@endsection
