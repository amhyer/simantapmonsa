@extends('layouts.app')
@section('title', 'Monitoring CBT - ' . $session->title)

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Monitoring & Pengawasan Ujian')
@section('page_subtitle', $session->title)

@section('header_actions')
    <div style="display:flex;align-items:center;gap:8px">
        <button type="button" onclick="location.reload()" class="btn btn-primary btn-sm">
            <i class="fas fa-sync-alt"></i> Segarkan Data
        </button>
        <a href="{{ route('cbt.sessions.show', $session) }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Detail Sesi
        </a>
    </div>
@endsection

@section('content')
@include('cbt.feedback')

@php
    $totalPeserta = count($session->participant_ids ?? []);
    $totalMulai = $results->total();
    $totalSoal = count($session->question_snapshot ?? []);
@endphp

{{-- Top Stat Cards --}}
<div class="grid grid-4" style="margin-bottom:20px">
    <div class="stat-card primary">
        <i class="fas fa-users stat-icon"></i>
        <div class="stat-label">Total Peserta Terdaftar</div>
        <div class="stat-value">{{ $totalPeserta }}</div>
        <div class="stat-change">Kelas {{ $session->kelas }}</div>
    </div>

    <div class="stat-card ok">
        <i class="fas fa-user-check stat-icon"></i>
        <div class="stat-label">Siswa Telah Mulai</div>
        <div class="stat-value">{{ $totalMulai }}</div>
        <div class="stat-change up">
            {{ $totalPeserta > 0 ? round(($totalMulai / $totalPeserta) * 100) : 0 }}% kehadiran
        </div>
    </div>

    <div class="stat-card gold">
        <i class="fas fa-hourglass-half stat-icon"></i>
        <div class="stat-label">Sedang Mengerjakan</div>
        <div class="stat-value">
            {{ $results->getCollection()->whereNull('submitted_at')->count() }}
        </div>
        <div class="stat-change">Aktif di sistem</div>
    </div>

    <div class="stat-card bad">
        <i class="fas fa-user-shield stat-icon"></i>
        <div class="stat-label">Deteksi Pindah Tab</div>
        <div class="stat-value">
            {{ $results->getCollection()->sum('violations_count') }}
        </div>
        <div class="stat-change down">Log integritas tercatat</div>
    </div>
</div>

{{-- Monitoring Table Card --}}
<div class="card">
    <div class="card-header">
        <div>
            <h3><i class="fas fa-desktop" style="color:var(--navy);margin-right:8px"></i>Status Pengerjaan Siswa (Waktu Nyata)</h3>
            <div style="font-size:12px;color:var(--muted);margin-top:2px">
                Sesi: <strong>{{ ucfirst($session->status) }}</strong> • Total Butir Soal: <strong>{{ $totalSoal }}</strong>
            </div>
        </div>

        <div style="display:flex;align-items:center;gap:10px">
            <span style="font-size:12px;color:var(--muted)">Auto-refresh rekomendasi: 15 detik</span>
            <button type="button" onclick="location.reload()" class="btn btn-secondary btn-sm">
                <i class="fas fa-redo"></i> Refresh
            </button>
        </div>
    </div>

    <div class="card-body tight">
        @if($results->isEmpty())
            <div class="empty-state">
                <div class="icon">👥</div>
                <h4>Belum Ada Siswa yang Mulai</h4>
                <p>Belum ada siswa yang menekan tombol Mulai Ujian pada sesi ini.</p>
            </div>
        @else
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width:5%">No</th>
                            <th style="width:28%">Nama Siswa</th>
                            <th style="width:20%">Progress Jawaban</th>
                            <th style="width:14%">Batas Waktu</th>
                            <th style="width:13%">Status</th>
                            <th style="width:10%">Nilai</th>
                            <th style="width:10%;text-align:right">Integritas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($results as $index => $res)
                            @php
                                $ansCount = count($res->answers ?? []);
                                $percent = $totalSoal > 0 ? round(($ansCount / $totalSoal) * 100) : 0;
                                $isExpired = $res->deadline_at && now()->gte($res->deadline_at);
                                $isDone = !is_null($res->submitted_at);
                            @endphp
                            <tr>
                                <td>{{ $results->firstItem() + $index }}</td>
                                <td>
                                    <div style="font-weight:700;font-size:13.5px;color:var(--text)">
                                        {{ $res->siswa?->nama_peserta_didik ?? 'Siswa (Dihapus)' }}
                                    </div>
                                    <div style="font-size:11.5px;color:var(--muted);margin-top:1px">
                                        NISN: {{ $res->siswa?->nisn ?? '-' }} • NIS: {{ $res->siswa?->nis ?? '-' }}
                                    </div>
                                </td>
                                <td>
                                    <div style="display:flex;align-items:center;justify-content:space-between;font-size:12px;margin-bottom:4px">
                                        <span style="font-weight:600">{{ $ansCount }}/{{ $totalSoal }} butir</span>
                                        <span style="color:var(--muted)">{{ $percent }}%</span>
                                    </div>
                                    <div class="bar">
                                        <span class="fill {{ $percent === 100 ? 'fill-ok' : '' }}" style="width:{{ $percent }}%"></span>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-family:monospace;font-size:12.5px;font-weight:600;color:{{ $isExpired ? 'var(--bad)' : 'var(--text)' }}">
                                        {{ $res->deadline_at?->format('H:i:s') ?? '—' }}
                                    </span>
                                </td>
                                <td>
                                    @if($isDone)
                                        <span class="tag tag-ok"><i class="fas fa-check-circle"></i> Terkumpul</span>
                                    @elseif($isExpired)
                                        <span class="tag tag-warn"><i class="fas fa-hourglass-end"></i> Menunggu Final</span>
                                    @else
                                        <span class="tag tag-primary"><i class="fas fa-pencil-alt"></i> Mengerjakan</span>
                                    @endif
                                </td>
                                <td>
                                    @if(!is_null($res->score))
                                        <span style="font-size:14px;font-weight:800;color:var(--navy)">
                                            {{ number_format($res->score, 1) }}
                                        </span>
                                    @else
                                        <span style="color:var(--muted)">—</span>
                                    @endif
                                </td>
                                <td style="text-align:right">
                                    @if($res->violations_count > 0)
                                        <button type="button" 
                                                onclick="showViolations('{{ route('cbt.integrity.violations', $res) }}', '{{ addslashes($res->siswa?->nama_peserta_didik ?? 'Siswa') }}')" 
                                                class="tag tag-bad" 
                                                style="cursor:pointer;border:none" 
                                                title="Lihat rincian pelanggaran">
                                            <i class="fas fa-exclamation-triangle"></i> {{ $res->violations_count }} Catatan
                                        </button>
                                    @else
                                        <span class="tag tag-ok" title="Tidak ada deteksi"><i class="fas fa-check"></i> 0</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($results->hasPages())
                <div style="padding:16px 20px;border-top:1px solid var(--line)">
                    {{ $results->links() }}
                </div>
            @endif
        @endif
    </div>
</div>

{{-- Modal Rincian Pelanggaran Integritas CBT --}}
<div id="violationsModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.6);z-index:9999;align-items:center;justify-content:center;padding:16px">
    <div style="background:#fff;border-radius:14px;max-width:600px;width:100%;box-shadow:0 20px 25px -5px rgba(0,0,0,0.2);overflow:hidden;animation:fadeIn 0.15s ease">
        <div style="padding:16px 20px;background:linear-gradient(135deg, var(--navy-dark) 0%, var(--navy) 100%);color:#fff;display:flex;align-items:center;justify-content:space-between">
            <h3 style="margin:0;font-size:15px;font-weight:700;display:flex;align-items:center;gap:8px">
                <i class="fas fa-shield-halved" style="color:#FBBF24"></i> Log Integritas: <span id="violModalStudentName">-</span>
            </h3>
            <button type="button" onclick="closeViolationsModal()" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer">&times;</button>
        </div>
        <div id="violModalBody" style="padding:20px;max-height:60vh;overflow-y:auto;font-size:13px">
            <div style="text-align:center;color:var(--muted);padding:20px">
                <i class="fas fa-spinner fa-spin" style="font-size:24px"></i>
                <div style="margin-top:8px">Memuat data log pelanggaran…</div>
            </div>
        </div>
        <div style="padding:12px 20px;background:#FAFBFD;border-top:1px solid var(--line);text-align:right">
            <button type="button" onclick="closeViolationsModal()" class="btn btn-secondary btn-sm">Tutup</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function showViolations(url, studentName) {
    const modal = document.getElementById('violationsModal');
    const nameEl = document.getElementById('violModalStudentName');
    const bodyEl = document.getElementById('violModalBody');
    if (!modal) return;

    nameEl.textContent = studentName;
    bodyEl.innerHTML = '<div style="text-align:center;color:var(--muted);padding:24px"><i class="fas fa-spinner fa-spin" style="font-size:24px"></i><div style="margin-top:8px">Memuat data log pelanggaran…</div></div>';
    modal.style.display = 'flex';

    fetch(url, {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(json => {
        const items = json.data || [];
        if (items.length === 0) {
            bodyEl.innerHTML = '<div style="text-align:center;color:var(--muted);padding:20px"><i class="fas fa-check-circle" style="color:var(--ok);font-size:28px"></i><p style="margin-top:8px">Tidak ditemukan catatan pelanggaran spesifik.</p></div>';
            return;
        }

        const typeLabels = {
            'tab_hidden': 'Pindah Tab / Browser Diminimalkan',
            'fullscreen_exit': 'Keluar dari Mode Layar Penuh',
            'window_blur': 'Keluar dari Jendela Ujian'
        };

        let html = '<div class="table-wrapper"><table style="font-size:12.5px;width:100%">';
        html += '<thead><tr><th style="width:10%">No</th><th style="width:45%">Jenis Indikasi</th><th style="width:45%">Waktu Kejadian</th></tr></thead><tbody>';

        items.forEach((item, idx) => {
            const label = typeLabels[item.type] || item.type || 'Peringatan Integritas';
            const dt = new Date(item.created_at);
            const formatted = isNaN(dt.getTime()) ? (item.created_at || '-') : dt.toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'medium' });
            html += `<tr><td>${idx + 1}</td><td><span class="tag tag-bad" style="font-size:11px"><i class="fas fa-exclamation-triangle"></i> ${label}</span></td><td>${formatted}</td></tr>`;
        });

        html += '</tbody></table></div>';
        bodyEl.innerHTML = html;
    })
    .catch(err => {
        bodyEl.innerHTML = '<div class="note note-bad"><i class="fas fa-exclamation-circle"></i> Gagal memuat data log. Silakan coba kembali.</div>';
    });
}

function closeViolationsModal() {
    const modal = document.getElementById('violationsModal');
    if (modal) modal.style.display = 'none';
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeViolationsModal();
});
</script>
@endpush
