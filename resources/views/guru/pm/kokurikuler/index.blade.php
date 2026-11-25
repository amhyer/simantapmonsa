@extends('layouts.app')

@section('title', 'Kokurikuler - Pembelajaran Mendalam')
@section('page_title', 'Perencanaan & Dokumentasi Kokurikuler')
@section('page_subtitle', 'Rancang kegiatan kolaboratif lintas disiplin, Gerakan 7 Kebiasaan Anak Indonesia Hebat & 8 Dimensi Profil Lulusan')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('header_actions')
    <a href="{{ route('guru.pm.kokurikuler.excel', ['tahun_ajaran' => $tahunAjaran]) }}" class="btn btn-ghost btn-sm">
        <i class="fas fa-file-excel"></i> Export Excel
    </a>
    <a href="{{ route('guru.pm.kokurikuler.pdf', ['tahun_ajaran' => $tahunAjaran]) }}" target="_blank" class="btn btn-ghost btn-sm">
        <i class="fas fa-print"></i> Cetak PDF
    </a>
@endsection

@section('content')
    @include('guru.pm._nav')

    {{-- Form Tambah Manual & Rancang dengan AI --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px;margin-bottom:16px">
        <div class="card">
            <div class="card-header"><strong><i class="fas fa-plus-circle"></i> Tambah Kegiatan Kokurikuler</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('guru.pm.kokurikuler.store') }}" style="display:grid;gap:10px">
                    @csrf
                    <input type="hidden" name="tahun_ajaran" value="{{ $tahunAjaran }}">
                    <div style="display:grid;grid-template-columns:1fr 110px;gap:8px">
                        <div>
                            <label for="fld-pm-kokurikuler-tema-judul" style="font-size:12px;font-weight:600">Tema / Judul Kegiatan *</label>
                            <input id="fld-pm-kokurikuler-tema-judul" type="text" name="tema_judul" required placeholder="Misal: Kebun Sayur Sekolahku"
                                   style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                        @php($daftarTingkatKoku = \App\Support\SchoolLevel::allowedTingkatForUser(auth()->user()))
                        <div>
                            <label for="fld-pm-kokurikuler-tingkat" style="font-size:12px;font-weight:600">Kelas *</label>
                            <select id="fld-pm-kokurikuler-tingkat" name="tingkat" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                                @foreach($daftarTingkatKoku as $t)
                                    <option value="{{ $t }}">Kelas {{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="fld-pm-kokurikuler-bentuk-kegiatan" style="font-size:12px;font-weight:600">Bentuk Kegiatan *</label>
                        <select id="fld-pm-kokurikuler-bentuk-kegiatan" name="bentuk_kegiatan" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                            @foreach($bentukLabels as $kode => $label)
                                <option value="{{ $kode }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <div id="lbl-koku-dimensi" style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Dimensi Profil Lulusan (8 Dimensi)</div>
                        <div role="group" aria-labelledby="lbl-koku-dimensi" style="display:flex;flex-wrap:wrap;gap:6px">
                            @foreach($dimensiList as $dim)
                                <label style="font-size:12px;display:flex;align-items:center;gap:4px;padding:3px 7px;border:1px solid #E4E7EC;border-radius:6px">
                                    <input type="checkbox" name="dimensi_profil[]" value="{{ $dim }}">
                                    {{ $dim }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <div>
                            <label for="fld-pm-kokurikuler-tanggal-mulai" style="font-size:12px;font-weight:600">Tanggal Mulai</label>
                            <input id="fld-pm-kokurikuler-tanggal-mulai" type="date" name="tanggal_mulai" style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                        <div>
                            <label for="fld-pm-kokurikuler-tanggal-selesai" style="font-size:12px;font-weight:600">Tanggal Selesai</label>
                            <input id="fld-pm-kokurikuler-tanggal-selesai" type="date" name="tanggal_selesai" style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                    </div>
                    <div>
                        <label for="fld-koku-tujuan" style="font-size:12px;font-weight:600">Tujuan & Rangkaian Kegiatan</label>
                        <textarea id="fld-koku-tujuan" name="tujuan" rows="2" placeholder="Tujuan kegiatan..."
                                  style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px;margin-bottom:6px"></textarea>
                        <textarea id="fld-koku-rangkaian" name="rangkaian_kegiatan" aria-label="Rangkaian tahapan kegiatan" rows="2" placeholder="Rangkaian tahapan kegiatan..."
                                  style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px"></textarea>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <div>
                            <label for="fld-pm-kokurikuler-mitra" style="font-size:12px;font-weight:600">Mitra / Kolaborator</label>
                            <input id="fld-pm-kokurikuler-mitra" type="text" name="mitra" placeholder="Orang tua, puskesmas, dll."
                                   style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                        <div>
                            <label for="fld-pm-kokurikuler-refleksi-hasil" style="font-size:12px;font-weight:600">Refleksi & Hasil</label>
                            <input id="fld-pm-kokurikuler-refleksi-hasil" type="text" name="refleksi_hasil" placeholder="Ringkasan capaian..."
                                   style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-sm"><i class="fas fa-save"></i> Simpan Kegiatan</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong><i class="fas fa-magic"></i> Rancang Kokurikuler dengan AI (Gemini)</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('guru.pm.kokurikuler.ai') }}" style="display:grid;gap:10px">
                    @csrf
                    <input type="hidden" name="tahun_ajaran" value="{{ $tahunAjaran }}">
                    <div>
                        <label for="fld-pm-kokurikuler-tingkat-2" style="font-size:12px;font-weight:600">Kelas *</label>
                        <select id="fld-pm-kokurikuler-tingkat-2" name="tingkat" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                            @foreach($daftarTingkatKoku as $t)
                                <option value="{{ $t }}">Kelas {{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="fld-pm-kokurikuler-bentuk-kegiatan-2" style="font-size:12px;font-weight:600">Bentuk Kegiatan *</label>
                        <select id="fld-pm-kokurikuler-bentuk-kegiatan-2" name="bentuk_kegiatan" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                            @foreach($bentukLabels as $kode => $label)
                                <option value="{{ $kode }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="fld-pm-kokurikuler-tema-ide" style="font-size:12px;font-weight:600">Tema / Ide Kegiatan *</label>
                        <textarea id="fld-pm-kokurikuler-tema-ide" name="tema_ide" rows="4" required placeholder="Misal: Pengelolaan sampah plastik menjadi ekobrik di lingkungan sekolah bersama orang tua..."
                                  style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px"></textarea>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                        <small style="color:#667085">Otomatis menyusun dimensi, tujuan, tahapan, mitra & refleksi.</small>
                        <button type="submit" class="btn btn-sm" {{ !$aiQuota['configured'] ? 'disabled' : '' }}>
                            <i class="fas fa-sparkles"></i> Rancang dengan AI
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Daftar Kokurikuler --}}
    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <strong>Daftar Kegiatan Kokurikuler ({{ $tahunAjaran }})</strong>
            <span style="font-size:12px;color:#667085">Total {{ $list->total() }} kegiatan</span>
        </div>
        <div class="card-body" style="padding:0">
            @if($list->isEmpty())
                <div style="padding:32px;text-align:center;color:#667085">
                    Belum ada kegiatan Kokurikuler. Tambahkan manual atau klik <strong>Rancang dengan AI</strong>.
                </div>
            @else
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width:44px">No</th>
                                <th style="width:74px">Kelas</th>
                                <th>Tema / Judul Kegiatan</th>
                                <th style="width:200px">Bentuk Kegiatan</th>
                                <th>Dimensi Profil Lulusan</th>
                                <th style="width:100px">Mulai</th>
                                <th style="width:60px;text-align:right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($list as $i => $item)
                                <tr>
                                    <td>{{ $list->firstItem() + $i }}</td>
                                    <td><span class="badge badge-info">Kls {{ $item->tingkat }}</span></td>
                                    <td>
                                        <div style="font-weight:600">{{ $item->tema_judul }}</div>
                                        @if($item->tujuan)
                                            <div style="font-size:12px;color:#667085;margin-top:2px">{{ \Illuminate\Support\Str::limit($item->tujuan, 100) }}</div>
                                        @endif
                                    </td>
                                    <td style="font-size:12px">{{ $item->bentukLabel() }}</td>
                                    <td>
                                        @foreach(is_array($item->dimensi_profil) ? $item->dimensi_profil : [] as $dim)
                                            <span class="badge badge-success" style="font-size:10px;margin:1px">{{ $dim }}</span>
                                        @endforeach
                                    </td>
                                    <td style="font-size:12px">{{ $item->tanggal_mulai?->format('d/m/Y') ?? '—' }}</td>
                                    <td style="text-align:right;white-space:nowrap">
                                        <button type="button" class="btn btn-sm btn-ghost"
                                                onclick="openEditKoku({{ json_encode(['url' => route('guru.pm.kokurikuler.update', $item), 'tingkat' => $item->tingkat, 'tema_judul' => $item->tema_judul, 'bentuk_kegiatan' => $item->bentuk_kegiatan, 'tanggal_mulai' => $item->tanggal_mulai?->format('Y-m-d'), 'tanggal_selesai' => $item->tanggal_selesai?->format('Y-m-d'), 'tujuan' => $item->tujuan, 'rangkaian_kegiatan' => $item->rangkaian_kegiatan, 'mitra' => $item->mitra, 'refleksi_hasil' => $item->refleksi_hasil, 'dimensi_profil' => $item->dimensi_profil ?? []]) }})"
                                                title="Edit Kokurikuler">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" action="{{ route('guru.pm.kokurikuler.destroy', $item) }}"
                                              onsubmit="return confirm('Hapus kegiatan ini?');" style="display:inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-ghost" style="color:var(--bad)" aria-label="Hapus"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="padding:12px 16px">{{ $list->links() }}</div>
            @endif
        </div>
    </div>

    {{-- Modal Edit Inline Kokurikuler --}}
    <div id="modalEditKoku" style="display:none;position:fixed;inset:0;background:rgba(16,24,40,.45);z-index:1000;align-items:center;justify-content:center;padding:16px">
        <div class="card" style="width:100%;max-width:600px;max-height:90vh;overflow:auto">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
                <strong>Edit Kegiatan Kokurikuler</strong>
                <button type="button" class="btn btn-sm btn-ghost" aria-label="Tutup" onclick="document.getElementById('modalEditKoku').style.display='none'">×</button>
            </div>
            <div class="card-body">
                <form id="formEditKoku" method="POST" style="display:grid;gap:10px">
                    @csrf
                    @method('PUT')
                    <div style="display:grid;grid-template-columns:1fr 110px;gap:8px">
                        <div>
                            <label style="font-size:12px;font-weight:600" for="editKokuTema">Tema / Judul *</label>
                            <input type="text" id="editKokuTema" name="tema_judul" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:600" for="editKokuTingkat">Kelas *</label>
                            <select id="editKokuTingkat" name="tingkat" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                                @foreach($daftarTingkatKoku as $t)<option value="{{ $t }}">Kelas {{ $t }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600" for="editKokuBentuk">Bentuk Kegiatan *</label>
                        <select id="editKokuBentuk" name="bentuk_kegiatan" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                            @foreach($bentukLabels as $kode => $label)<option value="{{ $kode }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <div>
                            <label style="font-size:12px;font-weight:600" for="editKokuMulai">Tanggal Mulai</label>
                            <input type="date" id="editKokuMulai" name="tanggal_mulai" style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:600" for="editKokuSelesai">Tanggal Selesai</label>
                            <input type="date" id="editKokuSelesai" name="tanggal_selesai" style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600" for="editKokuTujuan">Tujuan</label>
                        <textarea id="editKokuTujuan" name="tujuan" rows="2" style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px"></textarea>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600" for="editKokuRangkaian">Rangkaian Kegiatan</label>
                        <textarea id="editKokuRangkaian" name="rangkaian_kegiatan" rows="3" style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px"></textarea>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <div>
                            <label style="font-size:12px;font-weight:600" for="editKokuMitra">Mitra</label>
                            <input type="text" id="editKokuMitra" name="mitra" style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:600" for="editKokuRefleksi">Refleksi &amp; Hasil</label>
                            <input type="text" id="editKokuRefleksi" name="refleksi_hasil" style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                    </div>
                    <div style="display:flex;justify-content:flex-end;gap:8px">
                        <button type="button" class="btn btn-sm btn-ghost" onclick="document.getElementById('modalEditKoku').style.display='none'">Batal</button>
                        <button type="submit" class="btn btn-sm"><i class="fas fa-save"></i> Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        function openEditKoku(d) {
            document.getElementById('formEditKoku').action = d.url;
            document.getElementById('editKokuTema').value = d.tema_judul || '';
            document.getElementById('editKokuTingkat').value = d.tingkat || 1;
            document.getElementById('editKokuBentuk').value = d.bentuk_kegiatan || 'kolaboratif_lintas_disiplin';
            document.getElementById('editKokuMulai').value = d.tanggal_mulai || '';
            document.getElementById('editKokuSelesai').value = d.tanggal_selesai || '';
            document.getElementById('editKokuTujuan').value = d.tujuan || '';
            document.getElementById('editKokuRangkaian').value = d.rangkaian_kegiatan || '';
            document.getElementById('editKokuMitra').value = d.mitra || '';
            document.getElementById('editKokuRefleksi').value = d.refleksi_hasil || '';
            document.getElementById('modalEditKoku').style.display = 'flex';
        }
    </script>
@endsection
