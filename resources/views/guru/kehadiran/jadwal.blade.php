@extends('layouts.app')

@section('title', 'Absensi Kelas - SIMANTAP')
@section('sidebar')
    @include('guru.partials.sidebar')
@endsection
@section('page_title', 'Absensi Kelas')
@section('page_subtitle', $jadwal->mata_pelajaran . ' · ' . ($jadwal->rombel?->nama_rombel ?? $jadwal->kelas))

@section('header_actions')
    <a href="{{ route('guru.kehadiran.index') }}" class="btn btn-ghost btn-sm">← Kembali ke Kehadiran</a>
@endsection

@section('content')
    <div class="grid grid-3" style="margin-bottom:16px">
        <div class="stat-card primary"><div class="stat-label">Kelas</div><div class="stat-value">{{ $jadwal->rombel?->nama_rombel ?? $jadwal->kelas }}</div></div>
        <div class="stat-card gold"><div class="stat-label">Mata Pelajaran</div><div class="stat-value" style="font-size:18px">{{ $jadwal->mata_pelajaran }}</div></div>
        <div class="stat-card ok"><div class="stat-label">Jadwal / Siswa</div><div class="stat-value" style="font-size:18px">{{ $jadwal->jam_mulai }}–{{ $jadwal->jam_selesai }} · {{ count($students) }}</div></div>
    </div>

    @if(!$canRecordAttendance)
        <div class="note note-warn" style="margin-bottom:16px">Jadwal dapat dilihat, tetapi scan dan perubahan status hanya dibuka dari {{ $jadwal->jam_mulai }} sampai {{ $jadwal->jam_selesai }} (dengan tenggang yang diatur sekolah).</div>
    @endif

    <div class="note note-warn" style="margin-bottom:16px">
        QR yang dipindai mencatat jam masuk harian siswa. @if($presensiMapelTerpisah)
            Untuk mapel ini, scan yang sama juga mencatat kehadiran khusus pada pertemuan {{ $jadwal->mata_pelajaran }}.
        @else
            Status mapel tidak disimpan terpisah; absensi mapel terpisah hanya untuk Bahasa Inggris, Agama Islam, Agama Kristen, dan PJOK.
        @endif
        Siswa yang tidak dipindai tetap perlu ditinjau guru dan ditandai hadir/sakit/izin/alpa.
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="card-header"><h3>📷 Scan Kartu Siswa</h3></div>
        <div class="card-body">
            <div data-presensi-qr data-mode="attendance" data-endpoint="{{ route('guru.kehadiran.scan', $jadwal) }}" data-can-record="{{ $canRecordAttendance ? 'true' : 'false' }}">
                <div data-qr-reader style="width:100%;max-width:520px;margin:4px auto 12px"></div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                    <button type="button" class="btn btn-sm" data-qr-start @disabled(!$canRecordAttendance)>Aktifkan kamera</button>
                    <button type="button" class="btn btn-ghost btn-sm" data-qr-stop disabled>Hentikan kamera</button>
                    <input type="text" data-qr-payload autocomplete="off" @disabled(!$canRecordAttendance) placeholder="Atau scan/tempel QR dengan scanner USB" style="flex:1;min-width:220px">
                    <button type="button" class="btn btn-ghost btn-sm" data-qr-submit-manual @disabled(!$canRecordAttendance)>Catat QR</button>
                </div>
                <div data-qr-feedback role="status" aria-live="polite" style="margin-top:10px;padding:10px;border-radius:8px;background:#F2F4F7" data-kind="info">Pilih kamera atau gunakan pemindai QR yang terhubung ke perangkat.</div>
                <p style="margin:8px 0 0;color:#667085;font-size:12px">Scan terakhir: <strong data-latest-scan>Belum ada</strong></p>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Daftar Siswa dan Status</h3><span class="tag tag-primary">{{ \Carbon\Carbon::parse($tanggal)->translatedFormat('d F Y') }}</span></div>
        <div class="card-body tight">
            <div class="table-wrapper"><table>
                <thead>
                    <tr>
                        <th>Siswa</th>
                        <th>Absensi Harian</th>
                        @if($presensiMapelTerpisah)<th>Absensi {{ $jadwal->mata_pelajaran }}</th>@endif
                        <th>Tindakan Guru</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($students as $student)
                    @php
                        $daily = $kehadiranHarian->get($student->id);
                        $subjectRecord = $presensiMapel->get($student->id);
                        $statusLabel = ['H' => 'Hadir', 'S' => 'Sakit', 'I' => 'Izin', 'A' => 'Alpa'];
                    @endphp
                    <tr data-attendance-row="{{ $student->id }}">
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                @if($student->foto)
                                    <img src="{{ asset('storage/' . ltrim($student->foto, '/')) }}" alt="" style="width:36px;height:36px;border-radius:50%;object-fit:cover">
                                @else
                                    <span class="avatar">{{ mb_substr($student->nama_peserta_didik, 0, 2) }}</span>
                                @endif
                                <span><strong>{{ $student->nama_peserta_didik }}</strong><br><small>NIS: {{ $student->nis }}</small></span>
                            </div>
                        </td>
                        <td><span data-status-daily data-status="{{ $daily?->status }}">{{ $daily ? ($statusLabel[$daily->status] ?? $daily->status) . ($daily->waktu_masuk ? ' · ' . $daily->waktu_masuk->format('H:i:s') : '') : 'Belum dicatat' }}</span></td>
                        @if($presensiMapelTerpisah)
                            <td><span data-status-mapel data-status="{{ $subjectRecord?->status }}">{{ $subjectRecord ? ($statusLabel[$subjectRecord->status] ?? $subjectRecord->status) . ($subjectRecord->waktu_hadir ? ' · ' . $subjectRecord->waktu_hadir->format('H:i:s') : '') : 'Belum dicatat' }}</span></td>
                        @endif
                        <td style="min-width:250px">
                            <form method="POST" action="{{ route('guru.kehadiran.status', [$jadwal, $student]) }}" data-presensi-status-form style="display:flex;gap:5px;align-items:center;flex-wrap:wrap">
                                @csrf
                                <input type="hidden" name="cakupan" value="harian">
                                <select name="status" aria-label="Status harian {{ $student->nama_peserta_didik }}" style="max-width:110px" @disabled(!$canRecordAttendance)>
                                    @foreach($statusLabel as $code => $label)<option value="{{ $code }}" @selected($daily?->status === $code)>{{ $label }}</option>@endforeach
                                </select>
                                <input type="text" name="keterangan" maxlength="255" @disabled(!$canRecordAttendance) placeholder="Catatan opsional" style="max-width:140px">
                                <button type="submit" class="btn btn-ghost btn-sm" @disabled(!$canRecordAttendance)>Simpan harian</button>
                                <span data-status-feedback role="status" style="font-size:11px"></span>
                            </form>
                            @if($presensiMapelTerpisah)
                                <form method="POST" action="{{ route('guru.kehadiran.status', [$jadwal, $student]) }}" data-presensi-status-form style="display:flex;gap:5px;align-items:center;flex-wrap:wrap;margin-top:6px">
                                    @csrf
                                    <input type="hidden" name="cakupan" value="mapel">
                                    <select name="status" aria-label="Status mapel {{ $student->nama_peserta_didik }}" style="max-width:110px" @disabled(!$canRecordAttendance)>
                                        @foreach($statusLabel as $code => $label)<option value="{{ $code }}" @selected($subjectRecord?->status === $code)>{{ $label }}</option>@endforeach
                                    </select>
                                    <input type="text" name="keterangan" maxlength="255" @disabled(!$canRecordAttendance) placeholder="Catatan mapel opsional" style="max-width:140px">
                                    <button type="submit" class="btn btn-ghost btn-sm" @disabled(!$canRecordAttendance)>Simpan mapel</button>
                                    <span data-status-feedback role="status" style="font-size:11px"></span>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $presensiMapelTerpisah ? 4 : 3 }}" style="text-align:center;padding:24px">Belum ada siswa aktif pada kelas ini. Periksa hubungan jadwal dengan rombel/kelas.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
@endsection
