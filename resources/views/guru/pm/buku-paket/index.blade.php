@extends('layouts.app')

@section('title', 'Buku Paket (SIBI) - Pembelajaran Mendalam')
@section('page_title', 'Buku Paket (SIBI Kemendikdasmen)')
@section('page_subtitle', 'Kelola buku teks utama dan daftar isi bab per kelas & mapel sebagai referensi Prota, Modul Ajar & Asesmen')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('header_actions')
    <a href="https://buku.kemendikdasmen.go.id/katalog/" target="_blank" rel="noopener noreferrer" class="btn btn-ghost btn-sm">
        <i class="fas fa-external-link-alt"></i> Buka Katalog SIBI
    </a>
@endsection

@section('content')
    @include('guru.pm._nav')

    {{-- Pilihan Tingkat & Tab Mapel --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-body" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between">
            <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">
                <span style="font-size:12px;font-weight:700;color:#667085;margin-right:4px">KELAS:</span>
                @foreach($pmAllowedTingkat ?? [1,2,3,4,5,6] as $t)
                    <a href="{{ route('guru.pm.buku-paket.index', ['tingkat' => $t, 'mapel' => $mapelAktif]) }}"
                       class="btn btn-sm {{ $tingkat === $t ? '' : 'btn-ghost' }}">Kelas {{ $t }}</a>
                @endforeach
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:6px">
                @foreach($mapelList as $m)
                    <a href="{{ route('guru.pm.buku-paket.index', ['tingkat' => $tingkat, 'mapel' => $m->nama]) }}"
                       class="btn btn-sm {{ $mapelAktif === $m->nama ? '' : 'btn-ghost' }}">
                        {{ $m->kode }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px">
        {{-- Form Buku Paket --}}
        <div class="card">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                <strong>{{ $mapelAktif }} — Kelas {{ $tingkat }}</strong>
                <form method="POST" action="{{ route('guru.pm.buku-paket.ai') }}" style="display:inline">
                    @csrf
                    <input type="hidden" name="nama_mapel" value="{{ $mapelAktif }}">
                    <input type="hidden" name="tingkat" value="{{ $tingkat }}">
                    <input type="hidden" name="judul" value="{{ $bukuAktif?->judul ?: ($mapelAktif.' Kelas '.$tingkat.' Kurikulum Merdeka') }}">
                    <button type="submit" class="btn btn-sm btn-ghost" {{ !$aiQuota['configured'] ? 'disabled' : '' }}>
                        <i class="fas fa-magic"></i> Susun Daftar Isi dengan AI
                    </button>
                </form>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('guru.pm.buku-paket.store') }}" style="display:grid;gap:12px">
                    @csrf
                    <input type="hidden" name="nama_mapel" value="{{ $mapelAktif }}">
                    <input type="hidden" name="tingkat" value="{{ $tingkat }}">

                    <div>
                        <label style="font-size:12px;font-weight:600">Judul Buku *</label>
                        <input type="text" name="judul" required
                               value="{{ old('judul', $bukuAktif?->judul) }}"
                               placeholder="Misal: Matematika untuk SD/MI Kelas {{ $tingkat }}"
                               style="width:100%;padding:8px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:600">Penulis / Penerbit</label>
                        <input type="text" name="penulis"
                               value="{{ old('penulis', $bukuAktif?->penulis) }}"
                               placeholder="Misal: Kemendikdasmen / Pusat Perbukuan"
                               style="width:100%;padding:8px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:600">Tautan Buku di SIBI</label>
                        <input type="url" name="tautan_sibi"
                               value="{{ old('tautan_sibi', $bukuAktif?->tautan_sibi) }}"
                               placeholder="https://buku.kemendikdasmen.go.id/katalog/..."
                               style="width:100%;padding:8px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:600">Daftar Isi (satu bab per baris)</label>
                        <textarea name="daftar_isi" rows="8"
                                  placeholder="Bab 1: Bilangan Cacah sampai 10&#10;Bab 2: Penjumlahan dan Pengurangan&#10;..."
                                  style="width:100%;padding:8px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px;line-height:1.5">{{ old('daftar_isi', $bukuAktif?->daftar_isi) }}</textarea>
                    </div>

                    <div>
                        <button type="submit" class="btn"><i class="fas fa-save"></i> Simpan Buku Paket</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Ringkasan Buku Paket Kelas --}}
        <div class="card">
            <div class="card-header"><strong>Ringkasan Buku Paket Kelas {{ $tingkat }}</strong></div>
            <div class="card-body" style="padding:0">
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Mata Pelajaran</th>
                                <th>Judul Buku</th>
                                <th style="width:70px;text-align:center">Bab</th>
                                <th style="width:70px;text-align:center">SIBI</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($mapelList as $m)
                                @php $b = $ringkasanKelas->get($m->nama); @endphp
                                <tr>
                                    <td style="font-weight:600">
                                        <a href="{{ route('guru.pm.buku-paket.index', ['tingkat' => $tingkat, 'mapel' => $m->nama]) }}"
                                           style="color:#101828;text-decoration:none">
                                            {{ $m->nama }}
                                        </a>
                                    </td>
                                    <td style="font-size:13px">{{ $b?->judul ?: '— Belum diisi' }}</td>
                                    <td style="text-align:center">{{ $b ? count($b->babList()) : 0 }}</td>
                                    <td style="text-align:center">
                                        @if($b?->tautan_sibi)
                                            <a href="{{ $b->tautan_sibi }}" target="_blank" rel="noopener noreferrer" title="Buka SIBI">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
