@extends('layouts.app')

@section('title', 'Dashboard Orang Tua - SIMANTAP')

@section('sidebar')
    @include('ortu.partials.sidebar')
@endsection

@section('page_title', 'Beranda Orang Tua')
@section('page_subtitle', 'Pantau perkembangan anak')

@section('content')
    @if(isset($anakList) && $anakList->count() > 1)
        <div style="margin-bottom:16px">
            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Pilih Anak</label>
            <select onchange="window.location.href='{{ route('ortu.dashboard') }}?siswa_id='+this.value" style="padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px;min-width:180px">
                @foreach($anakList as $a)
                    <option value="{{ $a->id }}" {{ $anak && $a->id == $anak->id ? 'selected' : '' }}>{{ $a->nama_peserta_didik }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if(!$anak)
        <div class="note note-warn">
            <i class="fas fa-exclamation-triangle"></i> Akun Anda belum terhubung dengan data siswa. Hubungi admin.
        </div>
    @else

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
            </div>
        </div>
    </div>

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
    @endif
@endsection
