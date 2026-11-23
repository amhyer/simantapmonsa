@extends('layouts.admin-with-tabs')

@section('title', 'Data Guru - SIMANTAP')

@section('page_title', 'Data Guru')
@section('page_subtitle', 'Data pendidik dan tenaga kependidikan dari Dapodik dan penambahan manual')

@section('header_actions')
    <button type="button" class="btn btn-sm btn-secondary" onclick="fokusEditGelar()">
        <i class="fas fa-edit"></i> Edit Gelar Guru
    </button>
    <button type="button" class="btn btn-sm btn-primary" onclick="openTambahGuruModal()">
        <i class="fas fa-user-plus"></i> + Tambah PTK / Guru (Non-Dapodik)
    </button>
    <a href="{{ route('admin.dapodik.index') }}" class="btn btn-sm btn-ghost">Impor Dapodik</a>
    <a href="{{ route('admin.peta-kelas.index') }}" class="btn btn-sm">Peta Kelas</a>
@endsection

@section('content')
    @php
        $gpkList = $ptk->filter(fn($p) => $p->isGpk());
        $guruList = $ptk->filter(fn($p) => $p->isGuru() && !$p->isGpk());
        $kepsekList = $ptk->filter(fn($p) => $p->isKepsek());
        $kebersihanList = $ptk->filter(fn($p) => $p->isKebersihan());
        $securityList = $ptk->filter(fn($p) => $p->isSecurity());
        $tendikList = $ptk->filter(fn($p) => !$p->isGuru() && !$p->isKepsek() && !$p->isKebersihan() && !$p->isSecurity());
    @endphp

    <div class="grid grid-4" style="margin-bottom:16px">
        <div class="stat">
            <div class="label">Total PTK & Staf</div>
            <div class="value">{{ $ptk->count() }}</div>
            <div style="font-size:11px;color:#667085;margin-top:2px">Dapodik: {{ $ptk->whereNotNull('dapodik_id')->count() }} &bull; Non-Dapodik: {{ $ptk->whereNull('dapodik_id')->count() }}</div>
        </div>
        <div class="stat ok">
            <div class="label">Guru (Kelas & Mapel)</div>
            <div class="value">{{ $guruList->count() }}</div>
            <div style="font-size:11px;color:#027A48;margin-top:2px">Kepala Sekolah / PLT: {{ $kepsekList->count() }}</div>
        </div>
        <div class="stat" style="border-left:4px solid #7F56D9">
            <div class="label" style="color:#6941C6">Guru GPK (Inklusi)</div>
            <div class="value" style="color:#6941C6">{{ $gpkList->count() }}</div>
            <div style="font-size:11px;color:#6941C6;margin-top:2px">Pembimbing Khusus Non-Dapodik</div>
        </div>
        <div class="stat" style="border-left:4px solid #0BA5EC">
            <div class="label" style="color:#026AA2">Tendik, Kebersihan & Security</div>
            <div class="value" style="color:#026AA2">{{ $tendikList->count() + $kebersihanList->count() + $securityList->count() }}</div>
            <div style="font-size:11px;color:#026AA2;margin-top:2px">Kebersihan: {{ $kebersihanList->count() }} &bull; Security: {{ $securityList->count() }} &bull; Tendik: {{ $tendikList->count() }}</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="flex-wrap:wrap;gap:10px">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <h3 style="margin:0;margin-right:8px">Daftar PTK & Tenaga Kependidikan</h3>
                <button type="button" onclick="copyPtkData()" class="btn btn-sm btn-ghost">
                    <i class="fas fa-copy"></i> Copy
                </button>
                <button type="button" onclick="exportPtkCsv()" class="btn btn-sm btn-ghost">
                    <i class="fas fa-file-excel"></i> Excel
                </button>
                <button type="button" onclick="exportPtkPdf()" class="btn btn-sm btn-ghost">
                    <i class="fas fa-file-pdf"></i> Pdf
                </button>
                <button type="button" onclick="window.print()" class="btn btn-sm btn-ghost">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <label style="font-size:12px;color:var(--muted);display:flex;align-items:center;gap:4px" for="ptkPageSize">
                    Show
                    <select id="ptkPageSize" onchange="applyPtkFilter()" style="padding:4px 8px;border:1px solid #E4E7EC;border-radius:6px;font-size:12px">
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="999999" selected>Semua</option>
                    </select>
                    rows
                </label>
                <input type="text" id="searchPtk" placeholder="Cari nama, NIP, atau jenis..." style="padding:6px 12px;border:1px solid #E4E7EC;border-radius:8px;width:220px" oninput="filterPtk(this.value)">
            </div>
        </div>
        <div class="card-body tight">
            @if($ptk->count())
                <form action="{{ route('admin.referensi.gelar-ptk.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="table-wrapper">
                        <table style="width:100%;border-collapse:collapse" id="tabelPtk">
                            <thead>
                                <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                    <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085;width:40px">No</th>
                                    <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Nama PTK & Kategori</th>
                                    <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085;width:170px">NIP/NIY Rapor & NUPTK</th>
                                    <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085;width:50px">JK</th>
                                    <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085;width:150px">Jenis PTK</th>
                                    <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085;width:150px">Penugasan / Jabatan</th>
                                    <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085;width:85px">Gelar Depan</th>
                                    <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085;width:105px">Gelar Belakang</th>
                                    <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085;width:80px">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($ptk as $i => $p)
                                    <tr>
                                        <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center" class="col-no-ptk">{{ $i + 1 }}</td>
                                        <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                            <div style="display:flex;align-items:center;gap:11px">
                                                <span class="avatar">{{ substr($p->nama, 0, 2) }}</span>
                                                <div>
                                                    <b>{{ $p->nama_gelar }}</b>
                                                    @if($p->dapodik_id)
                                                        <span style="display:inline-block;background:#EEF2F9;color:var(--navy);font-size:9px;padding:1px 5px;border-radius:4px;margin-left:4px;font-weight:600;vertical-align:middle" title="Data dari Dapodik">DAPODIK</span>
                                                    @else
                                                        <span style="display:inline-block;background:#FEF3F2;color:var(--bad);border:1px solid #FECDCA;font-size:9px;padding:1px 5px;border-radius:4px;margin-left:4px;font-weight:600;vertical-align:middle" title="Data non-Dapodik">NON-DAPODIK</span>
                                                    @endif
                                                    <div style="margin-top:3px;display:flex;gap:4px;flex-wrap:wrap">
                                                        @if($p->isGpk())
                                                            <span class="tag" style="background:#F4EBFF;color:#6941C6;border:1px solid #D6BBFB;font-size:10px;font-weight:600">Guru GPK</span>
                                                        @elseif($p->isKebersihan())
                                                            <span class="tag" style="background:#ECFDF3;color:#027A48;border:1px solid #A6F4C5;font-size:10px;font-weight:600">Kebersihan</span>
                                                        @elseif($p->isSecurity())
                                                            <span class="tag" style="background:#EFF8FF;color:#175CD3;border:1px solid #B2DDFF;font-size:10px;font-weight:600">Security</span>
                                                        @elseif($p->isKepsek())
                                                            <span class="tag tag-gold" style="font-size:10px">Kepsek</span>
                                                        @elseif($p->isGuru())
                                                            <span class="tag tag-ok" style="font-size:10px">Guru</span>
                                                        @else
                                                            <span class="tag tag-mut" style="font-size:10px">Tendik</span>
                                                        @endif

                                                        @if(!empty($p->jabatan))
                                                            <span style="font-size:10px;color:#475467;background:#F2F4F7;padding:1px 6px;border-radius:4px" title="{{ $p->jabatan }}">{{ Str::limit($p->jabatan, 24) }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                            <input type="text" name="ptk[{{ $p->id }}][nip]" value="{{ $p->nip }}" placeholder="NIP/NIY Rapor" maxlength="50" class="input-edit-gelar" style="width:100%;padding:5px 8px;border:1px solid #D0D5DD;border-radius:6px;font-size:12px">
                                            @if($p->nuptk)
                                                <div style="font-size:10.5px;color:#667085;margin-top:3px">NUPTK: {{ $p->nuptk }}</div>
                                            @endif
                                        </td>
                                        <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">
                                            <span style="font-weight:600;font-size:12px;color:{{ $p->jk === 'L' ? '#175CD3' : ($p->jk === 'P' ? '#C11574' : '#667085') }}">{{ $p->jk ?? '-' }}</span>
                                        </td>
                                        <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                            <select name="ptk[{{ $p->id }}][jenis_ptk]" style="padding:5px 8px;border:1px solid #D0D5DD;border-radius:6px;font-size:12px;width:100%;background:#fff">
                                                <option value="Guru Kelas" {{ in_array($p->jenis_ptk, ['Guru Kelas', 'Guru'], true) ? 'selected' : '' }}>Guru Kelas</option>
                                                <option value="Guru Mapel" {{ ($p->jenis_ptk === 'Guru Mapel') ? 'selected' : '' }}>Guru Mapel</option>
                                                <option value="Guru GPK" {{ ($p->jenis_ptk === 'Guru GPK') ? 'selected' : '' }}>Guru GPK</option>
                                                <option value="Guru BK" {{ ($p->jenis_ptk === 'Guru BK') ? 'selected' : '' }}>Guru BK</option>
                                                <option value="Guru Pendamping" {{ ($p->jenis_ptk === 'Guru Pendamping') ? 'selected' : '' }}>Guru Pendamping</option>
                                                <option value="Kepala Sekolah" {{ ($p->jenis_ptk === 'Kepala Sekolah') ? 'selected' : '' }}>Kepala Sekolah</option>
                                                <option value="Tenaga Kependidikan" {{ ($p->jenis_ptk === 'Tenaga Kependidikan') ? 'selected' : '' }}>Tendik</option>
                                                <option value="Tenaga Kebersihan" {{ ($p->jenis_ptk === 'Tenaga Kebersihan') ? 'selected' : '' }}>Kebersihan</option>
                                                <option value="Security" {{ ($p->jenis_ptk === 'Security') ? 'selected' : '' }}>Security</option>
                                                @if(!empty($p->jenis_ptk) && !in_array($p->jenis_ptk, ['Guru','Guru Kelas','Guru Mapel','Guru GPK','Guru BK','Guru Pendamping','Kepala Sekolah','Tenaga Kependidikan','Tenaga Kebersihan','Security']))
                                                    <option value="{{ $p->jenis_ptk }}" selected>{{ $p->jenis_ptk }}</option>
                                                @endif
                                            </select>
                                        </td>
                                        <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                            <input type="text" name="ptk[{{ $p->id }}][jabatan]" value="{{ $p->jabatan }}" placeholder="Penugasan" maxlength="255" style="width:100%;padding:5px 8px;border:1px solid #D0D5DD;border-radius:6px;font-size:12px">
                                        </td>
                                        <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                            <input type="text" name="ptk[{{ $p->id }}][gelar_depan]" value="{{ $p->gelar_depan }}" maxlength="50" placeholder="H." style="width:100%;padding:5px 8px;border:1px solid #D0D5DD;border-radius:6px;font-size:12px">
                                        </td>
                                        <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                            <input type="text" name="ptk[{{ $p->id }}][gelar_belakang]" value="{{ $p->gelar_belakang }}" maxlength="100" placeholder="S.Pd" style="width:100%;padding:5px 8px;border:1px solid #D0D5DD;border-radius:6px;font-size:12px">
                                        </td>
                                        <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">
                                            @if(!$p->dapodik_id)
                                                <button type="submit" form="form-delete-ptk-{{ $p->id }}" class="btn btn-xs btn-ghost" style="color:var(--bad);padding:4px 8px;font-size:11.5px;border:1px solid #FECDCA;border-radius:6px" title="Hapus data PTK non-Dapodik ini">
                                                    <i class="fas fa-trash-alt"></i> Hapus
                                                </button>
                                            @else
                                                <span style="color:#98A2B3;font-size:11px" title="Data disinkronkan dari Dapodik">&mdash;</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top:14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="fas fa-save"></i> Simpan Perubahan PTK (Gelar, Jenis, & Jabatan)
                        </button>
                        <small style="color:#667085;font-size:11.5px">
                            * Anda dapat mengubah Jenis PTK (Guru GPK, Kebersihan, Security, dll) dan Jabatan langsung di tabel.
                        </small>
                    </div>
                </form>

                @foreach($ptk as $p)
                    @if(!$p->dapodik_id)
                        <form id="form-delete-ptk-{{ $p->id }}" action="{{ route('admin.referensi.guru.destroy', $p) }}" method="POST" style="display:none" onsubmit="return confirm('Hapus data non-Dapodik {{ $p->nama }}?')">
                            @csrf
                            @method('DELETE')
                        </form>
                    @endif
                @endforeach
            @else
                <div class="empty-state">
                    <div class="icon">👩‍🏫</div>
                    <h4>Belum ada data PTK</h4>
                    <p>Import dari Dapodik atau tambahkan guru manual melalui tombol di atas.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Modal Tambah Guru Non-Dapodik --}}
    <div class="modal-overlay" id="modalTambahGuru" style="display:none" onclick="if(event.target===this)closeTambahGuruModal()">
        <div class="modal" style="max-width:560px;width:95%">
            <div class="modal-header">
                <div>
                    <h3 style="margin:0;font-size:16px;font-weight:700;color:#1F3864">Tambah Guru (Non-Dapodik)</h3>
                    <small style="color:#667085;font-size:12px">Tambahkan data guru/pegawai yang belum tercatat di Dapodik</small>
                </div>
                <button type="button" class="modal-close" onclick="closeTambahGuruModal()">&times;</button>
            </div>
            <form action="{{ route('admin.referensi.guru.store') }}" method="POST">
                @csrf
                <div class="modal-body" style="max-height:calc(85vh - 120px);overflow-y:auto">
                    <div class="form-group" style="margin-bottom:12px">
                        <label for="fld-referensi-guru-nama">Nama Lengkap (tanpa gelar) <span style="color:var(--bad)">*</span></label>
                        <input id="fld-referensi-guru-nama" type="text" name="nama" value="{{ old('nama') }}" required placeholder="Contoh: Ahmad Fauzi" style="width:100%;padding:8px 12px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
                        <div class="form-group" style="margin-bottom:0">
                            <label for="fld-referensi-guru-gelar-depan">Gelar Depan</label>
                            <input id="fld-referensi-guru-gelar-depan" type="text" name="gelar_depan" value="{{ old('gelar_depan') }}" maxlength="50" placeholder="Contoh: Drs., H." style="width:100%;padding:8px 12px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                        <div class="form-group" style="margin-bottom:0">
                            <label for="fld-referensi-guru-gelar-belakang">Gelar Belakang</label>
                            <input id="fld-referensi-guru-gelar-belakang" type="text" name="gelar_belakang" value="{{ old('gelar_belakang') }}" maxlength="100" placeholder="Contoh: S.Pd, M.Pd" style="width:100%;padding:8px 12px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
                        <div class="form-group" style="margin-bottom:0">
                            <label for="fld-referensi-guru-nip">NIP (opsional)</label>
                            <input id="fld-referensi-guru-nip" type="text" name="nip" value="{{ old('nip') }}" maxlength="50" placeholder="18 digit NIP jika ada" style="width:100%;padding:8px 12px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                        <div class="form-group" style="margin-bottom:0">
                            <label for="fld-referensi-guru-nuptk">NUPTK (opsional)</label>
                            <input id="fld-referensi-guru-nuptk" type="text" name="nuptk" value="{{ old('nuptk') }}" maxlength="50" placeholder="16 digit NUPTK jika ada" style="width:100%;padding:8px 12px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
                        <div class="form-group" style="margin-bottom:0">
                            <label for="fld-referensi-guru-jk">Jenis Kelamin</label>
                            <select id="fld-referensi-guru-jk" name="jk" style="width:100%;padding:8px 12px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                                <option value="">— Pilih Jenis Kelamin —</option>
                                <option value="L" {{ old('jk') === 'L' ? 'selected' : '' }}>Laki-laki (L)</option>
                                <option value="P" {{ old('jk') === 'P' ? 'selected' : '' }}>Perempuan (P)</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0">
                            <label for="fld-referensi-guru-jenis-ptk">Jenis PTK <span style="color:var(--bad)">*</span></label>
                            <select id="fld-referensi-guru-jenis-ptk" name="jenis_ptk" required style="width:100%;padding:8px 12px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                                <option value="Guru GPK" {{ old('jenis_ptk') === 'Guru GPK' ? 'selected' : '' }}>Guru GPK (Pembimbing Khusus)</option>
                                <option value="Guru Kelas" {{ old('jenis_ptk', 'Guru Kelas') === 'Guru Kelas' ? 'selected' : '' }}>Guru Kelas</option>
                                <option value="Guru Mapel" {{ old('jenis_ptk') === 'Guru Mapel' ? 'selected' : '' }}>Guru Mapel</option>
                                <option value="Guru BK" {{ old('jenis_ptk') === 'Guru BK' ? 'selected' : '' }}>Guru BK</option>
                                <option value="Guru Pendamping" {{ old('jenis_ptk') === 'Guru Pendamping' ? 'selected' : '' }}>Guru Pendamping</option>
                                <option value="Kepala Sekolah" {{ old('jenis_ptk') === 'Kepala Sekolah' ? 'selected' : '' }}>Kepala Sekolah / PLT</option>
                                <option value="Tenaga Kependidikan" {{ old('jenis_ptk') === 'Tenaga Kependidikan' ? 'selected' : '' }}>Tenaga Kependidikan (Tendik)</option>
                                <option value="Tenaga Kebersihan" {{ old('jenis_ptk') === 'Tenaga Kebersihan' ? 'selected' : '' }}>Tenaga Kebersihan</option>
                                <option value="Security" {{ old('jenis_ptk') === 'Security' ? 'selected' : '' }}>Security / Satpam</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:14px">
                        <label for="fld-referensi-guru-jabatan">Jabatan / Penugasan (opsional)</label>
                        <input id="fld-referensi-guru-jabatan" type="text" name="jabatan" value="{{ old('jabatan') }}" placeholder="Contoh: Guru Kelas 1A, Guru PJOK, Operator" style="width:100%;padding:8px 12px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    </div>

                    <div style="background:#F9FAFB;border:1px solid #EAECF0;border-radius:8px;padding:12px;margin-bottom:8px">
                        <label style="display:flex;align-items:center;gap:8px;font-weight:600;font-size:13px;color:#1F3864;cursor:pointer" for="checkBuatAkun">
                            <input type="checkbox" name="buat_akun" value="1" id="checkBuatAkun" {{ old('buat_akun', '1') ? 'checked' : '' }} onchange="toggleAkunFields(this.checked)">
                            Buat akun login pengguna sekaligus
                        </label>
                        <small style="display:block;color:#667085;font-size:11.5px;margin-top:4px">
                            Guru dapat langsung login ke SIMANTAP untuk mengakses fitur penilaian, materi, CBT, dan presensi.
                        </small>

                        <div id="akunFields" style="margin-top:12px;display:grid;grid-template-columns:1fr 1fr;gap:10px">
                            <div>
                                <label for="fld-referensi-guru-nama-pengguna" style="font-weight:500;font-size:12px;display:block;margin-bottom:4px;color:#475467">Username (opsional)</label>
                                <input id="fld-referensi-guru-nama-pengguna" type="text" name="nama_pengguna" value="{{ old('nama_pengguna') }}" placeholder="Otomatis NIP/nama jika kosong" style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:6px;font-size:12px">
                            </div>
                            <div>
                                <label for="fld-referensi-guru-kata-sandi" style="font-weight:500;font-size:12px;display:block;margin-bottom:4px;color:#475467">Password Awal (opsional)</label>
                                <input id="fld-referensi-guru-kata-sandi" type="text" name="kata_sandi" placeholder="Default: guru123" style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:6px;font-size:12px">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="padding:12px 18px;border-top:1px solid #EAECF0;display:flex;justify-content:flex-end;gap:8px;background:#FAFBFD;border-radius:0 0 12px 12px">
                    <button type="button" class="btn btn-sm btn-ghost" onclick="closeTambahGuruModal()">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary">Simpan Guru</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(2px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1050;
            padding: 16px;
        }
        .modal {
            background: #fff;
            border-radius: 12px;
            width: 100%;
            max-width: 560px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            border: 1px solid #E4E7EC;
            overflow: hidden;
            animation: modalFadeIn .18s ease-out;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-10px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid #EAECF0;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            background: #FAFBFD;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 22px;
            line-height: 1;
            color: #98A2B3;
            cursor: pointer;
            padding: 0 4px;
        }
        .modal-close:hover { color: #344054; }
        .modal-body { padding: 20px; }
        .form-group label {
            font-weight: 600;
            font-size: 12.5px;
            display: block;
            margin-bottom: 5px;
            color: #344054;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #D0D5DD;
            border-radius: 8px;
            font-size: 13px;
            outline: none;
            transition: border-color .15s;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #1F3864;
            box-shadow: 0 0 0 3px rgba(31, 56, 100, 0.1);
        }
    </style>

    <script>
        function filterPtk() {
            applyPtkFilter();
        }

        function applyPtkFilter() {
            const keyword = (document.getElementById('searchPtk')?.value || '').toLowerCase();
            const limit = parseInt(document.getElementById('ptkPageSize')?.value || '999999', 10);
            let visible = 0;
            document.querySelectorAll('#tabelPtk tbody tr').forEach(function (row) {
                const match = row.textContent.toLowerCase().includes(keyword);
                if (match && visible < limit) {
                    row.style.display = '';
                    visible++;
                    const noCell = row.querySelector('.col-no-ptk');
                    if (noCell) noCell.textContent = visible;
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function fokusEditGelar() {
            const firstInput = document.querySelector('#tabelPtk input[name*="[gelar_depan]"]') || document.querySelector('#tabelPtk input');
            if (firstInput) {
                firstInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstInput.focus();
            }
        }

        function getPtkMatrix() {
            const rows = [['No', 'Nama PTK', 'NIP', 'JK', 'Jenis PTK', 'Jabatan', 'Gelar Depan', 'Gelar Belakang']];
            document.querySelectorAll('#tabelPtk tbody tr').forEach(tr => {
                if (tr.style.display === 'none') return;
                const tds = tr.querySelectorAll('td');
                if (tds.length >= 8) {
                    rows.push([
                        tds[0].innerText.trim(),
                        tds[1].querySelector('b')?.innerText.trim() || tds[1].innerText.trim(),
                        tds[2].querySelector('input')?.value || '',
                        tds[3].innerText.trim(),
                        tds[4].querySelector('select')?.value || '',
                        tds[5].querySelector('input')?.value || '',
                        tds[6].querySelector('input')?.value || '',
                        tds[7].querySelector('input')?.value || ''
                    ]);
                }
            });
            return rows;
        }

        function copyPtkData() {
            const text = getPtkMatrix().map(r => r.join('\t')).join('\n');
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text);
                if (typeof showToast === 'function') showToast('Data PTK disalin ke clipboard', 'success');
            }
        }

        function exportPtkCsv() {
            const csv = getPtkMatrix().map(r => r.map(c => '"' + String(c).replace(/"/g, '""') + '"').join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'Data_Guru_PTK_SIMANTAP.csv';
            link.click();
        }

        function exportPtkPdf() {
            const rows = getPtkMatrix();
            const win = window.open('', '_blank');
            if (!win) { window.print(); return; }
            let html = '<!DOCTYPE html><html><head><title>Data Guru & PTK</title><style>body{font-family:Arial,sans-serif;font-size:12px;padding:20px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #cbd5e1;padding:6px 8px;text-align:left}th{background:#f1f5f9}</style></head><body><h2>Data Guru & PTK</h2><div class="table-wrapper"><table><thead><tr>';
            rows[0].forEach(h => { html += '<th>' + h + '</th>'; });
            html += '</tr></thead><tbody>';
            rows.slice(1).forEach(r => {
                html += '<tr>';
                r.forEach(c => { html += '<td>' + c + '</td>'; });
                html += '</tr>';
            });
            html += '</tbody></table></div><script>window.onload=function(){window.print();}<\/script></body></html>';
            win.document.open(); win.document.write(html); win.document.close();
        }

        function openTambahGuruModal() {
            document.getElementById('modalTambahGuru').style.display = 'flex';
        }

        function closeTambahGuruModal() {
            document.getElementById('modalTambahGuru').style.display = 'none';
        }

        function toggleAkunFields(checked) {
            const el = document.getElementById('akunFields');
            if (el) el.style.display = checked ? 'grid' : 'none';
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeTambahGuruModal();
        });

        @if($errors->hasAny(['nama', 'nip', 'nuptk', 'jk', 'jenis_ptk', 'jabatan', 'gelar_depan', 'gelar_belakang', 'nama_pengguna', 'kata_sandi']))
            document.addEventListener('DOMContentLoaded', function() {
                openTambahGuruModal();
            });
        @endif
    </script>
@endsection

