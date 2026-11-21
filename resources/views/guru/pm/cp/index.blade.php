@extends('layouts.app')

@section('title', 'Capaian Pembelajaran (CP) - Pembelajaran Mendalam')
@section('page_title', 'Capaian Pembelajaran (CP)')
@section('page_subtitle', 'Repositori CP per Mata Pelajaran & Fase (Acuan BSKAP No. 046/H/KR/2025) sebagai dasar penyusunan TP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('header_actions')
    <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('boxImportCp').style.display = document.getElementById('boxImportCp').style.display === 'none' ? 'block' : 'none'">
        <i class="fas fa-file-import"></i> Import Excel
    </button>
    <a href="{{ route('guru.pm.cp.excel', ['mapel' => $mapelFilter, 'fase' => $faseFilter]) }}" class="btn btn-ghost btn-sm">
        <i class="fas fa-file-excel"></i> Export Excel
    </a>
    <a href="{{ route('guru.pm.cp.pdf', ['mapel' => $mapelFilter, 'fase' => $faseFilter]) }}" target="_blank" class="btn btn-ghost btn-sm">
        <i class="fas fa-print"></i> Cetak PDF
    </a>
@endsection

@section('content')
    @include('guru.pm._nav')

    {{-- Form Import Excel CP --}}
    <div id="boxImportCp" class="card" style="display:none;margin-bottom:16px;border:1px solid #2E90FA">
        <div class="card-header"><strong><i class="fas fa-file-import"></i> Import Capaian Pembelajaran dari Excel (.xlsx / .csv)</strong></div>
        <div class="card-body">
            <form method="POST" action="{{ route('guru.pm.cp.import') }}" enctype="multipart/form-data" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center">
                @csrf
                <input type="file" name="file_excel" accept=".xlsx,.xls,.csv" required
                       style="padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                <button type="submit" class="btn btn-sm"><i class="fas fa-upload"></i> Mulai Import</button>
                <small style="color:#667085">Kolom: <code>No | Mata Pelajaran | Fase | Elemen | Capaian Pembelajaran</code> (sama dengan format Export Excel).</small>
            </form>
        </div>
    </div>

    {{-- Filter --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-body">
            <form method="GET" action="{{ route('guru.pm.cp.index') }}" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
                <select name="mapel" style="padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    <option value="">Semua Mata Pelajaran</option>
                    @foreach($mapelList as $m)
                        <option value="{{ $m->nama }}" {{ $mapelFilter === $m->nama ? 'selected' : '' }}>{{ $m->nama }}</option>
                    @endforeach
                </select>
                <select name="fase" style="padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    <option value="">Semua Fase</option>
                    @foreach($faseLabels as $kode => $label)
                        <option value="{{ $kode }}" {{ $faseFilter === $kode ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <input type="text" name="q" value="{{ $cari }}" placeholder="Cari elemen / deskripsi CP..."
                       style="padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px;min-width:210px">
                <button type="submit" class="btn btn-sm">Terapkan</button>
                @if($mapelFilter || $faseFilter || $cari)
                    <a href="{{ route('guru.pm.cp.index') }}" class="btn btn-sm btn-ghost">Reset</a>
                @endif
            </form>
        </div>
    </div>

    {{-- Form Tambah Manual & Susun dengan AI --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;margin-bottom:16px">
        <div class="card">
            <div class="card-header"><strong><i class="fas fa-plus-circle"></i> Tambah Capaian Pembelajaran</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('guru.pm.cp.store') }}" style="display:grid;gap:10px">
                    @csrf
                    <div style="display:grid;grid-template-columns:1fr 140px;gap:8px">
                        <div>
                            <label style="font-size:12px;font-weight:600">Mata Pelajaran *</label>
                            <select name="nama_mapel" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                                @foreach($mapelList as $m)
                                    <option value="{{ $m->nama }}" {{ $mapelFilter === $m->nama ? 'selected' : '' }}>{{ $m->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:600">Fase *</label>
                            <select name="fase" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                                @foreach($faseLabels as $kode => $label)
                                    <option value="{{ $kode }}" {{ $faseFilter === $kode ? 'selected' : '' }}>Fase {{ $kode }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 90px;gap:8px">
                        <div>
                            <label style="font-size:12px;font-weight:600">Elemen</label>
                            <input type="text" name="elemen" placeholder="Misal: Bilangan / Membaca dan Memirsa"
                                   style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:600">Urutan</label>
                            <input type="number" name="urutan" value="1" min="1"
                                   style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600">Rumusan Capaian Pembelajaran *</label>
                        <textarea name="deskripsi" rows="3" required placeholder="Tuliskan rumusan CP sesuai dokumen BSKAP..."
                                  style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px"></textarea>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-sm"><i class="fas fa-save"></i> Simpan CP</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong><i class="fas fa-magic"></i> Susun CP dengan AI (Gemini)</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('guru.pm.cp.ai') }}" style="display:grid;gap:10px">
                    @csrf
                    <div style="display:grid;grid-template-columns:1fr 140px;gap:8px">
                        <div>
                            <label style="font-size:12px;font-weight:600">Mata Pelajaran *</label>
                            <select name="nama_mapel" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                                @foreach($mapelList as $m)
                                    <option value="{{ $m->nama }}" {{ $mapelFilter === $m->nama ? 'selected' : '' }}>{{ $m->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:600">Fase *</label>
                            <select name="fase" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                                @foreach($faseLabels as $kode => $label)
                                    <option value="{{ $kode }}" {{ $faseFilter === $kode ? 'selected' : '' }}>Fase {{ $kode }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600">Catatan untuk AI (opsional)</label>
                        <textarea name="catatan" rows="3" placeholder="Misal: Fokuskan elemen literasi numerasi dan konteks lingkungan sekolah..."
                                  style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px"></textarea>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                        <small style="color:#667085">Menghasilkan draf CP per elemen yang dapat Anda sunting.</small>
                        <button type="submit" class="btn btn-sm" {{ !$aiQuota['configured'] ? 'disabled' : '' }}>
                            <i class="fas fa-sparkles"></i> Susun dengan AI
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Daftar CP --}}
    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <strong>Daftar Capaian Pembelajaran</strong>
            <span style="font-size:12px;color:#667085">Total {{ $cpList->total() }} CP</span>
        </div>
        <div class="card-body" style="padding:0">
            @if($cpList->isEmpty())
                <div style="padding:32px;text-align:center;color:#667085">
                    Belum ada data Capaian Pembelajaran. Tambahkan secara manual atau gunakan tombol <strong>Susun dengan AI</strong> di atas.
                </div>
            @else
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width:46px">No</th>
                                <th style="width:170px">Mata Pelajaran</th>
                                <th style="width:80px">Fase</th>
                                <th style="width:150px">Elemen</th>
                                <th>Capaian Pembelajaran</th>
                                <th style="width:90px;text-align:right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cpList as $idx => $cp)
                                <tr>
                                    <td>{{ $cpList->firstItem() + $idx }}</td>
                                    <td style="font-weight:600">{{ $cp->nama_mapel }}</td>
                                    <td><span class="badge badge-info" style="white-space:nowrap">Fase {{ $cp->fase }}</span></td>
                                    <td>{{ $cp->elemen }}</td>
                                    <td style="font-size:13px;line-height:1.5">
                                        {{ $cp->deskripsi }}
                                        @if($cp->sumber === 'ai')
                                            <span class="badge badge-warning" style="margin-left:6px;font-size:10px">AI</span>
                                        @endif
                                    </td>
                                    <td style="text-align:right;white-space:nowrap">
                                        <button type="button" class="btn btn-sm btn-ghost"
                                                onclick="openEditCp({{ json_encode(['url' => route('guru.pm.cp.update', $cp), 'nama_mapel' => $cp->nama_mapel, 'fase' => $cp->fase, 'elemen' => $cp->elemen, 'urutan' => $cp->urutan, 'deskripsi' => $cp->deskripsi]) }})"
                                                title="Edit CP">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" action="{{ route('guru.pm.cp.destroy', $cp) }}"
                                              onsubmit="return confirm('Hapus CP ini?');" style="display:inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-ghost" style="color:#D92D20">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="padding:12px 16px">{{ $cpList->links() }}</div>
            @endif
        </div>
    </div>

    {{-- Modal Edit Inline CP --}}
    <div id="modalEditCp" style="display:none;position:fixed;inset:0;background:rgba(16,24,40,.45);z-index:1000;align-items:center;justify-content:center;padding:16px">
        <div class="card" style="width:100%;max-width:560px">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
                <strong>Edit Capaian Pembelajaran</strong>
                <button type="button" class="btn btn-sm btn-ghost" onclick="document.getElementById('modalEditCp').style.display='none'">×</button>
            </div>
            <div class="card-body">
                <form id="formEditCp" method="POST" style="display:grid;gap:10px">
                    @csrf
                    @method('PUT')
                    <div style="display:grid;grid-template-columns:1fr 120px;gap:8px">
                        <div>
                            <label style="font-size:12px;font-weight:600">Mata Pelajaran *</label>
                            <input type="text" id="editCpMapel" name="nama_mapel" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:600">Fase *</label>
                            <select id="editCpFase" name="fase" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                                @foreach($faseLabels as $kode => $label)
                                    <option value="{{ $kode }}">Fase {{ $kode }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 90px;gap:8px">
                        <div>
                            <label style="font-size:12px;font-weight:600">Elemen</label>
                            <input type="text" id="editCpElemen" name="elemen" style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:600">Urutan</label>
                            <input type="number" id="editCpUrutan" name="urutan" min="1" style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600">Rumusan CP *</label>
                        <textarea id="editCpDesk" name="deskripsi" rows="4" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px"></textarea>
                    </div>
                    <div style="display:flex;justify-content:flex-end;gap:8px">
                        <button type="button" class="btn btn-sm btn-ghost" onclick="document.getElementById('modalEditCp').style.display='none'">Batal</button>
                        <button type="submit" class="btn btn-sm"><i class="fas fa-save"></i> Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        function openEditCp(d) {
            document.getElementById('formEditCp').action = d.url;
            document.getElementById('editCpMapel').value = d.nama_mapel || '';
            document.getElementById('editCpFase').value = d.fase || 'A';
            document.getElementById('editCpElemen').value = d.elemen || '';
            document.getElementById('editCpUrutan').value = d.urutan || 1;
            document.getElementById('editCpDesk').value = d.deskripsi || '';
            document.getElementById('modalEditCp').style.display = 'flex';
        }
    </script>
@endsection
