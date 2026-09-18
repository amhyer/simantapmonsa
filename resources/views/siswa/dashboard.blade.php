@extends('layouts.app')

@section('title', 'Dashboard Siswa - SIMANTAP')

@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection

@section('page_title', 'Beranda Siswa')
@section('page_subtitle', optional($siswa)->kelas ?? '-')

@section('content')
    @if(!$siswa)
        <div class="note note-warn">
            <i class="fas fa-exclamation-triangle"></i> Akun Anda belum terhubung dengan data siswa. Hubungi guru.
        </div>
    @else

    <div class="grid grid-4" style="margin-bottom:20px">
        <div class="stat-card primary" style="position:relative;overflow:hidden">
            <div style="position:absolute;top:10px;right:14px;opacity:.1;font-size:48px"><i class="fas fa-user"></i></div>
            <div class="stat-label">Nama</div>
            <div class="stat-value" style="font-size:15px">{{ $siswa->nama_peserta_didik }}</div>
            <div class="stat-change" style="color:var(--navy)">NISN: {{ $siswa->nisn }}</div>
        </div>
        <div class="stat-card gold" style="position:relative;overflow:hidden">
            <div style="position:absolute;top:10px;right:14px;opacity:.1;font-size:48px"><i class="fas fa-chart-line"></i></div>
            <div class="stat-label">Rata-rata Nilai</div>
            <div class="stat-value" style="color:var(--gold)">{{ $rataRata > 0 ? number_format($rataRata, 1) : '-' }}</div>
            <div class="stat-change">{{ $jumlahNilai }} penilaian</div>
        </div>
        <div class="stat-card ok" style="position:relative;overflow:hidden">
            <div style="position:absolute;top:10px;right:14px;opacity:.1;font-size:48px"><i class="fas fa-check-circle"></i></div>
            <div class="stat-label">Kehadiran</div>
            <div class="stat-value" style="color:#0f766e">{{ number_format($kehadiran, 1) }}%</div>
            <div class="stat-change">{{ $totalHadir }} hari hadir</div>
        </div>
        <div class="stat-card" style="position:relative;overflow:hidden;border-left:3px solid var(--navy)">
            <div style="position:absolute;top:10px;right:14px;opacity:.1;font-size:48px"><i class="fas fa-trophy"></i></div>
            <div class="stat-label">Predikat</div>
            <div class="stat-value" style="font-size:28px">
                @php
                    $predikat = $rataRata >= 90 ? 'A' : ($rataRata >= 80 ? 'B' : ($rataRata >= 70 ? 'C' : ($rataRata > 0 ? 'D' : '-')));
                    $predikatColor = ['A' => '#0f766e', 'B' => '#1F3864', 'C' => '#B8860B', 'D' => '#B42318'];
                @endphp
                <span style="color:{{ $predikatColor[$predikat] ?? '#667085' }}">{{ $predikat }}</span>
            </div>
            <div class="stat-change">{{ $rataRata > 0 ? number_format($rataRata, 1) : '-' }} / 100</div>
        </div>
    </div>

    <div class="grid grid-2" style="margin-bottom:20px">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar" style="margin-right:8px;color:var(--navy)"></i>Perkembangan Nilai</h3>
            </div>
            <div class="card-body">
                @if($nilaiPerMapel->count())
                    <div style="overflow-x:auto">
                        <table style="width:100%;border-collapse:collapse;font-size:13px">
                            <thead>
                                <tr style="border-bottom:2px solid #E4E7EC">
                                    <th style="padding:8px;text-align:left;color:#667085">Mata Pelajaran</th>
                                    <th style="padding:8px;text-align:center;color:#667085">Nilai</th>
                                    <th style="padding:8px;text-align:center;color:#667085">Predikat</th>
                                    <th style="padding:8px;text-align:left;color:#667085">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($nilaiPerMapel as $mapel)
                                    @php
                                        $avg = $mapel['rata_rata'];
                                        $p = $avg >= 90 ? 'A' : ($avg >= 80 ? 'B' : ($avg >= 70 ? 'C' : 'D'));
                                        $barColor = $avg >= 80 ? '#0f766e' : ($avg >= 70 ? '#B8860B' : '#B42318');
                                    @endphp
                                    <tr style="border-bottom:1px solid #F2F4F7">
                                        <td style="padding:8px;font-weight:600">{{ $mapel['nama'] }}</td>
                                        <td style="padding:8px;text-align:center">
                                            <div style="background:#E4E7EC;border-radius:4px;height:8px;width:100px;margin:0 auto;position:relative">
                                                <div style="background:{{ $barColor }};border-radius:4px;height:8px;width:{{ min($avg, 100) }}%"></div>
                                            </div>
                                            <span style="font-size:11px;color:#667085">{{ number_format($avg, 1) }}</span>
                                        </td>
                                        <td style="padding:8px;text-align:center"><span class="tag {{ $p === 'A' ? 'tag-ok' : ($p === 'B' ? 'tag-mut' : ($p === 'C' ? 'tag-gold' : 'tag-bad')) }}">{{ $p }}</span></td>
                                        <td style="padding:8px">
                                            @if($avg >= 80)
                                                <span style="color:#0f766e;font-size:12px"><i class="fas fa-check"></i> Baik</span>
                                            @elseif($avg >= 70)
                                                <span style="color:#B8860B;font-size:12px"><i class="fas fa-exclamation"></i> Cukup</span>
                                            @else
                                                <span style="color:#B42318;font-size:12px"><i class="fas fa-times"></i> Perlu Ditingkatkan</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div style="text-align:center;padding:24px;color:#667085">
                        <i class="fas fa-chart-bar" style="font-size:32px;opacity:.3;margin-bottom:8px;display:block"></i>
                        Belum ada data nilai
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-check" style="margin-right:8px;color:#0f766e"></i>Riwayat Kehadiran</h3>
            </div>
            <div class="card-body">
                @if($kehadiranBulanan->count())
                    <div style="overflow-x:auto">
                        <table style="width:100%;border-collapse:collapse;font-size:13px">
                            <thead>
                                <tr style="border-bottom:2px solid #E4E7EC">
                                    <th style="padding:8px;text-align:left;color:#667085">Bulan</th>
                                    <th style="padding:8px;text-align:center;color:#667085">Hadir</th>
                                    <th style="padding:8px;text-align:center;color:#667085">Sakit</th>
                                    <th style="padding:8px;text-align:center;color:#667085">Izin</th>
                                    <th style="padding:8px;text-align:center;color:#667085">Alpa</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($kehadiranBulanan as $bulan)
                                    <tr style="border-bottom:1px solid #F2F4F7">
                                        <td style="padding:8px;font-weight:600">{{ $bulan['nama'] }}</td>
                                        <td style="padding:8px;text-align:center"><span class="tag tag-ok">{{ $bulan['H'] }}</span></td>
                                        <td style="padding:8px;text-align:center"><span class="tag tag-gold">{{ $bulan['S'] }}</span></td>
                                        <td style="padding:8px;text-align:center"><span class="tag tag-mut">{{ $bulan['I'] }}</span></td>
                                        <td style="padding:8px;text-align:center"><span class="tag tag-bad">{{ $bulan['A'] }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div style="text-align:center;padding:24px;color:#667085">
                        <i class="fas fa-calendar" style="font-size:32px;opacity:.3;margin-bottom:8px;display:block"></i>
                        Belum ada data kehadiran
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if($saran)
    <div class="card" style="margin-bottom:20px;border-left:3px solid {{ $saran['warna'] }}">
        <div class="card-header" style="background:{{ $saran['warna'] }}10">
            <h3><i class="fas fa-lightbulb" style="margin-right:8px;color:{{ $saran['warna'] }}"></i>Saran & Rekomendasi</h3>
        </div>
        <div class="card-body">
            <div style="display:flex;gap:16px;flex-wrap:wrap">
                @foreach($saran['items'] as $item)
                    <div style="flex:1;min-width:250px;padding:12px;background:#FAFBFD;border-radius:8px;border-left:3px solid {{ $item['color'] }}">
                        <div style="font-weight:600;font-size:13px;margin-bottom:4px;color:{{ $item['color'] }}">
                            <i class="fas {{ $item['icon'] }}" style="margin-right:6px"></i>{{ $item['title'] }}
                        </div>
                        <div style="font-size:12px;color:#667085">{{ $item['text'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <div class="grid grid-2" style="margin-bottom:20px">
        @if($materiTerbaru->count())
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-book" style="margin-right:8px;color:var(--navy)"></i>Materi Terbaru</h3>
            </div>
            <div class="card-body tight">
                @foreach($materiTerbaru as $m)
                    <div style="padding:10px 14px;border-bottom:1px solid #F2F4F7;display:flex;justify-content:space-between;align-items:center">
                        <div>
                            <div style="font-weight:600;font-size:13px">{{ $m->judul }}</div>
                            <div style="font-size:11px;color:#888">{{ $m->mata_pelajaran }} &middot; {{ \Carbon\Carbon::parse($m->tanggal)->translatedFormat('d M Y') }}</div>
                        </div>
                        <a href="{{ route('siswa.materi.show', $m->id) }}" class="btn btn-ghost btn-sm">Lihat</a>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        @if($kuisAktif->count())
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-clipboard-list" style="margin-right:8px;color:#B8860B"></i>Kuis Aktif</h3>
            </div>
            <div class="card-body tight">
                @foreach($kuisAktif as $k)
                    <div style="padding:10px 14px;border-bottom:1px solid #F2F4F7;display:flex;justify-content:space-between;align-items:center">
                        <div>
                            <div style="font-weight:600;font-size:13px">{{ $k->judul }}</div>
                            <div style="font-size:11px;color:#888">{{ $k->mata_pelajaran }} &middot; {{ $k->jumlah_soal }} soal</div>
                        </div>
                        <a href="{{ route('siswa.kuis.show', $k->id) }}" class="btn btn-sm" style="background:#B8860B;color:#fff">Kerjakan</a>
                    </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-id-card" style="margin-right:8px;color:var(--navy)"></i>Data Diri</h3>
            <a href="{{ route('siswa.profil.show') }}" class="btn btn-sm btn-ghost"><i class="fas fa-eye"></i> Lihat Profil</a>
        </div>
        <div class="card-body">
            <div style="display:flex;gap:24px;flex-wrap:wrap">
                <div style="flex:0 0 120px;text-align:center">
                    @if($siswa->foto)
                        <img src="{{ asset('storage/' . $siswa->foto) }}" alt="Foto" style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:3px solid var(--navy)">
                    @else
                        <div style="width:100px;height:100px;border-radius:50%;background:var(--navy);color:#fff;display:flex;align-items:center;justify-content:center;font-size:32px;margin:0 auto;font-weight:700">
                            {{ substr($siswa->nama_peserta_didik, 0, 1) }}
                        </div>
                    @endif
                    <div style="font-size:11px;color:#888;margin-top:6px">Foto Profil</div>
                </div>
                <div style="flex:1">
                    <div class="grid grid-2" style="gap:12px;font-size:13px">
                        <div>
                            <p><b>Nama Lengkap:</b> {{ $siswa->nama_peserta_didik }}</p>
                            <p><b>NIS:</b> {{ $siswa->nis }}</p>
                            <p><b>NISN:</b> {{ $siswa->nisn }}</p>
                            <p><b>Kelas:</b> {{ $siswa->kelas }}</p>
                            <p><b>Jenis Kelamin:</b> {{ $siswa->jenis_kelamin }}</p>
                        </div>
                        <div>
                            <p><b>Tempat, Tanggal Lahir:</b> {{ $siswa->tempat_lahir }}, {{ $siswa->tanggal_lahir ? \Carbon\Carbon::parse($siswa->tanggal_lahir)->translatedFormat('d M Y') : '-' }}</p>
                            <p><b>Agama:</b> {{ $siswa->agama ?? '-' }}</p>
                            <p><b>Alamat:</b> {{ $siswa->alamat ?? '-' }}</p>
                            <p><b>No. HP:</b> {{ $siswa->telepon ?? '-' }}</p>
                            <p><b>Nama Orang Tua:</b> {{ $siswa->nama_orang_tua ?? '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
@endsection
