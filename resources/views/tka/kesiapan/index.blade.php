@extends($peran === 'admin' ? 'layouts.admin-with-tabs' : 'layouts.app')

@section('title', 'Pemantauan Kesiapan TKA')

@section('sidebar')
    @include($peran.'.partials.sidebar')
@endsection

@section('page_title', 'Pemantauan Kesiapan TKA')
@section('page_subtitle', 'Pemantauan berbasis bukti belajar siswa (kelas 4, 5, 6) untuk perencanaan dukungan belajar')

@section('content')
@php
    use App\Support\KesiapanLabel;
    $rute = $peran.'.tka-kesiapan.';
@endphp
@if(session('success'))<div class="note note-ok" style="margin-bottom:16px">{{ session('success') }}</div>@endif

@include('tka.kesiapan._disclaimer')
@include('tka.kesiapan._filter')

<div style="margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn btn-outline btn-sm" href="{{ route($rute.'ekspor', $filter->keQuery()) }}">
        {{ $bolehProfil ? 'Ekspor CSV Kelas' : 'Ekspor CSV Agregat' }}
    </a>
    @if($peran === 'admin')
        <a class="btn btn-outline btn-sm" href="{{ route('admin.tka-kesiapan.pengaturan') }}">Pengaturan &amp; pemetaan</a>
    @endif
</div>

<div class="grid grid-4" style="margin-bottom:20px">
    <div class="stat-card primary"><div class="stat-label">Siswa dipantau</div><div class="stat-value">{{ $data['ringkas']['total'] }}</div></div>
    <div class="stat-card ok"><div class="stat-label">Data memadai</div><div class="stat-value">{{ $data['ringkas']['memadai'] }}</div>
        <div class="stat-change">+ {{ $data['ringkas']['sebagian'] }} sebagian</div></div>
    <div class="stat-card"><div class="stat-label">Data belum cukup</div><div class="stat-value">{{ $data['ringkas']['belum_cukup'] }}</div></div>
    <div class="stat-card"><div class="stat-label">Perlu ditinjau guru</div><div class="stat-value">{{ $data['ringkas']['perlu_ditinjau'] }}</div></div>
</div>

@if(!empty($data['agregat_terlindungi']))
    <div class="note note-warn" style="margin-bottom:20px">
        Rincian distribusi, tren, dan agregat kelas disembunyikan untuk menjaga privasi karena jumlah siswa yang sesuai filter kurang dari {{ $data['min_kelompok'] }} orang.
    </div>
@else
    {{-- Peta kekuatan/kesenjangan per indikator --}}
    <div class="card" style="margin-bottom:20px">
        <div class="card-header"><h3>Peta indikator kelas</h3></div>
        <div class="table-wrapper"><table class="table">
            <thead><tr><th>Indikator &amp; status pemetaan</th><th>Perlu dukungan</th><th>Berkembang</th><th>Cukup mantap</th><th>Data belum cukup dinilai</th><th>Tren ↑ / → / ↓</th></tr></thead>
            <tbody>
            @foreach($data['distribusi'] as $kode => $d)
                <tr>
                    <td>
                        {{ $indikator[$kode]['nama'] }}
                        <div style="font-size:.75rem;color:#64748b">{{ KesiapanLabel::asal($indikator[$kode]['asal']) }}</div>
                    </td>
                    <td>{{ $d['perlu_dukungan'] }}</td><td>{{ $d['berkembang'] }}</td><td>{{ $d['cukup_mantap'] }}</td><td>{{ $d['belum_cukup'] }}</td>
                    <td>{{ $data['tren'][$kode]['meningkat'] }} / {{ $data['tren'][$kode]['stabil'] }} / {{ $data['tren'][$kode]['menurun'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <p style="font-size:.8rem;padding:8px">Kategori hanya dihitung jika bukti memenuhi ambang minimum jumlah &amp; sebaran tanggal; selebihnya dicatat "Data belum cukup dinilai". Bukan pemeringkatan siswa.</p>
    </div>

    {{-- Lintasan kelas 4 → 5 → 6 (hanya bukti dengan jenjang historis tervalidasi) --}}
    <div class="card" style="margin-bottom:20px">
        <div class="card-header"><h3>Lintasan kelas 4 → 5 → 6 (bukti jenjang tervalidasi)</h3></div>
        <div class="table-wrapper"><table class="table">
            <thead><tr><th>Indikator</th>@foreach([4,5,6] as $t)<th>Kelas {{ $t }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach(array_keys($data['distribusi']) as $kode)
                <tr><td>{{ $indikator[$kode]['nama'] }}</td>
                @foreach([4,5,6] as $t)
                    @php($c = $data['peta_tingkat'][$t][$kode])
                    <td>
                        @if(!empty($c['privasi_terlindungi']))
                            <em>Disembunyikan (&lt; {{ $data['min_kelompok'] }} siswa)</em>
                        @elseif($c['skor_rata'] !== null)
                            {{ number_format($c['skor_rata'], 1) }} <span style="font-size:.75rem;color:#64748b">(estimasi internal, {{ $c['jumlah_siswa'] }} siswa)</span>
                        @else
                            — <span style="font-size:.75rem;color:#64748b">({{ $c['jumlah_siswa'] }} siswa)</span>
                        @endif
                    </td>
                @endforeach
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <p style="font-size:.8rem;padding:8px">Hanya memakai bukti yang punya bukti kelas/rombel historis yang valid; sel dengan &lt; {{ $data['min_kelompok'] }} siswa disembunyikan demi privasi. Bukti tanpa jenjang historis tidak dimasukkan ke tabel ini ({{ $data['kelengkapan']['tanpa_jenjang_historis'] ?? 0 }} bukti).</p>
    </div>

    {{-- Agregat per kelas / rombel (tampil utama untuk Kepsek & Admin, dan informatif bagi Guru) --}}
    <div class="card" style="margin-bottom:20px">
        <div class="card-header"><h3>Ringkasan agregat per kelas / rombel</h3></div>
        <div class="table-wrapper"><table class="table">
            <thead><tr><th>Kelas / Rombel</th><th>Jumlah siswa</th><th>Data memadai</th><th>Data belum cukup</th>
                @foreach(array_keys($data['distribusi']) as $kode)<th>{{ $indikator[$kode]['nama'] }} (Dukungan / Berkembang / Mantap)</th>@endforeach
            </tr></thead>
            <tbody>
            @forelse($data['agregat_rombel'] as $r)
                <tr>
                    <td>{{ $r['kelas'] }}</td>
                    <td>{{ $r['jumlah_siswa'] }}</td>
                    @if(!empty($r['privasi_terlindungi']))
                        <td colspan="{{ 2 + count($data['distribusi']) }}"><em>Rincian disembunyikan demi privasi (kelompok &lt; {{ $data['min_kelompok'] }} siswa).</em></td>
                    @else
                        <td>{{ $r['memadai'] }}</td>
                        <td>{{ $r['belum_cukup'] }}</td>
                        @foreach(array_keys($data['distribusi']) as $kode)
                            @php($c = $r['per_indikator'][$kode])
                            <td>{{ $c['perlu_dukungan'] }} / {{ $c['berkembang'] }} / {{ $c['cukup_mantap'] }} <span style="font-size:.75rem;color:#64748b">(belum cukup: {{ $c['belum_cukup'] }})</span></td>
                        @endforeach
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ 4 + count($data['distribusi']) }}">Belum ada kelompok kelas pada filter ini.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
@endif

{{-- Kelengkapan data & catatan deduplikasi --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-header"><h3>Kelengkapan sumber bukti &amp; validitas</h3></div>
    <ul style="padding:8px 24px">
        @forelse($data['kelengkapan']['per_sumber'] as $s => $n)
            <li>{{ config("tka_kesiapan.sumber.$s.label", $s) }}: {{ $n }} bukti sah (skala asal: {{ config("tka_kesiapan.sumber.$s.skala") }})</li>
        @empty
            <li>Belum ada bukti sah pada filter ini.</li>
        @endforelse
        <li>{{ $data['kelengkapan']['siswa_tanpa_bukti'] }} siswa belum memiliki bukti pada filter ini.</li>
        @if(($data['kelengkapan']['tanpa_jenjang_historis'] ?? 0) > 0)
            <li>{{ $data['kelengkapan']['tanpa_jenjang_historis'] }} bukti memiliki status <em>jenjang historis tidak diketahui</em> (tidak dimasukkan ke tren kelas 4–6).</li>
        @endif
        @php($diagTotal = array_sum($data['diagnostik'] ?? []))
        @if($diagTotal > 0)
            <li>Pembersihan otomatis:
                @if(($data['diagnostik']['duplikat_kuis_nilai'] ?? 0) > 0) {{ $data['diagnostik']['duplikat_kuis_nilai'] }} salinan kuis di nilai harian; @endif
                @if(($data['diagnostik']['duplikat_tka_sync'] ?? 0) > 0) {{ $data['diagnostik']['duplikat_tka_sync'] }} salinan sinkronisasi TKA; @endif
                @if(($data['diagnostik']['duplikat_attempt_ulang'] ?? 0) > 0) {{ $data['diagnostik']['duplikat_attempt_ulang'] }} percobaan ujian lama; @endif
                @if(($data['diagnostik']['ujian_belum_final'] ?? 0) > 0) {{ $data['diagnostik']['ujian_belum_final'] }} ujian belum final; @endif
                @if(($data['diagnostik']['ujian_dibatalkan_atau_draft'] ?? 0) > 0) {{ $data['diagnostik']['ujian_dibatalkan_atau_draft'] }} sesi draft/dibatalkan; @endif
                @if(($data['diagnostik']['skala_atau_nilai_kosong'] ?? 0) > 0) {{ $data['diagnostik']['skala_atau_nilai_kosong'] }} nilai kosong/tidak berskala; @endif
                dieksklusi agar tidak mendistorsi analisis.
            </li>
        @endif
    </ul>
</div>

{{-- Daftar siswa hanya tampil untuk guru pengampu ($bolehProfil) --}}
@if($bolehProfil)
<div class="card">
    <div class="card-header"><h3>Daftar siswa kelas Anda</h3></div>
    <div class="table-wrapper"><table class="table">
        <thead><tr><th>Siswa</th><th>Kelas</th><th>Data</th>
            @foreach(array_keys($indikator) as $kode)
                @if($filter->indikator === null || $filter->indikator === $kode)
                    <th>{{ $indikator[$kode]['nama'] }}</th>
                @endif
            @endforeach
            <th>Tinjauan</th></tr></thead>
        <tbody>
        @forelse($data['baris'] as $p)
            @php([$sl, $sc] = KesiapanLabel::statusData($p['status_data']))
            <tr>
                <td><a href="{{ route($rute.'siswa', array_merge(['uuid' => $p['siswa']->uuid], $filter->keQuery())) }}">{{ $p['siswa']->nama_peserta_didik }}</a></td>
                <td>{{ $p['siswa']->kelas }}</td>
                <td><span class="tag {{ $sc }}">{{ $sl }}</span></td>
                @foreach(array_keys($indikator) as $kode)
                    @if($filter->indikator === null || $filter->indikator === $kode)
                        @php([$kl, $kc] = KesiapanLabel::kecenderungan($p['indikator'][$kode]['kecenderungan']))
                        <td>
                            <span class="tag {{ $kc }}">{{ $kl }}</span>
                            @if($p['indikator'][$kode]['keyakinan'])
                                <div style="font-size:.7rem;color:#64748b">{{ KesiapanLabel::keyakinan($p['indikator'][$kode]['keyakinan']) }}</div>
                            @endif
                        </td>
                    @endif
                @endforeach
                <td>{{ $p['perlu_ditinjau'] !== [] ? 'Perlu ditinjau' : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ 4 + ($filter->indikator ? 1 : count($indikator)) }}">Tidak ada siswa pada filter ini.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@else
<div class="note" style="padding:12px">
    Daftar individual siswa tidak ditampilkan pada tampilan agregat {{ $peran === 'kepsek' ? 'Kepala Sekolah' : 'Admin' }}. Gunakan tampilan agregat di atas untuk memantau kebutuhan dukungan lintas rombel.
</div>
@endif
@endsection
