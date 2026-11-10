@extends('layouts.admin-with-tabs')

@section('title', 'Sinkron Presensi Mesin - SIMANTAP')
@section('page_title', 'Sinkron Presensi Mesin')
@section('page_subtitle', 'Riwayat hasil sinkron mesin absensi via Presensi Bridge')

@section('content')
    <div class="note note-ok" style="margin-bottom:16px">
        <i class="fas fa-link"></i>
        <span>
            SIMANTAP berjalan di hosting, sehingga mesin absensi dibaca lewat <strong>Presensi Bridge</strong>
            di PC sekolah (<code>presensi-bridge/</code>): script membaca database Attendance Management
            (<code>att2000.mdb</code>), lalu mengirim scan ke website melalui
            <code>POST /api/presensi/mesin/sinkron</code>. Hasil sinkron otomatis terekam di bawah.
        </span>
    </div>

    @if($directDisabled)
        <div class="note note-warn" style="margin-bottom:16px">
            Tarik langsung dari database mesin dinonaktifkan untuk sekolah bertenant. Gunakan Presensi Bridge
            yang terjadwal di PC sekolah (Task Scheduler) sebagai jalur sinkron resmi.
        </div>
    @endif

    <div class="grid grid-4" style="margin-bottom:16px">
        <div class="stat {{ $terakhir && $terakhir->status === 'berhasil' ? 'ok' : 'bad' }}">
            <div class="label">Status Sinkron Terakhir</div>
            <div class="value" style="font-size:22px">{{ $terakhir ? ucfirst((string) $terakhir->status) : 'Belum Ada' }}</div>
            <div class="desc">{{ $terakhir ? \Illuminate\Support\Carbon::parse($terakhir->created_at)->translatedFormat('d M Y, H:i') : 'Belum ada riwayat sinkron' }}</div>
        </div>
        <div class="stat">
            <div class="label">Jumlah Scan Terakhir</div>
            <div class="value">{{ $terakhir ? (int) $terakhir->jumlah_scan : 0 }}</div>
            <div class="desc">Dari bridge {{ $terakhir?->source ?? '-' }}</div>
        </div>
        <div class="stat ok">
            <div class="label">Data Dibuat / Diperbarui</div>
            <div class="value" style="font-size:22px">{{ ((int) ($terakhir?->dibuat ?? 0)) + ((int) ($terakhir?->diperbarui ?? 0)) }}</div>
            <div class="desc">Dibuat: {{ $terakhir ? (int) $terakhir->dibuat : 0 }} · Diperbarui: {{ $terakhir ? (int) $terakhir->diperbarui : 0 }}</div>
        </div>
        <div class="stat warn">
            <div class="label">Dilindungi / Tidak Dikenal</div>
            <div class="value" style="font-size:22px">{{ ((int) ($terakhir?->dilindungi ?? 0)) + ((int) ($terakhir?->nik_tidak_dikenal ?? 0)) }}</div>
            <div class="desc">Dilindungi: {{ $terakhir ? (int) $terakhir->dilindungi : 0 }} · NIK tidak dikenal: {{ $terakhir ? (int) $terakhir->nik_tidak_dikenal : 0 }}</div>
        </div>
    </div>

    @if($terakhir)
        <div class="card" style="margin-bottom:16px;border-left:4px solid {{ $terakhir->status === 'berhasil' ? '#12805C' : '#B42318' }}">
            <div class="card-header" style="gap:12px;flex-wrap:wrap">
                <h3 style="margin-right:auto">Hasil Sinkron Terakhir</h3>
                <span class="badge {{ $terakhir->status === 'berhasil' ? 'badge-success' : 'badge-danger' }}">
                    {{ $terakhir->status === 'berhasil' ? 'Berhasil' : 'Gagal' }}
                </span>
                <form action="{{ route('admin.presensi.mesin.tarik.semua') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-sm" style="background:#12805C;color:#fff" onclick="return confirm('Tarik SEMUA data presensi dari database mesin sekarang?')">Tarik Semua Data (1 Klik)</button>
                </form>
            </div>
            <div class="card-body">
                @if($terakhir->status === 'gagal' && filled($terakhir->pesan_error))
                    <div style="background:#FEF3E2;border:1px solid #F5C27A;color:#B42318;border-radius:8px;padding:10px 12px;font-size:13px;margin-bottom:12px">
                        <strong>Pesan error:</strong> {{ $terakhir->pesan_error }}
                    </div>
                @endif
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;font-size:13px;color:#344054">
                    <div><span style="color:#667085">Waktu sinkron:</span><br><strong>{{ $terakhir->created_at?->translatedFormat('d M Y, H:i') }}</strong></div>
                    <div><span style="color:#667085">Sumber:</span><br><strong>{{ $terakhir->source }}</strong></div>
                    <div><span style="color:#667085">Jumlah scan:</span><br><strong>{{ $terakhir->jumlah_scan }}</strong></div>
                    <div><span style="color:#667085">Durasi (detik):</span><br><strong>{{ $terakhir->duration_seconds ?? '-' }}</strong></div>
                    <div><span style="color:#667085">IP request:</span><br><strong>{{ $terakhir->ip_address ?? '-' }}</strong></div>
                    <div><span style="color:#667085">Terakhir tarik (cache):</span><br><strong>{{ $terakhirTarik ? \Illuminate\Support\Carbon::parse($terakhirTarik)->translatedFormat('d M Y, H:i') : 'Belum ada' }}</strong></div>
                </div>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header" style="gap:12px;flex-wrap:wrap">
            <h3 style="margin-right:auto">Riwayat Sinkron Mesin</h3>
            <a href="{{ route('admin.presensi.mesin.index') }}" class="btn btn-sm btn-ghost">Buka Halaman Import Presensi Mesin</a>
        </div>
        <div class="card-body tight">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Status</th>
                            <th>Sumber</th>
                            <th style="text-align:center">Scan</th>
                            <th style="text-align:center">Dibuat</th>
                            <th style="text-align:center">Diperbarui</th>
                            <th style="text-align:center">Tetap</th>
                            <th style="text-align:center">Dilindungi</th>
                            <th style="text-align:center">NIK Tidak Dikenal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td style="white-space:nowrap">{{ $log->created_at?->translatedFormat('d M Y, H:i') }}</td>
                                <td>
                                    <span class="badge {{ $log->status === 'berhasil' ? 'badge-success' : 'badge-danger' }}">
                                        {{ $log->status === 'berhasil' ? 'Berhasil' : 'Gagal' }}
                                    </span>
                                </td>
                                <td><small>{{ $log->source }}</small></td>
                                <td style="text-align:center">{{ $log->jumlah_scan }}</td>
                                <td style="text-align:center">{{ $log->dibuat }}</td>
                                <td style="text-align:center">{{ $log->diperbarui }}</td>
                                <td style="text-align:center">{{ $log->tetap }}</td>
                                <td style="text-align:center">{{ $log->dilindungi }}</td>
                                <td style="text-align:center">{{ $log->nik_tidak_dikenal }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" style="text-align:center;padding:24px;color:#667085">Belum ada riwayat sinkron mesin. Gunakan Presensi Bridge di PC sekolah atau Tarik Semua Data (1 Klik).</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
