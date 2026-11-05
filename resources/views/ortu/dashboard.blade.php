@extends('layouts.app')

@section('title', 'Dashboard Orang Tua - SIMANTAP')

@section('sidebar')
    @include('ortu.partials.sidebar')
@endsection

@section('page_title', 'Beranda Orang Tua')
@section('page_subtitle', 'Pantau perkembangan anak')

@section('content')
    @include('ortu.partials.pilih-anak', ['route' => 'ortu.dashboard', 'terpilih' => $anak, 'anakList' => $anakList])
    @if(!$anak)
        <div class="note note-warn">
            <i class="fas fa-exclamation-triangle"></i> Akun Anda belum terhubung dengan data siswa. Hubungi admin.
        </div>
    @else

    @php
        $kh = $ringkasan['kehadiran_hari_ini'] ?? null;
    @endphp
    @if($kh && $kh->status === 'H')
        <div class="note note-ok" style="margin-bottom:16px;display:flex;align-items:center;gap:12px">
            <i class="fas fa-check-circle" style="font-size:20px"></i>
            <div>
                <strong>Alhamdulillah, ananda telah hadir di sekolah hari ini.</strong>
                <div style="font-size:12px;opacity:0.9">Waktu kehadiran: {{ $kh->waktu_masuk ? \Carbon\Carbon::parse($kh->waktu_masuk)->format('H:i') . ' WITA' : 'Tercatat' }} (Metode: {{ strtoupper($kh->metode ?? 'QR') }})</div>
            </div>
        </div>
    @elseif($kh && in_array($kh->status, ['S', 'I']))
        <div class="note note-warn" style="margin-bottom:16px;display:flex;align-items:center;gap:12px">
            <i class="fas fa-info-circle" style="font-size:20px"></i>
            <div>
                <strong>Status Kehadiran Hari Ini: {{ $kh->status === 'S' ? 'Sakit' : 'Izin' }}</strong>
                @if($kh->keterangan) <div style="font-size:12px">Keterangan: {{ $kh->keterangan }}</div> @endif
            </div>
        </div>
    @elseif($kh && $kh->status === 'A')
        <div class="note note-bad" style="margin-bottom:16px;display:flex;align-items:center;gap:12px">
            <i class="fas fa-exclamation-triangle" style="font-size:20px"></i>
            <div>
                <strong>Perhatian: Ananda tercatat belum hadir (Alpa) pada hari ini.</strong>
                <div style="font-size:12px">Silakan hubungi pihak sekolah atau wali kelas jika ananda berhalangan hadir.</div>
            </div>
        </div>
    @else
        <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:10px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
            <div style="display:flex;align-items:center;gap:10px">
                <i class="fas fa-clock" style="color:var(--muted)"></i>
                <span style="font-size:13px;color:#475569">Presensi hari ini belum tercatat atau jam pelajaran sedang berjalan.</span>
            </div>
            <a href="{{ route('ortu.kehadiran.index') }}" class="btn btn-ghost btn-sm" style="font-size:12px">Lihat Riwayat Presensi</a>
        </div>
    @endif

    <div class="grid grid-4" style="margin-bottom:20px">
        <div class="stat-card primary">
            <i class="fas fa-user stat-icon"></i>
            <div class="stat-label">Nama Anak</div>
            <div class="stat-value" style="font-size:16px">{{ $anak->nama_peserta_didik }}</div>
        </div>
        <div class="stat-card gold">
            <i class="fas fa-id-card stat-icon"></i>
            <div class="stat-label">NIS</div>
            <div class="stat-value" style="font-size:16px">{{ $anak->nis }}</div>
        </div>
        <div class="stat-card ok">
            <i class="fas fa-school stat-icon"></i>
            <div class="stat-label">Kelas</div>
            <div class="stat-value" style="font-size:16px">{{ $anak->kelas }}</div>
        </div>
        <div class="stat-card primary">
            <i class="fas fa-phone stat-icon"></i>
            <div class="stat-label">No. HP Orang Tua</div>
            <div class="stat-value" style="font-size:14px">{{ $anak->telepon ?? '-' }}</div>
        </div>
    </div>

    <div class="grid grid-2">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-graduate" style="margin-right:8px;color:var(--navy)"></i>Data Diri Anak</h3>
            </div>
            <div class="card-body">
                <p style="margin-bottom:8px"><b>Tempat, Tanggal Lahir:</b> {{ $anak->tempat_lahir ?? '-' }}, {{ $anak->tanggal_lahir ? \Carbon\Carbon::parse($anak->tanggal_lahir)->translatedFormat('d M Y') : '-' }}</p>
                <p style="margin-bottom:8px"><b>Jenis Kelamin:</b> {{ $anak->jenis_kelamin }}</p>
                <p style="margin-bottom:8px"><b>Alamat:</b> {{ $anak->alamat ?? '-' }}</p>
                <p style="margin-bottom:8px"><b>No. HP Siswa:</b> {{ $anak->telepon ?? '-' }}</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-line" style="margin-right:8px;color:var(--gold)"></i>Akses Cepat</h3>
            </div>
            <div class="card-body" style="display:grid;gap:8px">
                <a href="{{ route('ortu.kebiasaan.index') }}" class="btn btn-ghost btn-block"><i class="fas fa-clipboard-list"></i> Isi 7 Kebiasaan</a>
                <a href="{{ route('ortu.nilai.index') }}" class="btn btn-ghost btn-block"><i class="fas fa-chart-bar"></i> Lihat Perkembangan Nilai</a>
                <a href="{{ route('tka.parent.index') }}" class="btn btn-ghost btn-block"><i class="fas fa-award"></i> Laporan Karakter & TKA Anak</a>
            </div>
        </div>
    </div>

    @if(isset($ringkasan['tka_rata']) && $ringkasan['tka_rata'] > 0)
        <div class="card" style="margin-top:16px;border-left:4px solid #D97706">
            <div class="card-header">
                <h3><i class="fas fa-award" style="color:#D97706;margin-right:8px"></i>Perkembangan Profil Karakter & TKA Anak</h3>
                <a href="{{ route('tka.parent.index') }}" class="btn btn-ghost btn-sm">Lihat Detail Laporan &rarr;</a>
            </div>
            <div class="card-body">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
                    <div>
                        <div style="font-size:24px;font-weight:800;color:#D97706">{{ number_format($ringkasan['tka_rata'], 2) }} <span style="font-size:13px;color:var(--muted);font-weight:400">/ 4.00</span></div>
                        <div style="font-size:12px;color:var(--muted)">Skor Observasi Guru Kelas</div>
                    </div>
                    <div>
                        @if($ringkasan['tka_rata'] >= 2.5)
                            <span class="tag tag-ok">Kesiapan Baik</span>
                        @else
                            <span class="tag tag-warn">Perlu Pendampingan Belajar</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="card" style="margin-top:20px">
        <div class="card-header">
            <h3><i class="fas fa-chart-line" style="margin-right:8px;color:var(--navy)"></i>Ringkasan Pantauan</h3>
            <a href="{{ route('ortu.nilai.index', $anak ? ['siswa_id' => $anak->id] : []) }}" class="btn btn-ghost btn-sm">Detail</a>
        </div>
        <div class="card-body">
            @if($ringkasan)
                <div class="grid grid-3" style="margin-bottom:12px">
                    <div class="stat">
                        <div class="label">Nilai Akhir</div>
                        <div class="value" style="font-size:22px">{{ $ringkasan['nilai_akhir'] > 0 ? number_format($ringkasan['nilai_akhir'], 1) : '-' }}{{ $ringkasan['predikat'] ? ' (' . $ringkasan['predikat']['huruf'] . ')' : '' }}</div>
                    </div>
                    <div class="stat gold">
                        <div class="label">Kehadiran</div>
                        <div class="value" style="font-size:22px">{{ $ringkasan['hadir_persen'] !== null ? number_format($ringkasan['hadir_persen'], 1) . '%' : '-' }}</div>
                    </div>
                    <div class="stat {{ $ringkasan['kebiasaan_minggu'] > 0 ? 'ok' : '' }}">
                        <div class="label">Kebiasaan pekan ini</div>
                        <div class="value" style="font-size:22px">{{ $ringkasan['kebiasaan_minggu'] > 0 ? 'Sudah isi' : 'Belum isi' }}</div>
                    </div>
                </div>
                @if($ringkasan['catatan']->count())
                    <div style="font-size:12px;font-weight:700;color:var(--muted);margin-bottom:6px">CATATAN TERBARU DARI GURU</div>
                    @foreach($ringkasan['catatan'] as $c)
                        <div style="padding:8px 0;border-top:1px solid #F2F4F7;font-size:13px">
                            <b>{{ $c->nama_guru ?? 'Guru' }}</b>
                            <span style="color:var(--muted)">· {{ $c->tanggal ? \Carbon\Carbon::parse($c->tanggal)->translatedFormat('d M Y') : '-' }}</span>
                            <div style="color:var(--text);margin-top:2px">{{ \Illuminate\Support\Str::limit($c->catatan, 120) }}</div>
                        </div>
                    @endforeach
                @endif
            @else
                <div style="color:var(--muted);font-size:13px">Belum ada data pantauan.</div>
            @endif
        </div>
    </div>
    
    {{-- CBT Results Section --}}
    @if($ringkasan && isset($ringkasan['cbt_results']) && count($ringkasan['cbt_results']) > 0)
    <div class="card" style="margin-top:20px">
        <div class="card-header">
            <h3><i class="fas fa-clipboard-check" style="margin-right:8px;color:var(--navy)"></i>Hasil Ujian CBT Terbaru</h3>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Nama Ujian</th>
                            <th>Mata Pelajaran</th>
                            <th>Tanggal</th>
                            <th>Nilai</th>
                            <th>Predikat</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ringkasan['cbt_results'] as $hasil)
                        <tr>
                            <td>{{ $hasil['judul'] }}</td>
                            <td>{{ $hasil['mapel'] }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($hasil['submitted_at'])->format('d M Y H:i') }}</td>
                            <td><strong>{{ number_format($hasil['score'], 1) }}</strong></td>
                            <td>
                                @php
                                    $skor = $hasil['score'];
                                    $predikat = $skor >= 90 ? 'A' : ($skor >= 80 ? 'B' : ($skor >= 70 ? 'C' : 'D'));
                                    $colors = ['A' => 'green', 'B' => 'blue', 'C' => 'yellow', 'D' => 'red'];
                                    $color = $colors[$predikat] ?? 'gray';
                                @endphp
                                <span class="tag tag-{{ $color }}">{{ $predikat }}</span>
                            </td>
                            <td>
                                @if($hasil['score'] >= 70)
                                    <span class="tag tag-green">LULUS</span>
                                @else
                                    <span class="tag tag-red">BELUM LULUS</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
@endif
@endsection

