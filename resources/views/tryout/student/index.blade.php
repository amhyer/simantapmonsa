@extends('layouts.app')

@section('title', 'Tryout Siswa - SIMANTAP')

@section('page_title', 'Simulasi Ujian & Tryout Mandiri')
@section('page_subtitle', 'Latihan terstruktur multi-mapel untuk mengukur kesiapan asesmen dan ujian sekolah')

@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection

@section('content')
{{-- Available Tryout Sessions --}}
<div class="card" style="margin-bottom:24px">
    <div class="card-header">
        <h3><i class="fas fa-bullseye" style="color:var(--violet);margin-right:8px"></i>Sesi Tryout Tersedia</h3>
        <span class="tag tag-primary">{{ count($availableSessions) }} Paket Tersedia</span>
    </div>

    <div class="card-body">
        @if(count($availableSessions) > 0)
            <div class="grid grid-3">
                @foreach($availableSessions as $session)
                    <div style="background:#FAFBFD;border:1.5px solid var(--line);border-radius:12px;padding:20px;display:flex;flex-direction:column;justify-content:space-between;transition:all .15s ease">
                        <div>
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                                <span class="tag tag-violet" style="font-weight:700">{{ $session['kode_sesi'] }}</span>
                                <span class="tag tag-ok">Aktif</span>
                            </div>

                            <h4 style="font-size:16px;font-weight:800;color:var(--text);margin:0 0 6px;line-height:1.4">
                                {{ $session['nama_sesi'] }}
                            </h4>
                            <div style="font-size:12px;color:var(--muted);margin-bottom:14px">
                                {{ $session['paket_nama'] }}
                            </div>

                            @if($session['deskripsi'])
                                <p style="font-size:12.5px;color:var(--text);line-height:1.5;margin:0 0 16px">
                                    {{ Str::limit($session['deskripsi'], 100) }}
                                </p>
                            @endif

                            {{-- Mini Stats --}}
                            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;background:#fff;padding:10px;border-radius:8px;border:1px solid var(--line);margin-bottom:16px;text-align:center">
                                <div>
                                    <div style="font-size:15px;font-weight:800;color:var(--navy)">{{ $session['jumlah_mapel'] }}</div>
                                    <div style="font-size:10.5px;color:var(--muted)">Mapel</div>
                                </div>
                                <div>
                                    <div style="font-size:15px;font-weight:800;color:var(--gold)">{{ $session['durasi_menit'] }}</div>
                                    <div style="font-size:10.5px;color:var(--muted)">Menit</div>
                                </div>
                                <div>
                                    <div style="font-size:15px;font-weight:800;color:var(--ok)">{{ $session['passing_grade'] }}</div>
                                    <div style="font-size:10.5px;color:var(--muted)">Target</div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div style="font-size:11.5px;color:var(--muted);margin-bottom:10px;display:flex;align-items:center;gap:6px">
                                <i class="far fa-clock"></i> Batas: <strong>{{ $session['deadline'] }}</strong>
                            </div>
                            <a href="{{ route('tryout.student.start', $session['id']) }}" 
                               class="btn btn-primary btn-block" 
                               onclick="return confirm('Mulai tryout ini sekarang? Waktu pengerjaan {{ $session['durasi_menit'] }} menit.')">
                                <i class="fas fa-play"></i> Mulai Tryout
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="empty-state" style="padding:30px 20px">
                <div class="icon">🎯</div>
                <h4>Belum Ada Tryout Tersedia</h4>
                <p>Saat ini belum ada paket tryout aktif yang dibuka untuk kelas Anda.</p>
            </div>
        @endif
    </div>
</div>

{{-- My Tryout History --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-chart-line" style="color:var(--navy);margin-right:8px"></i>Riwayat & Hasil Tryout Anda</h3>
        <span class="tag tag-secondary">{{ count($myResults) }} Selesai</span>
    </div>

    <div class="card-body tight">
        @if(count($myResults) > 0)
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width:5%">No</th>
                            <th style="width:35%">Paket & Sesi Tryout</th>
                            <th style="width:18%">Tanggal Pengerjaan</th>
                            <th style="width:12%">Nilai Akhir</th>
                            <th style="width:15%">Status Kelulusan</th>
                            <th style="width:15%;text-align:right">Rincian</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($myResults as $index => $result)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    <div style="font-weight:700;font-size:13.5px;color:var(--text)">
                                        {{ $result['paket_nama'] }}
                                    </div>
                                    <div style="font-size:11.5px;color:var(--muted);margin-top:2px">
                                        {{ $result['sesi_nama'] }}
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size:12.5px;color:var(--muted)">{{ $result['tanggal'] }}</span>
                                </td>
                                <td>
                                    <span style="font-size:16px;font-weight:800;color:{{ $result['lulus'] ? 'var(--ok)' : 'var(--bad)' }}">
                                        {{ number_format($result['persentase'], 1) }}
                                    </span>
                                </td>
                                <td>
                                    @if($result['lulus'])
                                        <span class="tag tag-ok"><i class="fas fa-check"></i> Memenuhi Target</span>
                                    @else
                                        <span class="tag tag-bad"><i class="fas fa-times"></i> Belum Memenuhi</span>
                                    @endif
                                </td>
                                <td style="text-align:right">
                                    @if(!empty($result['id']))
                                        <a href="{{ route('tryout.student.result', $result['id']) }}" class="btn btn-secondary btn-sm">
                                            <i class="fas fa-eye"></i> Rincian Skor
                                        </a>
                                    @else
                                        <span style="color:var(--muted)">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state" style="padding:30px 20px">
                <div class="icon">📜</div>
                <h4>Belum Ada Riwayat Tryout</h4>
                <p>Hasil nilai dan peringkat akan tampil di sini setelah Anda menyelesaikan tryout pertama.</p>
            </div>
        @endif
    </div>
</div>
@endsection
