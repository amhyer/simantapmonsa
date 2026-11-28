@extends('layouts.admin-with-tabs')

@section('title', 'Nilai Rapor - SIMANTAP')

@section('page_title', 'Nilai Rapor')
@section('page_subtitle', 'Cetak hasil belajar per peserta didik maupun keseluruhan satu kelas (format e-Rapor SD)')

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Filter &amp; Pengaturan Hasil Cetak</h3>
        </div>
        <div class="card-body">
            <form method="GET" id="filterRaporForm" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
                <div>
                    <label for="fld-cetak-nilai-kelas" class="form-label">Kelas</label>
                    <select id="fld-cetak-nilai-kelas" name="kelas" class="form-control">
                        @foreach($kelasList as $k)
                            <option value="{{ $k }}" @selected($k === $kelas)>{{ $k }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="fld-cetak-nilai-semester" class="form-label">Semester</label>
                    <select id="fld-cetak-nilai-semester" name="semester" class="form-control">
                        <option value="Ganjil" @selected($semester === 'Ganjil')>Ganjil</option>
                        <option value="Genap" @selected($semester === 'Genap')>Genap</option>
                    </select>
                </div>
                <div>
                    <label for="fld-cetak-nilai-tahun-ajaran" class="form-label">Tahun Ajaran</label>
                    <input id="fld-cetak-nilai-tahun-ajaran" type="text" name="tahun_ajaran" value="{{ $tahunAjaran }}" maxlength="9" class="form-control" style="width:120px">
                </div>
                <div>
                    <label for="opt-kertas" class="form-label">Kertas</label>
                    <select id="opt-kertas" name="kertas" class="form-control" onchange="syncPrintLinks()">
                        <option value="A4" @selected(request('kertas', 'A4') === 'A4')>A4</option>
                        <option value="F4" @selected(request('kertas') === 'F4')>F4 (Folio)</option>
                    </select>
                </div>
                <div>
                    <label for="opt-m-kiri" class="form-label">Margin Kiri/Kanan (mm)</label>
                    <div style="display:flex;gap:6px">
                        <input id="opt-m-kiri" type="number" name="margin_kiri" value="{{ request('margin_kiri', 15) }}" min="5" max="40" class="form-control" style="width:68px" onchange="syncPrintLinks()">
                        <input id="opt-m-kanan" type="number" name="margin_kanan" value="{{ request('margin_kanan', 15) }}" min="5" max="40" class="form-control" style="width:68px" onchange="syncPrintLinks()">
                    </div>
                </div>
                <div>
                    <label for="opt-m-atas" class="form-label">Margin Atas/Bawah (mm)</label>
                    <div style="display:flex;gap:6px">
                        <input id="opt-m-atas" type="number" name="margin_atas" value="{{ request('margin_atas', 15) }}" min="5" max="40" class="form-control" style="width:68px" onchange="syncPrintLinks()">
                        <input id="opt-m-bawah" type="number" name="margin_bawah" value="{{ request('margin_bawah', 12) }}" min="5" max="40" class="form-control" style="width:68px" onchange="syncPrintLinks()">
                    </div>
                </div>
                <div>
                    <label for="opt-posisi-ks" class="form-label">Posisi TTD KS</label>
                    <select id="opt-posisi-ks" name="posisi_ttd_ks" class="form-control" onchange="syncPrintLinks()">
                        <option value="sejajar" @selected(request('posisi_ttd_ks', 'sejajar') === 'sejajar')>Sejajar Wali Kelas</option>
                        <option value="bawah" @selected(request('posisi_ttd_ks') === 'bawah')>Di bawah Wali Kelas</option>
                    </select>
                </div>
                <div>
                    <label for="opt-ttd" class="form-label">Tanda Tangan</label>
                    <select id="opt-ttd" name="tampil_ttd" class="form-control" onchange="syncPrintLinks()">
                        <option value="1" @selected(request('tampil_ttd', '1') === '1')>Isi Tanda Tangan</option>
                        <option value="0" @selected(request('tampil_ttd') === '0')>Tanpa Tanda Tangan</option>
                    </select>
                </div>
                <div>
                    <label for="opt-wali" class="form-label">Nama Wali Kelas</label>
                    <select id="opt-wali" name="tampil_wali" class="form-control" onchange="syncPrintLinks()">
                        <option value="1" @selected(request('tampil_wali', '1') === '1')>Tampilkan</option>
                        <option value="0" @selected(request('tampil_wali') === '0')>Kosongkan</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-sm">Tampilkan</button>
                </div>
            </form>
        </div>
    </div>

    @if($kelas)
        <div class="card">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                    <h3>{{ str_starts_with($kelas, 'Kelas ') ? $kelas : 'Kelas ' . $kelas }} — {{ $semester }} {{ $tahunAjaran }}</h3>
                    <span class="tag tag-mut">{{ $siswa->count() }} siswa</span>
                </div>
                @if($siswa->isNotEmpty())
                    <a id="btnCetakSatuKelas"
                       href="{{ route('admin.cetak.nilai.kelas', ['kelas' => $kelas, 'semester' => $semester, 'tahun_ajaran' => $tahunAjaran]) }}"
                       target="_blank"
                       class="btn btn-sm btn-primary"
                       data-base-url="{{ route('admin.cetak.nilai.kelas', ['kelas' => $kelas, 'semester' => $semester, 'tahun_ajaran' => $tahunAjaran]) }}">
                        <i class="fas fa-print"></i> Cetak Langsung Rapor 1 Kelas ({{ $siswa->count() }} Siswa)
                    </a>
                @endif
            </div>
            <div class="card-body tight">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>NISN</th>
                                <th style="text-align:center">Nilai Terisi</th>
                                <th>Cetak Dokumen Rapor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($siswa as $row)
                                <tr>
                                    <td>{{ $row->nama_peserta_didik }}</td>
                                    <td>{{ $row->nisn ?? '-' }}</td>
                                    <td style="text-align:center">{{ $row->nilai_erapot_count }} mapel</td>
                                    <td style="white-space:nowrap">
                                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                                            <a href="{{ route('admin.cetak.pelengkap', ['kelas' => $kelas]) }}" target="_blank" class="btn btn-sm btn-secondary">Pelengkap</a>
                                            <a href="{{ route('admin.cetak.pts.show', ['siswa' => $row->id, 'semester' => $semester, 'tahun_ajaran' => $tahunAjaran]) }}" target="_blank" class="btn btn-sm" style="background:#0284C7;color:#fff">Tengah Semester</a>
                                            <a href="{{ route('admin.cetak.nilai.show', ['siswa' => $row->id, 'semester' => $semester, 'tahun_ajaran' => $tahunAjaran]) }}"
                                               target="_blank"
                                               class="btn btn-sm js-rapor-link"
                                               data-base-url="{{ route('admin.cetak.nilai.show', ['siswa' => $row->id, 'semester' => $semester, 'tahun_ajaran' => $tahunAjaran]) }}">Semester</a>
                                            <a href="{{ route('admin.cetak.p5bk.show', ['siswa' => $row->id, 'semester' => $semester, 'tahun_ajaran' => $tahunAjaran]) }}" target="_blank" class="btn btn-sm" style="background:#059669;color:#fff">P5BK</a>
                                            <a href="{{ route('admin.cetak.buku-induk.show', ['siswa' => $row->id]) }}" target="_blank" class="btn btn-sm" style="background:#7C3AED;color:#fff">Buku Induk</a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr class="table-empty"><td colspan="4" style="padding:16px;text-align:center;color:#667085">Belum ada data siswa di kelas ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <script>
        function syncPrintLinks() {
            var params = new URLSearchParams({
                kertas: document.getElementById('opt-kertas')?.value || 'A4',
                margin_kiri: document.getElementById('opt-m-kiri')?.value || '15',
                margin_kanan: document.getElementById('opt-m-kanan')?.value || '15',
                margin_atas: document.getElementById('opt-m-atas')?.value || '15',
                margin_bawah: document.getElementById('opt-m-bawah')?.value || '12',
                posisi_ttd_ks: document.getElementById('opt-posisi-ks')?.value || 'sejajar',
                tampil_ttd: document.getElementById('opt-ttd')?.value || '1',
                tampil_wali: document.getElementById('opt-wali')?.value || '1'
            });
            var links = document.querySelectorAll('#btnCetakSatuKelas, .js-rapor-link');
            links.forEach(function (el) {
                var base = el.getAttribute('data-base-url');
                if (!base) return;
                el.href = base + (base.indexOf('?') >= 0 ? '&' : '?') + params.toString();
            });
        }
        document.addEventListener('DOMContentLoaded', syncPrintLinks);
    </script>
@endsection
