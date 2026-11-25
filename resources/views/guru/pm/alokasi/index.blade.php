@extends($layout ?? 'layouts.app')

@section('title', 'Alokasi Waktu - Pembelajaran Mendalam')
@section('page_title', 'Alokasi Waktu Struktur Kurikulum')
@section('page_subtitle', 'Distribusi JP/minggu Intrakurikuler & Kokurikuler Kelas I–VI (Permendikdasmen No. 13 Tahun 2025)')

@if(!($canEdit ?? false))
    @section('sidebar')
        @include('guru.partials.sidebar')
    @endsection
@endif

@section('header_actions')
    <a href="{{ route(($routePrefix ?? 'guru.pm').'.alokasi.pdf', ['tahun_ajaran' => $tahunAjaran]) }}" target="_blank" class="btn btn-ghost btn-sm">
        <i class="fas fa-print"></i> Cetak Semua Kelas
    </a>
    @if($canEdit ?? false)
        <form method="POST" action="{{ route('admin.pm.alokasi.standar') }}" style="display:inline"
              onsubmit="return confirm('Isi seluruh tabel sesuai standar Permendikdasmen No. 13 Tahun 2025?');">
            @csrf
            <input type="hidden" name="tahun_ajaran" value="{{ $tahunAjaran }}">
            <button type="submit" class="btn btn-sm">
                <i class="fas fa-wand-magic-sparkles"></i> Isi Standar Permendikdasmen 2025
            </button>
        </form>
    @endif
@endsection

@section('content')
    @if(!($canEdit ?? false))
        @include('guru.pm._nav')
        <div style="margin-bottom:12px;padding:10px 14px;background:#EFF8FF;border:1px solid #B2DDFF;border-radius:8px;font-size:12px;color:#175CD3">
            <i class="fas fa-info-circle"></i> <strong>Mode Baca (Read-Only):</strong> Master Alokasi Waktu struktur kurikulum dikelola oleh Admin/Operator Sekolah.
        </div>
    @endif

    {{-- Ringkasan JP per Tingkat --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-bottom:16px">
        @foreach($ringkasanTingkat as $tingkat => $sum)
            <div class="card">
                <div class="card-body" style="padding:12px">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
                        <strong style="font-size:13px">Kelas {{ $tingkat }}</strong>
                        <a href="{{ route('guru.pm.alokasi.pdf', ['tahun_ajaran' => $tahunAjaran, 'tingkat' => $tingkat]) }}"
                           target="_blank" title="Cetak Kelas {{ $tingkat }}" style="font-size:11px;color:#475467">
                            <i class="fas fa-print"></i>
                        </a>
                    </div>
                    <div style="font-size:20px;font-weight:700;color:#101828">{{ $sum['total_minggu'] }} <span style="font-size:12px;font-weight:500;color:#667085">JP/mgg</span></div>
                    <div style="font-size:11px;color:#667085;margin-top:2px">
                        Intra: {{ $sum['intra'] }} · Ko: {{ $sum['ko'] }}
                    </div>
                    <div style="font-size:11px;color:#475467;margin-top:2px">
                        {{ $sum['total_tahun'] }} JP/tahun ({{ $sum['minggu_efektif'] }} mgg)
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Matriks Alokasi Waktu --}}
    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
            <div>
                <strong>Matriks Alokasi Waktu (JP / Minggu)</strong>
                <span style="font-size:12px;color:#667085;margin-left:6px">Tahun Ajaran {{ $tahunAjaran }} · 1 JP = 35 menit</span>
            </div>
        </div>
        @php
            $daftarTingkatAlokasi = $tingkatList ?? \App\Support\SchoolLevel::tingkatForJenjang(\App\Support\SchoolLevel::current());
        @endphp
        <form method="POST" action="{{ route(($routePrefix ?? 'guru.pm').'.alokasi.store') }}">
            @csrf
            <input type="hidden" name="tahun_ajaran" value="{{ $tahunAjaran }}">
            <div class="card-body" style="padding:0">
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th rowspan="2" style="min-width:220px;vertical-align:middle">Mata Pelajaran</th>
                                @foreach($daftarTingkatAlokasi as $t)
                                    <th colspan="2" style="text-align:center;border-left:1px solid #EAECF0">Kelas {{ $t }}</th>
                                @endforeach
                            </tr>
                            <tr>
                                @foreach($daftarTingkatAlokasi as $t)
                                    <th style="text-align:center;font-size:11px;border-left:1px solid #EAECF0">Intra</th>
                                    <th style="text-align:center;font-size:11px">Ko</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($namaMapelList as $namaMapel)
                                <tr>
                                    <td style="font-weight:600;color:#101828">{{ $namaMapel }}</td>
                                    @foreach($daftarTingkatAlokasi as $t)
                                        @php
                                            $intra = $matrix[$namaMapel][$t]['intra'] ?? 0;
                                            $ko = $matrix[$namaMapel][$t]['ko'] ?? 0;
                                        @endphp
                                        <td style="text-align:center;border-left:1px solid #EAECF0;padding:6px">
                                            @if($canEdit ?? false)
                                                <input type="number" min="0" max="30"
                                                       name="a[{{ $namaMapel }}][{{ $t }}][intra]"
                                                       value="{{ $intra }}"
                                                       style="width:52px;text-align:center;padding:4px;border:1px solid #D0D5DD;border-radius:6px;font-size:13px">
                                            @else
                                                {{ $intra }}
                                            @endif
                                        </td>
                                        <td style="text-align:center;padding:6px">
                                            @if($canEdit ?? false)
                                                <input type="number" min="0" max="20"
                                                       name="a[{{ $namaMapel }}][{{ $t }}][ko]"
                                                       value="{{ $ko }}"
                                                       style="width:46px;text-align:center;padding:4px;border:1px solid #D0D5DD;border-radius:6px;font-size:13px">
                                            @else
                                                {{ $ko }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr style="background:#F9FAFB;font-weight:700">
                                <td>Total JP / Minggu</td>
                                @foreach($daftarTingkatAlokasi as $t)
                                    <td style="text-align:center;border-left:1px solid #EAECF0">{{ $ringkasanTingkat[$t]['intra'] ?? 0 }}</td>
                                    <td style="text-align:center">{{ $ringkasanTingkat[$t]['ko'] ?? 0 }}</td>
                                @endforeach
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            @if($canEdit ?? false)
                <div class="card-body" style="border-top:1px solid #EAECF0;display:flex;justify-content:flex-end">
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Simpan Alokasi Waktu
                    </button>
                </div>
            @endif
        </form>
    </div>
@endsection
