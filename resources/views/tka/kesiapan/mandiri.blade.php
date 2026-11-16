@extends('layouts.app')

@section('title', 'Ringkasan Kesiapan Belajar TKA')

@section('sidebar')
    @include($peran.'.partials.sidebar')
@endsection

@section('page_title', 'Pemantauan Kesiapan Belajar Menghadapi TKA')
@section('page_subtitle', 'Ringkasan perkembangan belajar pribadi dan langkah latihan pendampingan')

@section('content')
@php use App\Support\KesiapanLabel; @endphp
@include('tka.kesiapan._disclaimer')

@if($peran === 'ortu' && $anakList->count() > 1)
    <form method="GET" class="card" style="padding:12px;margin-bottom:16px">
        <label>Pilih anak:
            <select name="siswa_uuid" class="form-control" onchange="this.form.submit()">
                @foreach($anakList as $a)
                    <option value="{{ $a->uuid }}" @selected($siswa && $siswa->id === $a->id)>{{ $a->nama_peserta_didik }} (Kelas {{ $a->kelas }})</option>
                @endforeach
            </select>
        </label>
    </form>
@endif

@if(!$siswa || !$profil)
    <div class="note note-warn">Akun belum terhubung dengan data peserta didik aktif. Silakan hubungi sekolah.</div>
@else
    @php([$sl, $sc] = KesiapanLabel::statusData($profil['status_data']))
    <div class="card" style="padding:14px;margin-bottom:16px">
        <h3>{{ $siswa->nama_peserta_didik }} — Kelas {{ $siswa->kelas }}</h3>
        <p>Kelengkapan bukti belajar: <span class="tag {{ $sc }}">{{ $sl }}</span></p>
        <p style="font-size:.85rem;color:#64748b">Ringkasan ini membantu melihat perkembangan belajar pribadi dari waktu ke waktu untuk merencanakan latihan di sekolah maupun di rumah, tanpa membandingkan dengan siswa lain.</p>
    </div>

    <div class="grid grid-2" style="margin-bottom:20px">
    @foreach($profil['indikator'] as $kode => $i)
        @php([$kl, $kc] = KesiapanLabel::kecenderunganMandiri($i['kecenderungan']))
        <div class="card" style="padding:14px">
            <h4>{{ $i['nama'] }}</h4>
            <div style="font-size:.75rem;color:#64748b">{{ KesiapanLabel::asal($i['asal']) }}</div>
            <p><span class="tag {{ $kc }}">{{ $kl }}</span> · {{ KesiapanLabel::tren($i['tren']['arah']) }}</p>
            <p style="font-size:.85rem">Berdasarkan {{ $i['jumlah_bukti'] }} bukti belajar pada {{ $i['jumlah_tanggal'] }} tanggal.
                @if($i['bukti_status'] === 'belum_cukup')
                    <em>(Data belum cukup untuk disimpulkan.)</em>
                @endif
            </p>
            @if($i['lintasan'])
                <div style="font-size:.8rem;margin-top:6px">
                    <strong>Perkembangan tervalidasi per periode:</strong>
                    <ul>
                        @foreach($i['lintasan'] as $l)
                            <li>Kelas {{ $l['tingkat'] }} ({{ $l['tahun_ajaran'] }} {{ $l['semester'] }}): {{ $l['jumlah_bukti'] }} bukti</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @php($tl = $tindakLanjut->get($kode))
            <div style="margin-top:8px;padding:8px;background:#f8fafc;border-radius:6px;font-size:.85rem">
                <strong>Saran latihan &amp; pendampingan:</strong><br>
                @if($tl)
                    {{ $tl->rekomendasi }}
                    <div style="font-size:.75rem;color:#64748b;margin-top:4px">Status tindak lanjut: {{ KesiapanLabel::statusTindakLanjut($tl->status) }}</div>
                @else
                    {{ config("tka_kesiapan.rekomendasi.$kode.0") }}
                @endif
            </div>
        </div>
    @endforeach
    </div>

    <div class="card">
        <div class="card-header"><h3>Bukti belajar yang tercatat</h3></div>
        <div class="table-wrapper"><table class="table">
            <thead><tr><th>Tanggal</th><th>Sumber</th><th>Kegiatan</th><th>Mata pelajaran / ranah</th><th>Periode &amp; jenjang</th></tr></thead>
            <tbody>
            @forelse($profil['bukti'] as $b)
                <tr>
                    <td>{{ $b['tanggal'] }}</td>
                    <td>{{ $b['sumber_label'] }}</td>
                    <td>{{ $b['judul'] }}</td>
                    <td>{{ $b['indikator'] ? $indikator[$b['indikator']]['nama'] : ($b['mapel'] ?? '—') }}</td>
                    <td>{{ $b['tahun_ajaran'] }} {{ $b['semester'] }} ({{ KesiapanLabel::tingkatBukti($b['tingkat'], $b['tingkat_sumber'] ?? null) }})</td>
                </tr>
            @empty
                <tr><td colspan="5">Belum ada bukti belajar tercatat.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
@endif
@endsection
