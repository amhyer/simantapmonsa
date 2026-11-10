@extends('layouts.admin-with-tabs')

@section('title', 'Presensi Pegawai - SIMANTAP')
@section('page_title', 'Presensi Pegawai')
@section('page_subtitle', 'Atur area absensi dan pantau presensi guru, kepala sekolah, serta tendik')

@section('header_actions')
    <a href="{{ route('admin.presensi.mesin.index') }}" class="btn btn-primary btn-sm">
        <i class="fas fa-file-import"></i> Import Presensi Mesin
    </a>
    <a href="{{ route('admin.presensi.laporan') }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-file-alt"></i> Laporan
    </a>
@endsection

@push('styles')
<style>
    .presensi-stat-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 20px;
    }
    @media (max-width: 1024px) {
        .presensi-stat-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
    }
    @media (max-width: 576px) {
        .presensi-stat-grid { grid-template-columns: 1fr; gap: 10px; }
    }

    .presensi-stat-card {
        background: #fff;
        border: 1px solid #E2E8F0;
        border-radius: 12px;
        padding: 16px 18px;
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .presensi-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    .presensi-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        bottom: 0;
        width: 4px;
    }
    .presensi-stat-card.stat-navy::before { background: #1F3864; }
    .presensi-stat-card.stat-ok::before { background: #10B981; }
    .presensi-stat-card.stat-warn::before { background: #F59E0B; }
    .presensi-stat-card.stat-info::before { background: #3B82F6; }
    .presensi-stat-card.stat-purple::before { background: #8B5CF6; }

    .presensi-stat-card .card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 8px;
    }
    .presensi-stat-card .card-label {
        font-size: 11.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748B;
    }
    .presensi-stat-card .card-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
    }
    .stat-navy .card-icon { background: #EEF2F9; color: #1F3864; }
    .stat-ok .card-icon { background: #ECFDF5; color: #10B981; }
    .stat-warn .card-icon { background: #FFFBEB; color: #D97706; }
    .stat-info .card-icon { background: #EFF6FF; color: #2563EB; }
    .stat-purple .card-icon { background: #F5F3FF; color: #7C3AED; }

    .presensi-stat-card .card-val {
        font-size: 28px;
        font-weight: 800;
        color: #1E293B;
        line-height: 1.1;
    }
    .stat-ok .card-val { color: #059669; }
    .stat-warn .card-val { color: #D97706; }
    .stat-info .card-val { color: #2563EB; }
    .stat-purple .card-val { color: #7C3AED; }

    .responsive-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        padding: 14px 20px;
        background: #F8FAFC;
        border-bottom: 1px solid #E2E8F0;
    }
    .responsive-header h3 {
        margin: 0;
        font-size: 15px;
        font-weight: 700;
        color: #1E293B;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .form-grid-responsive {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
        align-items: end;
    }

    .koreksi-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px;
    }
    @media (max-width: 576px) {
        .koreksi-form-grid { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')

    {{-- ═══════════════ REKAP STATISTIK UMUM ═══════════════ --}}
    <div class="presensi-stat-grid">
        <div class="presensi-stat-card stat-navy">
            <div class="card-head">
                <span class="card-label">Pegawai Aktif</span>
                <div class="card-icon"><i class="fas fa-users"></i></div>
            </div>
            <div class="card-val">{{ $rekap['total_pegawai'] ?? $rekap->total_pegawai }}</div>
        </div>
        <div class="presensi-stat-card stat-ok">
            <div class="card-head">
                <span class="card-label">Hadir / Tepat Waktu</span>
                <div class="card-icon"><i class="fas fa-check-circle"></i></div>
            </div>
            <div class="card-val">{{ $rekap['hadir'] ?? $rekap->hadir }}</div>
        </div>
        <div class="presensi-stat-card stat-warn">
            <div class="card-head">
                <span class="card-label">Belum Tercatat</span>
                <div class="card-icon"><i class="fas fa-clock"></i></div>
            </div>
            <div class="card-val">{{ $rekap['belum_tercatat'] ?? $rekap->belum_tercatat }}</div>
        </div>
        <div class="presensi-stat-card stat-purple">
            <div class="card-head">
                <span class="card-label">Belum Pulang</span>
                <div class="card-icon"><i class="fas fa-sign-out-alt"></i></div>
            </div>
            <div class="card-val">{{ $rekap['belum_pulang'] ?? $rekap->belum_pulang }}</div>
        </div>
    </div>

    @if(($rekap['belum_tercatat'] ?? $rekap->belum_tercatat) > 0)
        <div class="note note-warn" style="margin-bottom:18px;display:flex;align-items:center;gap:8px">
            <i class="fas fa-exclamation-triangle"></i>
            <span><strong>{{ $rekap['belum_tercatat'] ?? $rekap->belum_tercatat }} pegawai</strong> belum memiliki catatan pada tanggal ini. Catatan belum tercatat tidak otomatis berarti alpa.</span>
        </div>
    @endif

    {{-- ═══════════════ RINGKASAN PRESENSI MESIN (FINGERPRINT) ═══════════════ --}}
    <div class="card" style="margin-bottom:20px;border:1px solid #E2E8F0;box-shadow:0 1px 4px rgba(0,0,0,0.04)">
        <div class="responsive-header">
            <h3>
                <i class="fas fa-fingerprint" style="color:#2563EB"></i>
                Ringkasan Presensi Mesin
            </h3>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                @if(request()->routeIs('admin.presensi.mesin.ringkasan'))
                    <form method="POST" action="{{ route('admin.presensi.mesin.ringkasan.flush') }}" style="margin:0">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-ghost"><i class="fas fa-undo"></i> Reset ringkasan</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('admin.presensi.mesin.ringkasan.pull') }}" style="margin:0" id="pull-ringkasan">
                    @csrf
                    <input type="hidden" name="tanggal" value="{{ $tanggal }}">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-sync-alt"></i> Tarik presensi mesin
                    </button>
                </form>
                <a href="{{ route('admin.presensi.mesin.index') }}" class="btn btn-secondary btn-sm">
                    <i class="fas fa-file-excel"></i> Import File Mesin
                </a>
            </div>
        </div>
        <div class="card-body" style="padding:18px 20px">
            <p style="margin:0 0 16px;color:#64748B;font-size:12.5px;line-height:1.55">
                Ringkasan berikut dibaca dari data presensi mesin (fingerprint) yang sudah terimpor. Cron <code>presensi:mesin-sync</code> akan mengisi/refresh ringkasan ini secara berkala tanpa menghapus koreksi manual admin.
            </p>

            <div class="presensi-stat-grid" style="margin-bottom:16px">
                <div class="presensi-stat-card stat-ok">
                    <div class="card-head">
                        <span class="card-label">Hadir / Terlambat</span>
                        <div class="card-icon"><i class="fas fa-user-check"></i></div>
                    </div>
                    <div class="card-val">{{ $rekap['hadir'] ?? $rekap->hadir }}</div>
                </div>
                <div class="presensi-stat-card stat-warn">
                    <div class="card-head">
                        <span class="card-label">Belum Tercatat</span>
                        <div class="card-icon"><i class="fas fa-user-clock"></i></div>
                    </div>
                    <div class="card-val">{{ $rekap['belum_tercatat'] ?? $rekap->belum_tercatat }}</div>
                </div>
                <div class="presensi-stat-card stat-info">
                    <div class="card-head">
                        <span class="card-label">Izin / Sakit / Cuti</span>
                        <div class="card-icon"><i class="fas fa-notes-medical"></i></div>
                    </div>
                    <div class="card-val">{{ $rekap['izin_sakit_cuti'] ?? $rekap->izin_sakit_cuti }}</div>
                </div>
                <div class="presensi-stat-card stat-purple">
                    <div class="card-head">
                        <span class="card-label">Belum Pulang</span>
                        <div class="card-icon"><i class="fas fa-door-open"></i></div>
                    </div>
                    <div class="card-val">{{ $rekap['belum_pulang'] ?? $rekap->belum_pulang }}</div>
                </div>
            </div>

            @if(isset($pulled) && $pulled)
                <div class="note note-ok" style="margin-bottom:14px"><i class="fas fa-check-circle"></i> Ringkasan berhasil diperbarui dari mesin.</div>
            @endif

            <div class="table-wrapper" style="border:1px solid #E2E8F0;border-radius:8px;overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead>
                        <tr style="background:#F8FAFC;border-bottom:1px solid #E2E8F0">
                            <th style="padding:10px 14px;text-align:left;font-weight:700;color:#475569;font-size:11.5px;text-transform:uppercase">Nama Pegawai</th>
                            <th style="padding:10px 14px;text-align:left;font-weight:700;color:#475569;font-size:11.5px;text-transform:uppercase">Status</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:700;color:#475569;font-size:11.5px;text-transform:uppercase">Masuk</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:700;color:#475569;font-size:11.5px;text-transform:uppercase">Pulang</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:700;color:#475569;font-size:11.5px;text-transform:uppercase">Sumber Data</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($catatan as $person)
                        @php $record = $catatan->get($person->id); @endphp
                        <tr style="border-bottom:1px solid #F1F5F9">
                            <td style="padding:10px 14px;white-space:nowrap">
                                <strong style="color:#1E293B">{{ $person->nama_lengkap }}</strong><br>
                                <small style="color:#64748B">{{ ucfirst($person->peran) }}</small>
                            </td>
                            <td style="padding:10px 14px">
                                @if(!$record)
                                    <span class="tag tag-mut">Belum tercatat</span>
                                @else
                                    <span class="tag {{ in_array($record->status, ['hadir', 'terlambat'], true) ? 'tag-ok' : 'tag-warn' }}">
                                        {{ ucfirst($record->status) }}
                                    </span>
                                @endif
                            </td>
                            <td style="padding:10px 14px;text-align:center;font-family:monospace;white-space:nowrap">
                                {{ $record?->waktu_masuk?->format('H:i:s') ?? '—' }}
                            </td>
                            <td style="padding:10px 14px;text-align:center;font-family:monospace;white-space:nowrap">
                                {{ $record?->waktu_pulang?->format('H:i:s') ?? '—' }}
                            </td>
                            <td style="padding:10px 14px;text-align:center;font-size:12px;color:#64748B">
                                @if($record?->metode === 'mesin')
                                    <span class="badge" style="background:#EFF6FF;color:#2563EB"><i class="fas fa-fingerprint"></i> Mesin Fingerprint</span>
                                @elseif($record?->metode === 'machine')
                                    <span class="badge" style="background:#EFF6FF;color:#2563EB"><i class="fas fa-fingerprint"></i> Mesin (Auto)</span>
                                @else
                                    {{ $record?->metode ?? '-' }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align:center;padding:28px 16px;color:#64748B">
                                <div style="font-size:24px;margin-bottom:6px;color:#94A3B8"><i class="fas fa-inbox"></i></div>
                                <div>Belum ada data presensi mesin untuk hari ini.</div>
                                <div style="font-size:12px;color:#94A3B8;margin-top:4px">Klik tombol "Tarik presensi mesin" atau import berkas fingerprint untuk memuat data.</div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ═══════════════ REKAP & KOREKSI PEGAWAI HARIAN ═══════════════ --}}
    <div class="card" style="margin-bottom:20px;border:1px solid #E2E8F0;box-shadow:0 1px 4px rgba(0,0,0,0.04)">
        <div class="responsive-header">
            <h3>
                <i class="fas fa-calendar-check" style="color:#1F3864"></i>
                Rekap Pegawai — {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('l, d F Y') }}
            </h3>
            <form method="GET" action="{{ route('admin.presensi.index') }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <input type="date" name="tanggal" value="{{ $tanggal }}" required style="padding:6px 10px;border:1px solid #CBD5E1;border-radius:6px;font-size:13px">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Tampilkan</button>
            </form>
        </div>
        <div class="card-body" style="padding:18px 20px">
            <p style="margin:0 0 14px;color:#64748B;font-size:12.5px">
                Admin dapat memeriksa jam kedatangan/kepulangan serta memperbaiki status kehadiran pegawai dengan mengisi alasan koreksi. Semua perubahan dicatat dalam Log Aktivitas.
            </p>
            <div class="table-wrapper" style="border:1px solid #E2E8F0;border-radius:8px;overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead>
                        <tr style="background:#F8FAFC;border-bottom:1px solid #E2E8F0">
                            <th style="padding:10px 14px;text-align:left;font-weight:700;color:#475569;font-size:11.5px;text-transform:uppercase">Nama / Peran</th>
                            <th style="padding:10px 14px;text-align:left;font-weight:700;color:#475569;font-size:11.5px;text-transform:uppercase">Masuk</th>
                            <th style="padding:10px 14px;text-align:left;font-weight:700;color:#475569;font-size:11.5px;text-transform:uppercase">Pulang</th>
                            <th style="padding:10px 14px;text-align:left;font-weight:700;color:#475569;font-size:11.5px;text-transform:uppercase">Metode</th>
                            <th style="padding:10px 14px;text-align:left;font-weight:700;color:#475569;font-size:11.5px;text-transform:uppercase;min-width:300px">Koreksi Admin</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($pegawai as $person)
                        @php $record = $catatan->get($person->id); @endphp
                        <tr style="border-bottom:1px solid #F1F5F9">
                            <td style="padding:12px 14px;white-space:nowrap">
                                <strong style="color:#1E293B">{{ $person->nama_lengkap }}</strong><br>
                                <small style="color:#64748B">{{ ucfirst($person->peran) }} · {{ $person->nama_pengguna }}</small>
                            </td>
                            <td style="padding:12px 14px;white-space:nowrap">
                                <span style="font-family:monospace;font-weight:600">{{ $record?->waktu_masuk?->format('H:i:s') ?? '—' }}</span><br>
                                <small style="color:#64748B">{{ $record?->lokasiMasuk?->nama ?? '' }}</small>
                            </td>
                            <td style="padding:12px 14px;white-space:nowrap">
                                <span style="font-family:monospace;font-weight:600">{{ $record?->waktu_pulang?->format('H:i:s') ?? '—' }}</span><br>
                                <small style="color:#64748B">{{ $record?->lokasiPulang?->nama ?? '' }}</small>
                            </td>
                            <td style="padding:12px 14px">
                                @if(!$record)
                                    <span class="tag tag-mut">Belum tercatat</span>
                                @else
                                    <span class="tag {{ in_array($record->status, ['hadir', 'terlambat'], true) ? 'tag-ok' : 'tag-warn' }}">
                                        {{ ucfirst($record->status) }}
                                    </span>
                                @endif
                                @if($record?->metode === 'qr')<br><small style="color:#64748B">Scan QR</small>
                                @elseif($record?->metode === 'qr+gps')<br><small style="color:#64748B">QR + GPS</small>
                                @elseif($record?->metode === 'gps')<br><small style="color:#64748B">GPS Geofence</small>
                                @elseif($record?->metode === 'mesin')<br><small style="color:#64748B">Mesin Fingerprint</small>
                                @elseif($record?->metode === 'admin')<br><small style="color:#64748B">Koreksi Admin</small>@endif
                            </td>
                            <td style="padding:12px 14px">
                                <form method="POST" action="{{ route('admin.presensi.pegawai.koreksi') }}" class="koreksi-form-grid">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $person->id }}">
                                    <input type="hidden" name="tanggal" value="{{ $tanggal }}">
                                    <select name="status" required style="padding:6px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px">
                                        <option value="hadir" @selected(($record?->status ?? 'hadir') === 'hadir')>Hadir</option>
                                        <option value="terlambat" @selected($record?->status === 'terlambat')>Terlambat</option>
                                        <option value="izin" @selected($record?->status === 'izin')>Izin</option>
                                        <option value="sakit" @selected($record?->status === 'sakit')>Sakit</option>
                                        <option value="alpa" @selected($record?->status === 'alpa')>Alpa</option>
                                    </select>
                                    <input type="time" name="jam_masuk" value="{{ $record?->waktu_masuk?->format('H:i') }}" aria-label="Jam masuk" style="padding:6px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px">
                                    <input type="time" name="jam_pulang" value="{{ $record?->waktu_pulang?->format('H:i') }}" aria-label="Jam pulang" style="padding:6px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px">
                                    <input name="alasan_koreksi" required maxlength="500" placeholder="Alasan koreksi" value="{{ old('alasan_koreksi') }}" style="padding:6px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px">
                                    <button class="btn btn-secondary btn-sm" type="submit" style="grid-column:1/-1;padding:6px">
                                        <i class="fas fa-save"></i> Simpan koreksi
                                    </button>
                                </form>
                                @if($record?->alasan_koreksi)
                                    <small style="color:#D97706;display:block;margin-top:4px">Catatan: {{ $record->alasan_koreksi }}</small>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align:center;padding:24px;color:#64748B">
                                Belum ada akun pegawai aktif.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ═══════════════ KELOLA AREA LOKASI ABSENSI ═══════════════ --}}
    <div class="card" style="margin-bottom:20px;border:1px solid #E2E8F0;box-shadow:0 1px 4px rgba(0,0,0,0.04)">
        <div class="responsive-header">
            <h3>📍 Titik Lokasi yang Diizinkan untuk Absensi (Geofence)</h3>
        </div>
        <div class="card-body" style="padding:18px 20px">
            <p style="margin:0 0 16px;color:#64748B;font-size:12.5px;line-height:1.55">
                Pegawai hanya dapat mencatat presensi masuk/pulang dari titik aktif dalam radius yang ditentukan. Gunakan lokasi perangkat admin atau isi koordinat secara manual.
            </p>
            <form action="{{ route('admin.presensi.lokasi.store') }}" method="POST" class="form-grid-responsive">
                @csrf
                <div>
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Nama titik *</label>
                    <input name="nama" required maxlength="100" placeholder="Contoh: Gedung Sekolah" style="width:100%;padding:8px 10px;border:1px solid #CBD5E1;border-radius:6px">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Latitude *</label>
                    <input id="new-latitude" name="latitude" required type="number" step="any" min="-90" max="90" placeholder="-5.1476" style="width:100%;padding:8px 10px;border:1px solid #CBD5E1;border-radius:6px">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Longitude *</label>
                    <input id="new-longitude" name="longitude" required type="number" step="any" min="-180" max="180" placeholder="119.4327" style="width:100%;padding:8px 10px;border:1px solid #CBD5E1;border-radius:6px">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Radius (meter) *</label>
                    <input name="radius_meter" required type="number" min="20" max="5000" value="150" style="width:100%;padding:8px 10px;border:1px solid #CBD5E1;border-radius:6px">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Akurasi GPS maks. (m)</label>
                    <input name="akurasi_maksimal_meter" required type="number" min="10" max="1000" value="150" style="width:100%;padding:8px 10px;border:1px solid #CBD5E1;border-radius:6px">
                </div>
                <div style="display:flex;align-items:center;gap:8px;padding-bottom:8px">
                    <input type="checkbox" name="aktif" id="titik-aktif" value="1" checked style="width:18px;height:18px">
                    <label for="titik-aktif" style="margin:0;font-size:13px;font-weight:600;cursor:pointer">Status Aktif</label>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <button type="button" class="btn btn-ghost btn-sm" id="get-admin-location">
                        <i class="fas fa-crosshairs"></i> Lokasi Perangkat
                    </button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Tambah Titik
                    </button>
                </div>
            </form>
            @if($errors->any())
                <div class="note note-bad" style="margin-top:14px">{{ $errors->first() }}</div>
            @endif
        </div>
    </div>

    @if($lokasi->isNotEmpty())
        <div class="card" style="margin-bottom:20px;border:1px solid #E2E8F0;box-shadow:0 1px 4px rgba(0,0,0,0.04)">
            <div class="responsive-header">
                <h3>Daftar Titik Lokasi Presensi Terdaftar</h3>
            </div>
            <div class="card-body tight">
                <div class="table-wrapper" style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:13px">
                        <thead>
                            <tr style="background:#F8FAFC;border-bottom:1px solid #E2E8F0">
                                <th style="padding:10px 14px;text-align:left;font-weight:700;color:#475569">Nama Titik</th>
                                <th style="padding:10px 14px;text-align:left;font-weight:700;color:#475569">Koordinat</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:700;color:#475569">Radius</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:700;color:#475569">Akurasi</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:700;color:#475569">Status</th>
                                <th style="padding:10px 14px;text-align:right;font-weight:700;color:#475569">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($lokasi as $zone)
                            <tr style="border-bottom:1px solid #F1F5F9">
                                <td style="padding:12px 14px;font-weight:600;color:#1E293B">{{ $zone->nama }}</td>
                                <td style="padding:12px 14px;font-family:monospace;font-size:12px;color:#475569">
                                    {{ number_format($zone->latitude, 6) }}, {{ number_format($zone->longitude, 6) }}
                                </td>
                                <td style="padding:12px 14px;text-align:center">{{ $zone->radius_meter }} m</td>
                                <td style="padding:12px 14px;text-align:center">{{ $zone->akurasi_maksimal_meter }} m</td>
                                <td style="padding:12px 14px;text-align:center">
                                    <span class="badge {{ $zone->aktif ? 'badge-ok' : 'badge-mut' }}">
                                        {{ $zone->aktif ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td style="padding:12px 14px;text-align:right">
                                    <form action="{{ route('admin.presensi.lokasi.destroy', $zone) }}" method="POST" style="display:inline-block" onsubmit="return confirm('Hapus titik lokasi {{ addslashes($zone->nama) }}?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-ghost btn-sm" style="color:#DC2626;padding:4px 8px">
                                            <i class="fas fa-trash-alt"></i> Hapus
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

@endsection

@push('scripts')
<script>
(() => {
    const button = document.getElementById('get-admin-location');
    if (!button) return;
    button.addEventListener('click', () => {
        if (!navigator.geolocation) { alert('Browser tidak mendukung GPS.'); return; }
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengambil lokasi…';
        navigator.geolocation.getCurrentPosition((position) => {
            document.getElementById('new-latitude').value = position.coords.latitude.toFixed(7);
            document.getElementById('new-longitude').value = position.coords.longitude.toFixed(7);
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-crosshairs"></i> Gunakan lokasi perangkat';
        }, (error) => {
            alert(error.message || 'Gagal mendapatkan lokasi perangkat.');
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-crosshairs"></i> Gunakan lokasi perangkat';
        }, { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 });
    });
})();
</script>
@endpush
