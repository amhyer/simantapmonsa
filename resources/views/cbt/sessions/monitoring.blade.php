@extends('layouts.app')

@section('title', 'Pantau Ujian - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Pantau Ujian: ' . $session->nama_sesi)
@section('page_subtitle', 'Live Update — Aktif: ' . $activeStudents->count() . ' siswa | Selesai: ' . $completedStudents->count() . ' siswa')

@section('content')
<div x-data="monitoringState()" style="display:flex;flex-direction:column;gap:16px">
    {{-- Control Bar --}}
    <div class="card" style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px">
        <div>
            <label style="font-size:13px;color:#667085;margin-right:8px">
                <i class="fas fa-sync" style="margin-right:4px"></i>Perbarui setiap:
            </label>
            <select @change="setRefreshInterval()" x-model="refreshInterval" style="padding:6px 10px;border:1px solid #E4E7EC;border-radius:6px;font-size:13px">
                <option value="5000">5 detik</option>
                <option value="10000">10 detik</option>
                <option value="30000">30 detik</option>
                <option value="0">Manual saja</option>
            </select>
        </div>
        
        <div style="display:flex;gap:8px">
            <button @click="refreshNow()" class="btn btn-sm" style="background:#7C3AED;color:#fff">
                <i class="fas fa-sync-alt" style="margin-right:4px"></i>Perbarui Sekarang
            </button>
        </div>
    </div>

    {{-- Session Info --}}
    <div class="card">
        <div class="card-body" style="display:grid;grid-template-columns:repeat(4, 1fr);gap:16px">
            <div style="text-align:center;padding:12px;background:#F9FAFB;border-radius:8px">
                <div style="font-size:24px;font-weight:700;color:var(--violet)">{{ $activeStudents->count() }}</div>
                <div style="font-size:12px;color:#667085">Sedang Mengerjakan</div>
            </div>
            <div style="text-align:center;padding:12px;background:#F9FAFB;border-radius:8px">
                <div style="font-size:24px;font-weight:700;color:var(--ok)">{{ $completedStudents->count() }}</div>
                <div style="font-size:12px;color:#667085">Selesai</div>
            </div>
            <div style="text-align:center;padding:12px;background:#F9FAFB;border-radius:8px">
                <div style="font-size:24px;font-weight:700;color:var(--muted)">{{ $activeStudents->count() + $completedStudents->count() }}</div>
                <div style="font-size:12px;color:#667085">Total Peserta</div>
            </div>
            <div style="text-align:center;padding:12px;background:#F9FAFB;border-radius:8px">
                <div style="font-size:24px;font-weight:700;color:var(--violet)">
                    @php
                        $avgScore = $completedStudents->avg('nilai_akhir') ?? 0;
                    @endphp
                    {{ number_format($avgScore, 1) }}
                </div>
                <div style="font-size:12px;color:#667085">Rata-rata Nilai</div>
            </div>
        </div>
    </div>

    {{-- Active Students Table --}}
    <div class="card">
        <div class="card-header">
            <h3>📱 Siswa Sedang Mengerjakan</h3>
        </div>
        <div class="card-body tight">
            @if($activeStudents->isEmpty())
                <div class="empty-state">
                    <div class="icon">👥</div>
                    <h4>Belum ada siswa yang mulai</h4>
                    <p>Siswa yang membuka ujian akan muncul di sini.</p>
                </div>
            @else
                <div class="table-wrapper">
                    <table id="active-students-table">
                        <thead>
                            <tr>
                                <th>Nama Siswa</th>
                                <th>Waktu Mulai</th>
                                <th>Durasi</th>
                                <th>Soal</th>
                                <th>Integritas</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($activeStudents as $student)
                                <tr data-result-id="{{ $student->id }}">
                                    <td>{{ $student->siswa->nama_peserta_didik ?? 'Unknown' }}</td>
                                    <td style="font-size:12px">{{ $student->waktu_mulai->format('H:i:s') }}</td>
                                    <td style="font-size:12px">
                                        <span x-data="durationData({{ $student->waktu_mulai->timestamp }})" x-text="duration"></span>
                                    </td>
                                    <td style="text-align:center">-</td>
                                    <td style="text-align:center">
                                        <span 
                                            class="tag"
                                            :class="{
                                                'tag-ok': {{ $student->integrity_score ?? 100 }} >= 80,
                                                'tag-warn': {{ $student->integrity_score ?? 100 }} >= 50 && {{ $student->integrity_score ?? 100 }} < 80,
                                                'tag-bad': {{ $student->integrity_score ?? 100 }} < 50
                                            }"
                                        >
                                            {{ $student->integrity_score ?? 100 }}%
                                        </span>
                                    </td>
                                    <td style="text-align:right">
                                        <button 
                                            @click="forceComplete({{ $student->id }})"
                                            class="btn btn-sm"
                                            style="background:#B42318;color:#fff;font-size:11px"
                                        >
                                            Paksa Selesai
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Completed Students Table --}}
    <div class="card">
        <div class="card-header">
            <h3>✅ Siswa Selesai</h3>
        </div>
        <div class="card-body tight">
            @if($completedStudents->isEmpty())
                <div class="empty-state">
                    <div class="icon">🏁</div>
                    <h4>Belum ada siswa yang selesai</h4>
                    <p>Siswa yang telah mengumpulkan ujian akan muncul di sini.</p>
                </div>
            @else
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Nama Siswa</th>
                                <th>Nilai</th>
                                <th>%</th>
                                <th>Status</th>
                                <th>Waktu Selesai</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($completedStudents as $student)
                                <tr>
                                    <td>{{ $student->siswa->nama_peserta_didik ?? 'Unknown' }}</td>
                                    <td style="font-weight:600">{{ $student->nilai_akhir ?? 0 }}</td>
                                    <td style="font-size:12px">{{ number_format(($student->nilai_akhir ?? 0) / ($student->nilai_maksimal ?? 100) * 100, 1) }}%</td>
                                    <td>
                                        <span class="tag {{ $student->lulus ? 'tag-ok' : 'tag-bad' }}">
                                            {{ $student->lulus ? 'Lulus' : 'Belum Lulus' }}
                                        </span>
                                    </td>
                                    <td style="font-size:12px">{{ $student->waktu_selesai?->format('d/m H:i:s') ?? '-' }}</td>
                                    <td style="text-align:right">
                                        <a href="{{ route('cbt.student.result', $student) }}" class="btn btn-sm" style="background:#7C3AED;color:#fff;font-size:11px">
                                            Lihat Hasil
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Session Actions --}}
    <div style="display:flex;justify-content:center;gap:12px;flex-wrap:wrap">
        <a href="{{ route('cbt.sessions.show', $session) }}" class="btn" style="background:#E4E7EC;color:#101828">
            <i class="fas fa-arrow-left" style="margin-right:6px"></i>Kembali
        </a>
        <form method="POST" action="{{ route('cbt.sessions.complete', $session) }}" style="display:inline">
            @csrf
            <button type="submit" class="btn" style="background:#B42318;color:#fff" onclick="return confirm('Tutup ujian untuk semua siswa? Ujian tidak akan bisa dilanjutkan.')">
                <i class="fas fa-times-circle" style="margin-right:6px"></i>Tutup Ujian
            </button>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
    function monitoringState() {
        return {
            refreshInterval: 5000,
            autoRefreshTimer: null,
            
            setRefreshInterval() {
                clearInterval(this.autoRefreshTimer);
                if (parseInt(this.refreshInterval) > 0) {
                    this.autoRefreshTimer = setInterval(() => this.refreshNow(), parseInt(this.refreshInterval));
                }
            },
            
            refreshNow() {
                const sessionId = {{ $session->id }};
                const url = '{{ route("cbt.sessions.api.monitoring", ["session" => ":session"]) }}'.replace(':session', sessionId);
                
                fetch(url)
                    .then(r => r.json())
                    .then(data => {
                        // Update active students table
                        const tbody = document.querySelector('#active-students-table tbody');
                        if (data.active_students) {
                            // Simple update: reload table
                            // In production, use a more sophisticated AJAX update
                        }
                    })
                    .catch(err => console.error('Failed to refresh:', err));
            },
            
            forceComplete(resultId) {
                if (confirm('Paksa mengumpulkan ujian siswa ini?')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '{{ route("cbt.student.submit", ":result") }}'.replace(':result', resultId);
                    
                    const token = document.querySelector('meta[name="csrf-token"]').content;
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = '_token';
                    input.value = token;
                    form.appendChild(input);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            },
            
            init() {
                this.setRefreshInterval();
            }
        }
    }
    
    function durationData(startTimestamp) {
        return {
            duration: '0m',
            init() {
                this.updateDuration();
                setInterval(() => this.updateDuration(), 1000);
            },
            updateDuration() {
                const now = Math.floor(Date.now() / 1000);
                const elapsed = now - startTimestamp;
                const minutes = Math.floor(elapsed / 60);
                const seconds = elapsed % 60;
                this.duration = `${minutes}m ${seconds}s`;
            }
        }
    }
    
    document.addEventListener('alpine:init', () => {
        document.querySelectorAll('[x-data*="durationData"]').forEach(el => {
            el._x_dataStack[0].init?.();
        });
    });
</script>
@endpush
