@extends('layouts.app')

@section('title', 'Kirim Nilai Ke Dapodik - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Kirim Nilai Ke Dapodik')
@section('page_subtitle', 'Kirim data dari SIMANTAP ke server Dapodik pusat')

@section('content')
    @php
        $isConfigured = !empty($config->npsn) && !empty($config->token);
        $modules = [
            ['key' => 'sekolah', 'label' => 'Data Sekolah', 'icon' => 'lucide-building-2', 'desc' => 'Profil sekolah (NPSN, alamat, kepala sekolah)', 'method' => 'GET', 'needs_semester' => false],
            ['key' => 'peserta-didik', 'label' => 'Peserta Didik', 'icon' => 'lucide-graduation-cap', 'desc' => 'Data siswa aktif per semester', 'method' => 'POST', 'needs_semester' => true],
            ['key' => 'gtk', 'label' => 'GTK (Guru/Tendik)', 'icon' => 'lucide-presentation', 'desc' => 'Data guru & tendik terhubung Dapodik', 'method' => 'POST', 'needs_semester' => false],
            ['key' => 'rombel', 'label' => 'Rombongan Belajar', 'icon' => 'lucide-school', 'desc' => 'Data rombel beserta wali kelas', 'method' => 'POST', 'needs_semester' => false],
            ['key' => 'jadwal', 'label' => 'Jadwal Pelajaran', 'icon' => 'lucide-calendar-days', 'desc' => 'Jadwal pelajaran terhubung Dapodik', 'method' => 'POST', 'needs_semester' => false],
            ['key' => 'nilai-rapor', 'label' => 'Nilai Rapor', 'icon' => 'lucide-file-text', 'desc' => 'Nilai rapor siswa per semester', 'method' => 'POST', 'needs_semester' => true],
            ['key' => 'kehadiran', 'label' => 'Kehadiran', 'icon' => 'lucide-calendar-check', 'desc' => 'Rekap kehadiran siswa per semester', 'method' => 'POST', 'needs_semester' => true],
        ];
    @endphp

    @if(!$isConfigured)
        <div class="note note-warn" style="margin-bottom:16px">
            <b>Konfigurasi belum lengkap.</b> Isi NPSN dan token di
            <a href="{{ route('admin.dapodik.index') }}">Web Service Dapodik</a> sebelum melakukan push.
        </div>
    @endif

    <div class="grid grid-4" style="margin-bottom:20px">
        <div class="stat-card {{ $isConfigured ? 'ok' : 'bad' }}">
            <div class="stat-value">{{ $isConfigured ? 'Siap' : 'Belum' }}</div>
            <div class="stat-label">Status Koneksi</div>
        </div>
        <div class="stat-card primary">
            <div class="stat-value">{{ $config->npsn ?? '-' }}</div>
            <div class="stat-label">NPSN</div>
        </div>
        <div class="stat-card gold">
            <div class="stat-value">{{ $counts['peserta_didik'] }}</div>
            <div class="stat-label">Siswa Siap Push</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $config->last_sync_at?->diffForHumans() ?? 'Belum pernah' }}</div>
            <div class="stat-label">Sinkron Terakhir</div>
        </div>
    </div>

    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <h3>Modul Push Data</h3>
            <div style="display:flex;align-items:center;gap:8px">
                <label for="pushSemester" style="font-size:12px;color:var(--muted);font-weight:600">Semester:</label>
                <select id="pushSemester" style="padding:7px 10px;border:1px solid var(--line);border-radius:8px;font-size:13px">
                    @forelse($semesters as $s)
                        <option value="{{ $s->semester_id }}">{{ $s->tahun_ajaran }} {{ $s->nama_semester }}</option>
                    @empty
                        <option value="">Belum ada semester</option>
                    @endforelse
                </select>
            </div>
        </div>
        <div class="card-body">
            <div class="grid grid-2" id="pushModules">
                @foreach($modules as $m)
                    <div class="card" style="background:#FAFBFD">
                        <div class="card-body" style="display:flex;align-items:center;gap:14px">
                            <span class="icon" style="width:42px;height:42px;border-radius:10px;background:#EEF2F9;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0">
                                <x-dynamic-component :component="$m['icon']" class="w-5 h-5" />
                            </span>
                            <div style="flex:1;min-width:0">
                                <b>{{ $m['label'] }}</b>
                                <div style="font-size:12px;color:var(--muted)">{{ $m['desc'] }}</div>
                                <div style="font-size:12px;margin-top:2px">
                                    Siap push: <b id="count-{{ $m['key'] }}">{{ $counts[str_replace('-', '_', $m['key'])] }}</b> data
                                </div>
                                <div id="result-{{ $m['key'] }}" style="font-size:12px;margin-top:4px"></div>
                            </div>
                            <button type="button" class="btn btn-sm"
                                    data-push="{{ $m['key'] }}"
                                    data-method="{{ $m['method'] }}"
                                    data-needs-semester="{{ $m['needs_semester'] ? '1' : '0' }}"
                                    {{ $isConfigured ? '' : 'disabled' }}>
                                Kirim
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Log Sinkronisasi Terbaru</h3>
        </div>
        <div class="card-body tight">
            @if($logs->count())
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr><th>Waktu</th><th>Tipe</th><th style="text-align:right">Berhasil</th><th style="text-align:right">Gagal</th><th style="text-align:right">Oleh</th></tr>
                        </thead>
                        <tbody>
                            @foreach($logs as $log)
                                <tr>
                                    <td>{{ $log->created_at->diffForHumans() }}</td>
                                    <td><span class="tag tag-mut">{{ $log->tipe }}</span></td>
                                    <td style="text-align:right">{{ $log->berhasil }}</td>
                                    <td style="text-align:right">{{ $log->gagal }}</td>
                                    <td style="text-align:right">{{ $log->user?->nama_lengkap ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty">
                    <div class="icon">📋</div>
                    <h4>Belum ada log sinkronisasi</h4>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const token = document.querySelector('meta[name="csrf-token"]').content;
    // Petakan key modul ke nama route push JSON yang sudah ada.
    const routeFor = (key) => ({
        'sekolah': '{{ route('admin.dapodik.push.sekolah') }}',
        'peserta-didik': '{{ route('admin.dapodik.push.peserta-didik') }}',
        'gtk': '{{ route('admin.dapodik.push.gtk') }}',
        'rombel': '{{ route('admin.dapodik.push.rombel') }}',
        'jadwal': '{{ route('admin.dapodik.push.jadwal') }}',
        'nilai-rapor': '{{ route('admin.dapodik.push.nilai-rapor') }}',
        'kehadiran': '{{ route('admin.dapodik.push.kehadiran') }}',
    })[key];

    document.querySelectorAll('[data-push]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const key = btn.dataset.push;
            const result = document.getElementById('result-' + key);
            const body = {};
            if (btn.dataset.needsSemester === '1') {
                const semester = document.getElementById('pushSemester').value;
                if (!semester) {
                    showToast('Pilih semester terlebih dahulu.', 'warning');
                    return;
                }
                body.semester_id = semester;
            }
            if (!confirm('Kirim data ' + key + ' ke Dapodik sekarang?')) return;
            btn.disabled = true;
            const label = btn.textContent;
            btn.textContent = 'Mengirim...';
            result.innerHTML = '';
            try {
                const res = await fetch(routeFor(key), {
                    method: btn.dataset.method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                    },
                    body: btn.dataset.method === 'GET' ? undefined : JSON.stringify(body),
                });
                const json = await res.json();
                if (json.success) {
                    result.innerHTML = '<span class="tag tag-ok">Berhasil: ' + (json.data.berhasil ?? 0) + ' · Gagal: ' + (json.data.gagal ?? 0) + '</span>';
                    showToast(json.message || 'Push berhasil.', 'success');
                } else {
                    result.innerHTML = '<span class="tag tag-bad">' + (json.message || 'Push gagal.') + '</span>';
                    showToast(json.message || 'Push gagal.', 'error');
                }
            } catch (e) {
                result.innerHTML = '<span class="tag tag-bad">Kesalahan jaringan.</span>';
                showToast('Kesalahan jaringan saat push.', 'error');
            }
            btn.disabled = false;
            btn.textContent = label;
        });
    });
})();
</script>
@endpush
