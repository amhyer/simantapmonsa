@extends('layouts.app')

@section('title', 'Tujuan Pembelajaran (TP) - Pembelajaran Mendalam')
@section('page_title', 'Bank Tujuan Pembelajaran (TP)')
@section('page_subtitle', 'Rumusan TP turunan CP yang menjadi sumber otomatis untuk ATP, KKTP, Prota, Prosem, RPM & Jurnal')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('header_actions')
    <form method="POST" action="{{ route('guru.pm.tp.sinkron-erapor') }}" style="display:inline"
          onsubmit="return confirm('Sinkronkan daftar TP ini ke modul TP e-Rapor Anda?');">
        @csrf
        <input type="hidden" name="mapel" value="{{ $mapelFilter }}">
        <input type="hidden" name="fase" value="{{ $faseFilter }}">
        <button type="submit" class="btn btn-sm">
            <i class="fas fa-sync-alt"></i> Sinkron ke TP e-Rapor
        </button>
    </form>
    <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('boxImportTp').style.display = document.getElementById('boxImportTp').style.display === 'none' ? 'block' : 'none'">
        <i class="fas fa-file-import"></i> Import Excel
    </button>
    <a href="{{ route('guru.pm.tp.excel', ['mapel' => $mapelFilter, 'fase' => $faseFilter]) }}" class="btn btn-ghost btn-sm">
        <i class="fas fa-file-excel"></i> Export Excel
    </a>
    <a href="{{ route('guru.pm.tp.pdf', ['mapel' => $mapelFilter, 'fase' => $faseFilter]) }}" target="_blank" class="btn btn-ghost btn-sm">
        <i class="fas fa-print"></i> Cetak PDF
    </a>
@endsection

@section('content')
    @include('guru.pm._nav')

    {{-- Form Import Excel TP --}}
    <div id="boxImportTp" class="card" style="display:none;margin-bottom:16px;border:1px solid #2E90FA">
        <div class="card-header"><strong><i class="fas fa-file-import"></i> Import Tujuan Pembelajaran dari Excel (.xlsx / .csv)</strong></div>
        <div class="card-body">
            <form method="POST" action="{{ route('guru.pm.tp.import') }}" enctype="multipart/form-data" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center">
                @csrf
                <input type="file" name="file_excel" accept=".xlsx,.xls,.csv" required
                       style="padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                <button type="submit" class="btn btn-sm"><i class="fas fa-upload"></i> Mulai Import</button>
                <small style="color:#667085">Kolom: <code>No | Mata Pelajaran | Fase | Elemen | Kode TP | Rumusan Tujuan Pembelajaran</code>.</small>
            </form>
        </div>
    </div>

    {{-- Filter --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-body">
            <form method="GET" action="{{ route('guru.pm.tp.index') }}" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
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
                <input type="text" name="q" value="{{ $cari }}" placeholder="Cari kode TP, elemen, rumusan..."
                       style="padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px;min-width:210px">
                <button type="submit" class="btn btn-sm">Terapkan</button>
                @if($mapelFilter || $faseFilter || $cari)
                    <a href="{{ route('guru.pm.tp.index') }}" class="btn btn-sm btn-ghost">Reset</a>
                @endif
            </form>
        </div>
    </div>

    {{-- Form Tambah Manual & AI --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;margin-bottom:16px">
        <div class="card">
            <div class="card-header"><strong><i class="fas fa-plus-circle"></i> Tambah Tujuan Pembelajaran</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('guru.pm.tp.store') }}" style="display:grid;gap:10px">
                    @csrf
                    <div style="display:grid;grid-template-columns:1fr 120px;gap:8px">
                        <div>
                            <label for="fld-pm-tp-nama-mapel" style="font-size:12px;font-weight:600">Mata Pelajaran *</label>
                            <select id="fld-pm-tp-nama-mapel" name="nama_mapel" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                                @foreach($mapelList as $m)
                                    <option value="{{ $m->nama }}" {{ $mapelFilter === $m->nama ? 'selected' : '' }}>{{ $m->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="fld-pm-tp-fase" style="font-size:12px;font-weight:600">Fase *</label>
                            <select id="fld-pm-tp-fase" name="fase" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                                @foreach($faseLabels as $kode => $label)
                                    <option value="{{ $kode }}" {{ $faseFilter === $kode ? 'selected' : '' }}>Fase {{ $kode }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 110px 80px;gap:8px">
                        <div>
                            <label for="fld-pm-tp-elemen" style="font-size:12px;font-weight:600">Elemen</label>
                            <input id="fld-pm-tp-elemen" type="text" name="elemen" placeholder="Misal: Bilangan"
                                   style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                        <div>
                            <label for="fld-pm-tp-kode-tp" style="font-size:12px;font-weight:600">Kode TP</label>
                            <input id="fld-pm-tp-kode-tp" type="text" name="kode_tp" placeholder="TP.1"
                                   style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                        <div>
                            <label for="fld-pm-tp-urutan" style="font-size:12px;font-weight:600">Urutan</label>
                            <input id="fld-pm-tp-urutan" type="number" name="urutan" value="1" min="1"
                                   style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                    </div>
                    <div>
                        <label for="fld-pm-tp-deskripsi" style="font-size:12px;font-weight:600">Rumusan Tujuan Pembelajaran *</label>
                        <textarea id="fld-pm-tp-deskripsi" name="deskripsi" rows="3" required placeholder="Murid mampu..."
                                  style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px"></textarea>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-sm"><i class="fas fa-save"></i> Simpan TP</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong><i class="fas fa-magic"></i> Susun TP dengan AI (Gemini)</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('guru.pm.tp.ai') }}" style="display:grid;gap:10px">
                    @csrf
                    <div style="display:grid;grid-template-columns:1fr 110px 90px;gap:8px">
                        <div>
                            <label for="fld-pm-tp-nama-mapel-2" style="font-size:12px;font-weight:600">Mata Pelajaran *</label>
                            <select id="fld-pm-tp-nama-mapel-2" name="nama_mapel" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                                @foreach($mapelList as $m)
                                    <option value="{{ $m->nama }}" {{ $mapelFilter === $m->nama ? 'selected' : '' }}>{{ $m->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="fld-pm-tp-fase-2" style="font-size:12px;font-weight:600">Fase *</label>
                            <select id="fld-pm-tp-fase-2" name="fase" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                                @foreach($faseLabels as $kode => $label)
                                    <option value="{{ $kode }}" {{ $faseFilter === $kode ? 'selected' : '' }}>Fase {{ $kode }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="fld-pm-tp-jumlah" style="font-size:12px;font-weight:600">Jumlah TP</label>
                            <input id="fld-pm-tp-jumlah" type="number" name="jumlah" value="6" min="1" max="15"
                                   style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                    </div>
                    <div>
                        <label for="fld-pm-tp-elemen-2" style="font-size:12px;font-weight:600">Elemen (opsional)</label>
                        <input id="fld-pm-tp-elemen-2" type="text" name="elemen" placeholder="Kosongkan untuk semua elemen"
                               style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    </div>
                    <div>
                        <label for="fld-pm-tp-catatan" style="font-size:12px;font-weight:600">Catatan untuk AI (opsional)</label>
                        <textarea id="fld-pm-tp-catatan" name="catatan" rows="2" placeholder="Otomatis mengacu pada CP yang sudah tersimpan..."
                                  style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px"></textarea>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                        <small style="color:#667085">CP tersimpan otomatis dijadikan acuan AI.</small>
                        <button type="submit" class="btn btn-sm" {{ !$aiQuota['configured'] ? 'disabled' : '' }}>
                            <i class="fas fa-sparkles"></i> Susun TP dengan AI
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Daftar TP --}}
    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <strong>Daftar Tujuan Pembelajaran</strong>
            <span style="font-size:12px;color:#667085">Total {{ $tpList->total() }} TP</span>
        </div>
        <div class="card-body" style="padding:0">
            @if($tpList->isEmpty())
                <div style="padding:32px;text-align:center;color:#667085">
                    Belum ada Tujuan Pembelajaran. Tambahkan secara manual atau gunakan <strong>Susun TP dengan AI</strong>.
                </div>
            @else
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width:46px">No</th>
                                <th style="width:160px">Mata Pelajaran</th>
                                <th style="width:76px">Fase</th>
                                <th style="width:130px">Elemen</th>
                                <th style="width:90px">Kode TP</th>
                                <th>Rumusan Tujuan Pembelajaran</th>
                                <th style="width:100px">ATP</th>
                                <th style="width:70px;text-align:right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tpList as $idx => $tp)
                                <tr>
                                    <td>{{ $tpList->firstItem() + $idx }}</td>
                                    <td style="font-weight:600">{{ $tp->nama_mapel }}</td>
                                    <td><span class="badge badge-info" style="white-space:nowrap">Fase {{ $tp->fase }}</span></td>
                                    <td>{{ $tp->elemen }}</td>
                                    <td><code>{{ $tp->kode_tp ?: '-' }}</code></td>
                                    <td style="font-size:13px;line-height:1.45">
                                        {{ $tp->deskripsi }}
                                        @if($tp->sumber === 'ai')
                                            <span class="badge badge-warning" style="margin-left:6px;font-size:10px">AI</span>
                                        @endif
                                    </td>
                                    <td style="font-size:12px;color:#475467">
                                        @if($tp->kelas_target)
                                            Kls {{ $tp->kelas_target }} · S{{ $tp->semester_target ?: '-' }} ({{ $tp->alokasi_jp }} JP)
                                        @else
                                            <span style="color:#98A2B3">Belum di-ATP</span>
                                        @endif
                                    </td>
                                    <td style="text-align:right;white-space:nowrap">
                                        <button type="button" class="btn btn-sm btn-ghost"
                                                onclick="openEditTp({{ json_encode(['url' => route('guru.pm.tp.update', $tp), 'nama_mapel' => $tp->nama_mapel, 'fase' => $tp->fase, 'elemen' => $tp->elemen, 'kode_tp' => $tp->kode_tp, 'urutan' => $tp->urutan, 'deskripsi' => $tp->deskripsi]) }})"
                                                title="Edit TP">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" action="{{ route('guru.pm.tp.destroy', $tp) }}"
                                              onsubmit="return confirm('Hapus TP ini?');" style="display:inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-ghost" style="color:var(--bad)" aria-label="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="padding:12px 16px">{{ $tpList->links() }}</div>
            @endif
        </div>
    </div>

    {{-- Modal Edit Inline TP --}}
    <div id="modalEditTp" style="display:none;position:fixed;inset:0;background:rgba(16,24,40,.45);z-index:1000;align-items:center;justify-content:center;padding:16px">
        <div class="card" style="width:100%;max-width:560px">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
                <strong>Edit Tujuan Pembelajaran</strong>
                <button type="button" class="btn btn-sm btn-ghost" aria-label="Tutup" onclick="document.getElementById('modalEditTp').style.display='none'">×</button>
            </div>
            <div class="card-body">
                <form id="formEditTp" method="POST" style="display:grid;gap:10px">
                    @csrf
                    @method('PUT')
                    <div style="display:grid;grid-template-columns:1fr 120px;gap:8px">
                        <div>
                            <label style="font-size:12px;font-weight:600" for="editTpMapel">Mata Pelajaran *</label>
                            <input type="text" id="editTpMapel" name="nama_mapel" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:600" for="editTpFase">Fase *</label>
                            <select id="editTpFase" name="fase" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                                @foreach($faseLabels as $kode => $label)
                                    <option value="{{ $kode }}">Fase {{ $kode }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 110px 80px;gap:8px">
                        <div>
                            <label style="font-size:12px;font-weight:600" for="editTpElemen">Elemen</label>
                            <input type="text" id="editTpElemen" name="elemen" style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:600" for="editTpKode">Kode TP</label>
                            <input type="text" id="editTpKode" name="kode_tp" style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:600" for="editTpUrutan">Urutan</label>
                            <input type="number" id="editTpUrutan" name="urutan" min="1" style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600" for="editTpDesk">Rumusan Tujuan Pembelajaran *</label>
                        <textarea id="editTpDesk" name="deskripsi" rows="4" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px"></textarea>
                    </div>
                    <div style="display:flex;justify-content:flex-end;gap:8px">
                        <button type="button" class="btn btn-sm btn-ghost" onclick="document.getElementById('modalEditTp').style.display='none'">Batal</button>
                        <button type="submit" class="btn btn-sm"><i class="fas fa-save"></i> Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        function openEditTp(d) {
            document.getElementById('formEditTp').action = d.url;
            document.getElementById('editTpMapel').value = d.nama_mapel || '';
            document.getElementById('editTpFase').value = d.fase || 'A';
            document.getElementById('editTpElemen').value = d.elemen || '';
            document.getElementById('editTpKode').value = d.kode_tp || '';
            document.getElementById('editTpUrutan').value = d.urutan || 1;
            document.getElementById('editTpDesk').value = d.deskripsi || '';
            document.getElementById('modalEditTp').style.display = 'flex';
        }
    </script>
@endsection
