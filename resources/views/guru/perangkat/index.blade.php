@extends('layouts.app')

@section('title', 'Perangkat Ajar - SIMANTAP')
@section('page_title', 'Perangkat Ajar & Administrasi Guru')
@section('page_subtitle', 'CP, TP, ATP, Prota, Promes, Modul Ajar (Pembelajaran Mendalam), KKM/KKTP & Cetak PDF')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('header_actions')
    <a href="{{ route('guru.pm.generate.index') }}" class="btn btn-ghost btn-sm">
        <i class="fas fa-magic"></i> Buat dengan AI / Batch
    </a>
    <a href="{{ route('guru.perangkat.jurnal.index') }}" class="btn btn-ghost btn-sm">
        <i class="fas fa-book-reader"></i> Jurnal Mengajar ({{ $jumlahJurnal }})
    </a>
    <a href="{{ route('guru.perangkat.create', ['jenis' => $jenisFilter ?: 'modul_ajar']) }}" class="btn btn-sm">
        <i class="fas fa-plus"></i> Buat Perangkat Ajar
    </a>
@endsection

@section('content')
    @include('guru.pm._nav')

    {{-- Tab cepat jenis perangkat (termasuk CP, TP, ATP) --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-body" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between">
            <div style="display:flex;flex-wrap:wrap;gap:6px">
                <a href="{{ route('guru.perangkat.index') }}"
                   class="btn btn-sm {{ $jenisFilter === '' ? '' : 'btn-ghost' }}">
                    Semua ({{ array_sum($countsByJenis) }})
                </a>
                @foreach($jenisRingkas as $kode => $labelSingkat)
                    <a href="{{ route('guru.perangkat.index', ['jenis' => $kode]) }}"
                       class="btn btn-sm {{ $jenisFilter === $kode ? '' : 'btn-ghost' }}">
                        {{ $labelSingkat }} ({{ $countsByJenis[$kode] ?? 0 }})
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('guru.perangkat.index') }}" style="display:flex;gap:8px;align-items:center">
                @if($jenisFilter)
                    <input type="hidden" name="jenis" value="{{ $jenisFilter }}">
                @endif
                <input type="text" name="q" value="{{ $cari }}" placeholder="Cari judul, mapel, kelas..."
                       style="padding:7px 10px;border:1px solid #E4E7EC;border-radius:8px;font-size:13px;min-width:190px">
                <button type="submit" class="btn btn-sm btn-ghost"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </div>

    {{-- Pintasan Buat Cepat per Jenis --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-body">
            <div style="font-size:12px;font-weight:700;color:#667085;text-transform:uppercase;letter-spacing:.4px;margin-bottom:10px">
                Buat Cepat dengan Template Kurikulum Merdeka
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px">
                @foreach($jenisLabels as $kode => $label)
                    <a href="{{ route('guru.perangkat.create', ['jenis' => $kode]) }}" class="btn btn-ghost btn-sm">
                        <i class="fas fa-file-alt"></i> + {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Daftar Perangkat Milik Guru --}}
    <div class="card" style="margin-bottom:18px">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
            <strong>Daftar Perangkat Ajar Saya</strong>
            <span style="font-size:12px;color:#667085">Menampilkan {{ $perangkatList->count() }} dari {{ $perangkatList->total() }} dokumen</span>
        </div>
        <div class="card-body" style="padding:0">
            @if($perangkatList->isEmpty())
                <div style="padding:36px 20px;text-align:center;color:#667085">
                    <div style="font-size:28px;margin-bottom:8px;opacity:.45"><i class="fas fa-folder-open"></i></div>
                    <div style="font-weight:600;color:#344054;margin-bottom:4px">Belum ada perangkat ajar tersimpan</div>
                    <p style="font-size:13px;margin:0 0 14px">Mulai menyusun CP, TP, ATP, Prota, Promes, Modul Ajar, atau KKM dengan template siap pakai.</p>
                    <a href="{{ route('guru.perangkat.create', ['jenis' => $jenisFilter ?: 'cp']) }}" class="btn btn-sm">
                        <i class="fas fa-plus"></i> Buat Perangkat Pertama
                    </a>
                </div>
            @else
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Jenis</th>
                                <th>Judul Dokumen</th>
                                <th>Mapel &amp; Kelas</th>
                                <th>Periode</th>
                                <th>Status</th>
                                <th style="text-align:right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($perangkatList as $item)
                                <tr>
                                    <td>
                                        <span class="badge badge-info">{{ $item->jenisRingkas() }}</span>
                                    </td>
                                    <td>
                                        <a href="{{ route('guru.perangkat.show', $item) }}" style="font-weight:600;color:#101828;text-decoration:none">
                                            {{ $item->judul }}
                                        </a>
                                        @if($item->kelasDigital)
                                            <div style="font-size:11px;color:#667085">
                                                <i class="fas fa-chalkboard"></i> Terhubung: {{ $item->kelasDigital->nama_kelas }}
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <div>{{ $item->mata_pelajaran }}</div>
                                        <small style="color:#667085">
                                            Kelas {{ $item->rombel?->nama_rombel ?? $item->kelas }}
                                            @if($item->fase) • Fase {{ $item->fase }} @endif
                                        </small>
                                    </td>
                                    <td>
                                        <div>{{ $item->semester ?? '-' }}</div>
                                        <small style="color:#667085">{{ $item->tahun_ajaran ?? '-' }}</small>
                                    </td>
                                    <td>
                                        <span class="badge {{ $item->status === 'final' ? 'badge-ok' : 'badge-warn' }}">
                                            {{ $item->status === 'final' ? 'Final' : 'Draf' }}
                                        </span>
                                    </td>
                                    <td style="text-align:right;white-space:nowrap">
                                        <a href="{{ route('guru.perangkat.show', $item) }}" class="btn btn-ghost btn-sm" title="Lihat">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('guru.perangkat.pdf', $item) }}" target="_blank" class="btn btn-ghost btn-sm" title="Cetak PDF">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                        <a href="{{ route('guru.perangkat.edit', $item) }}" class="btn btn-ghost btn-sm" title="Ubah">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" action="{{ route('guru.perangkat.destroy', $item) }}" style="display:inline"
                                              onsubmit="return confirm('Hapus perangkat ajar ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-sm" style="color:#D92D20" title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($perangkatList->hasPages())
                    <div style="padding:12px 16px">{{ $perangkatList->links() }}</div>
                @endif
            @endif
        </div>
    </div>

    {{-- Hak Baca Read-Only Wali Kelas (Gelombang 6B) --}}
    @if($adalahWaliKelas)
        <div class="card">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
                <strong><i class="fas fa-user-check"></i> Perangkat Guru Pengajar Kelas Binaan Saya (Baca-Saja)</strong>
                <span class="badge badge-info">Akses Wali Kelas</span>
            </div>
            <div class="card-body" style="padding:0">
                @if($perangkatPengajarKelas->isEmpty())
                    <div style="padding:20px;color:#667085;font-size:13px">
                        Belum ada perangkat ajar yang dibagikan oleh guru mata pelajaran lain untuk kelas binaan Anda.
                    </div>
                @else
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Jenis</th>
                                    <th>Judul</th>
                                    <th>Guru Pengajar</th>
                                    <th>Mapel / Kelas</th>
                                    <th style="text-align:right">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($perangkatPengajarKelas as $dok)
                                    <tr>
                                        <td><span class="badge badge-info">{{ $dok->jenisRingkas() }}</span></td>
                                        <td><strong>{{ $dok->judul }}</strong></td>
                                        <td>{{ $dok->guru?->nama_lengkap ?? '-' }}</td>
                                        <td>{{ $dok->mata_pelajaran }} • {{ $dok->rombel?->nama_rombel ?? $dok->kelas }}</td>
                                        <td style="text-align:right;white-space:nowrap">
                                            <a href="{{ route('guru.perangkat.show', $dok) }}" class="btn btn-ghost btn-sm">
                                                <i class="fas fa-eye"></i> Lihat
                                            </a>
                                            <a href="{{ route('guru.perangkat.pdf', $dok) }}" target="_blank" class="btn btn-ghost btn-sm">
                                                <i class="fas fa-file-pdf"></i> PDF
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
    @endif
@endsection
