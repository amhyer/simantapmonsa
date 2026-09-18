@extends('layouts.app')

@section('title', 'Google Sheet Integration - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Integrasi Google Sheet')
@section('page_subtitle', 'Hubungkan data SIMANTAP dengan Google Spreadsheet')

@section('content')
    <div class="grid grid-2" style="margin-bottom:20px">
        <div class="stat-card {{ $terhubung ? 'ok' : 'bad' }}">
            <i class="fas fa-link stat-icon"></i>
            <div class="stat-label">Status Koneksi</div>
            <div class="stat-value" style="font-size:20px">{{ $terhubung ? 'Terhubung' : 'Terputus' }}</div>
            <div class="stat-change">{{ $terhubung ? 'Google Sheet aktif' : 'Belum terhubung' }}</div>
        </div>
        <div class="stat-card primary">
            <i class="fas fa-file-export stat-icon"></i>
            <div class="stat-label">Terakhir Disinkron</div>
            <div class="stat-value" style="font-size:20px">{{ $terakhirSinkron ? $terakhirSinkron->diffForHumans() : 'Belum pernah' }}</div>
            <div class="stat-change">{{ $terakhirSinkron ? $terakhirSinkron->format('d M Y H:i') : '—' }}</div>
        </div>
    </div>

    <div class="grid grid-2" style="margin-bottom:20px">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-plug" style="margin-right:8px;color:var(--navy)"></i>Koneksi Google Sheet</h3>
                @if($terhubung)
                    <span class="tag tag-ok">Aktif</span>
                @else
                    <span class="tag tag-bad">Nonaktif</span>
                @endif
            </div>
            <div class="card-body">
                @if($terhubung)
                    <div class="note note-ok" style="margin-bottom:16px">
                        <i class="fas fa-check-circle"></i>
                        Koneksi ke Google Sheet <b>{{ $spreadsheetName ?? 'Sheet Aktif' }}</b> berhasil.
                    </div>
                    <div style="display:grid;gap:12px;margin-bottom:16px">
                        <div>
                            <label style="font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px">Spreadsheet ID</label>
                            <div style="padding:10px 14px;background:var(--bg);border-radius:8px;font-family:monospace;font-size:13px;margin-top:4px;word-break:break-all">
                                {{ $spreadsheetId ?? '—' }}
                            </div>
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px">URL Spreadsheet</label>
                            <div style="margin-top:4px">
                                <a href="{{ $spreadsheetUrl ?? '#' }}" target="_blank" style="color:var(--navy);font-weight:600;font-size:13px;text-decoration:none">
                                    {{ $spreadsheetUrl ?? '—' }}
                                    <i class="fas fa-external-link-alt" style="margin-left:4px;font-size:11px"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div style="display:flex;gap:8px">
                        <form action="{{ route('guru.integrasi.disconnect') }}" method="POST" onsubmit="return confirm('Putuskan koneksi ke Google Sheet?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-unlink"></i> Putuskan
                            </button>
                        </form>
                        <form action="{{ route('guru.integrasi.full-sync') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-sync-alt"></i> Sync Semua Data
                            </button>
                        </form>
                    </div>
                @else
                    <div class="note note-warn" style="margin-bottom:16px">
                        <i class="fas fa-exclamation-triangle"></i>
                        Belum terhubung ke Google Sheet. Hubungkan untuk mengekspor data otomatis.
                    </div>
                    <form action="{{ route('guru.integrasi.connect') }}" method="POST">
                        @csrf
                        <div style="display:grid;gap:12px;margin-bottom:16px">
                            <div>
                                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Google Spreadsheet URL atau ID</label>
                                <input type="text" name="spreadsheet_url" required
                                       placeholder="https://docs.google.com/spreadsheets/d/... atau Spreadsheet ID"
                                       value="{{ old('spreadsheet_url') }}"
                                       style="width:100%;padding:10px 14px;border:1px solid var(--line);border-radius:8px;font-size:13px">
                                @error('spreadsheet_url')
                                    <div style="color:var(--bad);font-size:12px;margin-top:4px">{{ $message }}</div>
                                @enderror
                            </div>
                            <div>
                                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Nama Sheet di Spreadsheet</label>
                                <input type="text" name="sheet_name" required
                                       placeholder="Contoh: Sheet1, Nilai, Data"
                                       value="{{ old('sheet_name', 'Sheet1') }}"
                                       style="width:100%;padding:10px 14px;border:1px solid var(--line);border-radius:8px;font-size:13px">
                                @error('sheet_name')
                                    <div style="color:var(--bad);font-size:12px;margin-top:4px">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-link"></i> Hubungkan Google Sheet
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-cogs" style="margin-right:8px;color:var(--gold)"></i>Pengaturan Sinkronisasi</h3>
            </div>
            <div class="card-body">
                <div class="note" style="margin-bottom:16px">
                    <i class="fas fa-info-circle"></i>
                    Pilih data yang ingin disinkronkan ke Google Sheet.
                </div>
                <form action="{{ route('guru.integrasi.update-settings') }}" method="POST">
                    @csrf
                    <div style="display:grid;gap:12px;margin-bottom:16px">
                        <label style="display:flex;align-items:center;gap:10px;padding:12px 16px;border:1.5px solid var(--line);border-radius:10px;cursor:pointer;{{ ($sinkronNilai ?? true) ? 'border-color:var(--navy);background:#EEF2F9;' : '' }}">
                            <input type="checkbox" name="sinkron_nilai" value="1" {{ ($sinkronNilai ?? true) ? 'checked' : '' }} style="width:18px;height:18px;accent-color:var(--navy)">
                            <div>
                                <b style="font-size:14px">Nilai Siswa</b>
                                <div style="font-size:12px;color:var(--muted)">Semua data nilai dari kuis dan penilaian</div>
                            </div>
                        </label>
                        <label style="display:flex;align-items:center;gap:10px;padding:12px 16px;border:1.5px solid var(--line);border-radius:10px;cursor:pointer;{{ ($sinkronKehadiran ?? true) ? 'border-color:var(--navy);background:#EEF2F9;' : '' }}">
                            <input type="checkbox" name="sinkron_kehadiran" value="1" {{ ($sinkronKehadiran ?? true) ? 'checked' : '' }} style="width:18px;height:18px;accent-color:var(--navy)">
                            <div>
                                <b style="font-size:14px">Kehadiran</b>
                                <div style="font-size:12px;color:var(--muted)">Data kehadiran harian siswa</div>
                            </div>
                        </label>
                        <label style="display:flex;align-items:center;gap:10px;padding:12px 16px;border:1.5px solid var(--line);border-radius:10px;cursor:pointer;{{ ($sinkronKebiasaan ?? false) ? 'border-color:var(--navy);background:#EEF2F9;' : '' }}">
                            <input type="checkbox" name="sinkron_kebiasaan" value="1" {{ ($sinkronKebiasaan ?? false) ? 'checked' : '' }} style="width:18px;height:18px;accent-color:var(--navy)">
                            <div>
                                <b style="font-size:14px">7 Kebiasaan</b>
                                <div style="font-size:12px;color:var(--muted)">Laporan kebiasaan dari orang tua</div>
                            </div>
                        </label>
                        <label style="display:flex;align-items:center;gap:10px;padding:12px 16px;border:1.5px solid var(--line);border-radius:10px;cursor:pointer;{{ ($sinkronDimensi ?? false) ? 'border-color:var(--navy);background:#EEF2F9;' : '' }}">
                            <input type="checkbox" name="sinkron_dimensi" value="1" {{ ($sinkronDimensi ?? false) ? 'checked' : '' }} style="width:18px;height:18px;accent-color:var(--navy)">
                            <div>
                                <b style="font-size:14px">Profil Lulusan</b>
                                <div style="font-size:12px;color:var(--muted)">Skor dimensi Profil Pelajar Pancasila</div>
                            </div>
                        </label>
                    </div>
                    <button type="submit" class="btn btn-ghost btn-block">
                        <i class="fas fa-save"></i> Simpan Pengaturan
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history" style="margin-right:8px;color:var(--navy)"></i>Riwayat Sinkronisasi</h3>
        </div>
        <div class="card-body tight">
            @if(count($riwayatSinkron ?? []))
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Jenis Data</th>
                                <th style="text-align:center">Jumlah Baris</th>
                                <th style="text-align:center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($riwayatSinkron as $riwayat)
                                <tr>
                                    <td>{{ $riwayat['waktu'] instanceof \Carbon\Carbon ? $riwayat['waktu']->format('d M Y H:i') : $riwayat['waktu'] }}</td>
                                    <td>
                                        <span class="tag tag-primary">{{ $riwayat['jenis'] }}</span>
                                    </td>
                                    <td style="text-align:center">{{ $riwayat['jumlah_baris'] }}</td>
                                    <td style="text-align:center">
                                        @if($riwayat['status'] === 'berhasil')
                                            <span class="tag tag-ok">Berhasil</span>
                                        @else
                                            <span class="tag tag-bad">Gagal</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <div class="icon">🔄</div>
                    <h4>Belum ada riwayat</h4>
                    <p>Riwayat sinkronisasi akan muncul di sini.</p>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    const label = this.closest('label');
                    if (this.checked) {
                        label.style.borderColor = 'var(--navy)';
                        label.style.background = '#EEF2F9';
                    } else {
                        label.style.borderColor = 'var(--line)';
                        label.style.background = '#fff';
                    }
                });
            });
        });
    </script>
    @endpush
@endsection
