@extends('layouts.app')

@section('title', 'Profil Kesiapan TKA — '.$siswa->nama_peserta_didik)

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Profil Kesiapan TKA: '.$siswa->nama_peserta_didik)
@section('page_subtitle', 'Kelas '.$siswa->kelas.' • bukti belajar, tren tervalidasi, dan rencana dukungan')

@section('content')
@php use App\Support\KesiapanLabel; @endphp
@if(session('success'))<div class="note note-ok" style="margin-bottom:16px">{{ session('success') }}</div>@endif
@if($errors->any())<div class="note note-warn">{{ $errors->first() }}</div>@endif

@include('tka.kesiapan._disclaimer')
@include('tka.kesiapan._filter')

@php([$sl, $sc] = KesiapanLabel::statusData($profil['status_data']))
<p style="margin-bottom:14px">
    Status kelengkapan bukti: <span class="tag {{ $sc }}">{{ $sl }}</span>
    <a class="btn btn-ghost btn-sm" href="{{ route('guru.tka-kesiapan.index', $filter->keQuery()) }}">← Kembali ke kelas</a>
</p>

{{-- Kondisi histori akademik khusus (tinggal kelas / pindah rombel / akselerasi / kalender) --}}
@php($hk = $profil['histori_kondisi'] ?? [])
@if(!empty($hk['tinggal_kelas']) || !empty($hk['akselerasi']) || !empty($hk['pindah_rombel']) || !empty($hk['kalender_tidak_konsisten']) || !empty($hk['catatan_mutasi']))
    <div class="note note-warn" style="margin-bottom:16px">
        <strong>Catatan riwayat akademik siswa:</strong>
        <ul style="margin:6px 0 0 18px">
            @foreach(($hk['tinggal_kelas'] ?? []) as $msg)<li>{{ $msg }}</li>@endforeach
            @foreach(($hk['akselerasi'] ?? []) as $msg)<li>{{ $msg }}</li>@endforeach
            @foreach(($hk['pindah_rombel'] ?? []) as $msg)<li>Perpindahan kelas/rombel: {{ $msg }}</li>@endforeach
            @foreach(($hk['kalender_tidak_konsisten'] ?? []) as $msg)<li>Kalender akademik: {{ $msg }}</li>@endforeach
            @if(!empty($hk['catatan_mutasi']))<li>{{ $hk['catatan_mutasi'] }}</li>@endif
        </ul>
    </div>
@endif

{{-- Profil per indikator: prioritaskan kecenderungan, ringkasan per jenis sumber, keyakinan, & tren --}}
<div class="grid grid-2" style="margin-bottom:20px">
@foreach($profil['indikator'] as $kode => $i)
    @php([$kl, $kc] = KesiapanLabel::kecenderungan($i['kecenderungan']))
    <div class="card" style="padding:14px">
        <h4>{{ $i['nama'] }}</h4>
        <div style="font-size:.75rem;color:#64748b;margin-bottom:6px">{{ KesiapanLabel::asal($i['asal']) }}</div>

        <p><span class="tag {{ $kc }}">{{ $kl }}</span> · <strong>{{ KesiapanLabel::keyakinan($i['keyakinan']) }}</strong></p>
        <p style="font-size:.82rem;color:#475569">{{ $i['keyakinan_alasan'] }}</p>

        <p style="font-size:.85rem">
            Cakupan bukti: <strong>{{ $i['jumlah_bukti'] }}</strong> bukti sah pada <strong>{{ $i['jumlah_tanggal'] }}</strong> tanggal berbeda
            @if($i['bukti_status'] === 'belum_cukup')
                — <em>Data belum cukup dinilai (butuh minimal bukti &amp; sebaran tanggal sesuai pengaturan sekolah).</em>
            @endif
        </p>

        {{-- Ringkasan per jenis sumber (skala asli tetap ditampilkan; instrumen berbeda tidak langsung setara) --}}
        @if(!empty($i['ringkasan_sumber']))
            <div style="margin:8px 0;padding:8px;background:#f8fafc;border-radius:6px;font-size:.8rem">
                <strong>Ringkasan per jenis sumber</strong> (skala berbeda tidak disetarakan otomatis):
                <ul style="margin:4px 0 0 18px">
                    @foreach($i['ringkasan_sumber'] as $rs)
                        <li>{{ $rs['label'] }}: {{ $rs['jumlah'] }} bukti · rata-rata {{ number_format($rs['skor_rata'], 1) }} (skala asal: {{ $rs['skala'] }}, terbaru {{ $rs['tanggal_terakhir'] }})</li>
                    @endforeach
                </ul>
                @if($i['estimasi_indeks_internal'] !== null)
                    <div style="margin-top:6px;color:#334155">
                        <strong>Estimasi indeks internal SIMANTAP: {{ number_format($i['estimasi_indeks_internal'], 1) }} / 100</strong>
                        <span style="color:#64748b">(rata-rata antar-jenis sumber sesuai bobot aturan sekolah; bukan skor setara TKA).</span>
                    </div>
                @endif
            </div>
        @endif

        <p style="font-size:.85rem">{{ KesiapanLabel::tren($i['tren']['arah']) }}
            @if($i['tren']['selisih'] !== null)
                ({{ $i['tren']['selisih'] > 0 ? '+' : '' }}{{ $i['tren']['selisih'] }} poin estimasi, {{ $i['tren']['dari'] }} → {{ $i['tren']['ke'] }})
            @endif
        </p>

        @if($i['lintasan'])
            <div class="table-wrapper"><table class="table" style="font-size:.8rem">
                <thead><tr><th>Kelas (tervalidasi)</th><th>Periode</th><th>Bukti</th><th>Estimasi periode</th></tr></thead>
                <tbody>
                @foreach($i['lintasan'] as $l)
                    <tr>
                        <td>Kelas {{ $l['tingkat'] }}</td>
                        <td>{{ $l['tahun_ajaran'] }} {{ $l['semester'] }}</td>
                        <td>{{ $l['jumlah_bukti'] }}</td>
                        <td>{{ $l['skor'] !== null ? number_format($l['skor'], 1) : 'belum cukup' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
        @if(($i['jumlah_tanpa_jenjang_historis'] ?? 0) > 0)
            <div style="font-size:.75rem;color:#64748b;margin-top:4px">
                Catatan: {{ $i['jumlah_tanpa_jenjang_historis'] }} bukti pada indikator ini berstatus <em>jenjang historis tidak diketahui</em> sehingga tidak dimasukkan ke tren kelas 4–6.
            </div>
        @endif

        {{-- Tindak lanjut guru --}}
        @php($tl = $tindakLanjut->get($kode))
        @if($tl)
            <form method="POST" action="{{ route('guru.tka-kesiapan.perbarui', $tl->uuid) }}" style="margin-top:10px;border-top:1px solid #e2e8f0;padding-top:10px">
                @csrf @method('PATCH')
                <label>Rekomendasi (berbasis bukti, dapat disunting guru)
                    <textarea name="rekomendasi" rows="3" class="form-control" maxlength="2000">{{ $tl->rekomendasi }}</textarea></label>
                <label>Status tindak lanjut
                    <select name="status" class="form-control">
                        @foreach($statusTindakLanjut as $st)<option value="{{ $st }}" @selected($tl->status === $st)>{{ KesiapanLabel::statusTindakLanjut($st) }}</option>@endforeach
                    </select></label>
                <label>Catatan guru <textarea name="catatan_guru" rows="2" class="form-control" maxlength="1000">{{ $tl->catatan_guru }}</textarea></label>
                <label>Alasan peninjauan/perubahan (opsional, untuk audit)
                    <input type="text" name="alasan" class="form-control" maxlength="500" placeholder="mis. disesuaikan setelah observasi kelas"></label>
                <button class="btn btn-primary btn-sm" style="margin-top:6px" type="submit">Simpan tindak lanjut</button>
                @if($tl->bukti_ref)
                    <details style="margin-top:6px"><summary>Bukti yang mendasari saran ({{ count($tl->bukti_ref) }})</summary>
                        <ul>@foreach($tl->bukti_ref as $b)<li>{{ $b['judul'] }} — {{ $b['tanggal'] }}, nilai {{ $b['nilai_asli'] ?? $b['skor'] }} ({{ $b['sumber'] }}, skala {{ $b['skala'] }})</li>@endforeach</ul></details>
                @endif
                @if($tl->revisi->isNotEmpty())
                    <details style="margin-top:6px"><summary>Riwayat revisi ({{ $tl->revisi->count() }})</summary>
                        <ul>@foreach($tl->revisi as $r)<li>{{ $r->created_at->format('d/m/Y H:i') }} — {{ $r->user?->nama_lengkap }}: {{ $r->status_sebelum }} → {{ $r->status_sesudah }}@if($r->alasan) (alasan: {{ $r->alasan }})@endif</li>@endforeach</ul></details>
                @endif
            </form>
        @endif
    </div>
@endforeach
</div>

<form method="POST" action="{{ route('guru.tka-kesiapan.susun', array_merge(['uuid' => $siswa->uuid], $filter->keQuery())) }}" style="margin-bottom:20px">
    @csrf
    <button class="btn btn-outline btn-sm" type="submit">Susun saran tindak lanjut dari bukti ({{ $periode['tahun_ajaran'] }} {{ $periode['semester'] }})</button>
    <span style="font-size:.8rem">Saran hanya dibuat untuk indikator dengan bukti cukup yang memerlukan dukungan/perhatian tren; nilai sumber tidak pernah diubah.</span>
</form>

{{-- Kelengkapan data & diagnostik deduplikasi --}}
<div class="card" style="margin-bottom:20px;padding:14px">
    <h4>Kelengkapan data &amp; keterangan validasi</h4>
    <ul>
        @forelse($profil['kelengkapan']['indikator_belum_cukup'] as $k)<li>{{ $indikator[$k]['nama'] }}: bukti belum cukup dinilai</li>@empty<li>Semua indikator pada filter ini memiliki bukti cukup.</li>@endforelse
        @foreach($profil['tidak_terpetakan'] as $s => $n)<li>{{ $n }} bukti {{ config("tka_kesiapan.sumber.$s.label", $s) }} belum terpetakan ke indikator (Admin dapat menambah pemetaan).</li>@endforeach
        @if(($profil['tanpa_jenjang_historis']['jumlah'] ?? 0) > 0)
            <li>{{ $profil['tanpa_jenjang_historis']['jumlah'] }} bukti berstatus <em>jenjang historis tidak diketahui</em> (tidak dipakai untuk tren kelas 4 → 5 → 6).</li>
        @endif
        @php($diag = $profil['diagnostik'] ?? [])
        @if(array_sum($diag) > 0)
            <li>Rekaman yang dieksklusi sistem:
                @if(($diag['duplikat_kuis_nilai'] ?? 0) > 0) {{ $diag['duplikat_kuis_nilai'] }} nilai salinan kuis; @endif
                @if(($diag['duplikat_tka_sync'] ?? 0) > 0) {{ $diag['duplikat_tka_sync'] }} salinan sinkronisasi TKA; @endif
                @if(($diag['duplikat_attempt_ulang'] ?? 0) > 0) {{ $diag['duplikat_attempt_ulang'] }} percobaan ujian lama; @endif
                @if(($diag['ujian_belum_final'] ?? 0) > 0) {{ $diag['ujian_belum_final'] }} ujian belum selesai/dinilai; @endif
                @if(($diag['ujian_dibatalkan_atau_draft'] ?? 0) > 0) {{ $diag['ujian_dibatalkan_atau_draft'] }} sesi draft/dibatalkan; @endif
                @if(($diag['skala_atau_nilai_kosong'] ?? 0) > 0) {{ $diag['skala_atau_nilai_kosong'] }} nilai kosong/tidak berskala; @endif
                @if(($diag['kalender_tidak_valid'] ?? 0) > 0) {{ $diag['kalender_tidak_valid'] }} catatan kalender tidak valid; @endif
            </li>
        @endif
    </ul>
</div>

<div class="card">
    <div class="card-header"><h3>Linimasa bukti belajar</h3></div>
    <div class="table-wrapper"><table class="table">
        <thead><tr><th>Tanggal</th><th>Sumber</th><th>Judul</th><th>Indikator</th><th>Nilai asli</th><th>Skala</th><th>Normalisasi instrumen</th><th>Periode &amp; jenjang</th><th>Catatan validasi</th></tr></thead>
        <tbody>
        @forelse($profil['bukti'] as $b)
            <tr>
                <td>{{ $b['tanggal'] }}</td>
                <td>{{ $b['sumber_label'] }}</td>
                <td>{{ $b['judul'] }}</td>
                <td>{{ $b['indikator'] ? $indikator[$b['indikator']]['nama'] : 'Belum terpetakan' }}</td>
                <td>{{ $b['nilai_asli'] }}</td>
                <td>{{ $b['skala'] }}</td>
                <td>{{ number_format($b['skor'], 1) }}</td>
                <td>{{ $b['tahun_ajaran'] }} {{ $b['semester'] }} · {{ KesiapanLabel::tingkatBukti($b['tingkat'], $b['tingkat_sumber'] ?? null) }}</td>
                <td>{{ $b['catatan'] ?? 'Sah (skala jelas, tanpa duplikat)' }}</td>
            </tr>
        @empty
            <tr><td colspan="9">Belum ada bukti pada filter ini.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
