@php
    $isEdit = isset($perangkat);
    $selectedJenis = old('jenis', $isEdit ? $perangkat->jenis : ($jenis ?? 'cp'));
    $konten = old('konten', $isEdit ? ($perangkat->konten ?? []) : ($templates[$selectedJenis] ?? []));
    $dimensiOptions = [
        'Keimanan & Ketakwaan terhadap Tuhan YME',
        'Kewargaan',
        'Penalaran Kritis',
        'Kreativitas',
        'Kolaborasi',
        'Kemandirian',
        'Kesehatan',
        'Komunikasi',
    ];
    $daftarTp = $konten['daftar_tp'] ?? ($templates['tp']['daftar_tp'] ?? []);
    $alurItem = $konten['alur_item'] ?? ($templates['atp']['alur_item'] ?? []);
    $daftarUnit = $konten['daftar_unit'] ?? ($templates['prota']['daftar_unit'] ?? []);
    $daftarMinggu = $konten['daftar_minggu'] ?? ($templates['promes']['daftar_minggu'] ?? []);
    $selectedDimensi = is_array($konten['dimensi'] ?? null) ? $konten['dimensi'] : ($templates['modul_ajar']['dimensi'] ?? []);
@endphp

@if($errors->any())
    <div class="alert alert-bad" style="margin-bottom:14px">
        <ul style="margin:0;padding-left:18px">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card" style="margin-bottom:16px">
    <div class="card-header"><strong>1. Identitas Perangkat Ajar</strong></div>
    <div class="card-body">
        <div class="grid grid-2" style="gap:14px;margin-bottom:14px">
            <div>
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Jenis Perangkat *</label>
                <select name="jenis" id="jenisPerangkatSelect" required
                        style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px"
                        onchange="toggleSectionByJenis(this.value)">
                    @foreach($jenisLabels as $kode => $label)
                        <option value="{{ $kode }}" {{ $selectedJenis === $kode ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Judul Dokumen *</label>
                <input type="text" name="judul" required
                       value="{{ old('judul', $isEdit ? $perangkat->judul : '') }}"
                       placeholder="Contoh: ATP Matematika Fase C Kelas 6 Semester Ganjil"
                       style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
            </div>
        </div>

        <div class="grid grid-3" style="gap:14px;margin-bottom:14px">
            <div>
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Mata Pelajaran *</label>
                <input type="text" name="mata_pelajaran" required
                       value="{{ old('mata_pelajaran', $isEdit ? $perangkat->mata_pelajaran : '') }}"
                       placeholder="Contoh: Matematika / IPAS / Bahasa Indonesia"
                       style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
            </div>
            <div>
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Rombel (Opsional)</label>
                <select name="rombel_id" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    <option value="">— Pilih Rombel —</option>
                    @foreach($rombelList as $r)
                        <option value="{{ $r->id }}" {{ (string) old('rombel_id', $isEdit ? $perangkat->rombel_id : '') === (string) $r->id ? 'selected' : '' }}>
                            {{ $r->nama_rombel }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Label Kelas *</label>
                <input type="text" name="kelas"
                       value="{{ old('kelas', $isEdit ? $perangkat->kelas : '') }}"
                       placeholder="Contoh: 6A atau Kelas VI"
                       style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
            </div>
        </div>

        <div class="grid grid-4" style="gap:14px;margin-bottom:14px">
            <div>
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Fase Kurikulum</label>
                <select name="fase" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    <option value="">— Pilih Fase —</option>
                    @foreach(\App\Models\Perangkat\PerangkatPembelajaran::FASE_VALID as $f)
                        <option value="{{ $f }}" {{ old('fase', $isEdit ? $perangkat->fase : 'C') === $f ? 'selected' : '' }}>Fase {{ $f }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Semester</label>
                <select name="semester" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    @php $defaultSem = isset($semesterAktif) && $semesterAktif?->nama_semester ? ucfirst((string) $semesterAktif->nama_semester) : 'Ganjil'; @endphp
                    @foreach(['Ganjil', 'Genap', 'Tahunan'] as $sm)
                        <option value="{{ $sm }}" {{ old('semester', $isEdit ? $perangkat->semester : $defaultSem) === $sm ? 'selected' : '' }}>{{ $sm }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Tahun Ajaran</label>
                <input type="text" name="tahun_ajaran"
                       value="{{ old('tahun_ajaran', $isEdit ? $perangkat->tahun_ajaran : ($semesterAktif->tahun_ajaran ?? date('Y').'/'.(date('Y')+1))) }}"
                       placeholder="2026/2027"
                       style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
            </div>
            <div>
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Alokasi Waktu</label>
                <input type="text" name="alokasi_waktu"
                       value="{{ old('alokasi_waktu', $isEdit ? $perangkat->alokasi_waktu : '') }}"
                       placeholder="Mis. 4 JP (4 x 35 menit)"
                       style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
            </div>
        </div>

        <div class="grid grid-2" style="gap:14px;margin-bottom:14px">
            <div>
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Tautkan ke Kelas Digital (Opsional)</label>
                <select name="kelas_digital_id" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    <option value="">— Tidak ditautkan —</option>
                    @foreach($kelasDigitalList as $kd)
                        <option value="{{ $kd->id }}" {{ (string) old('kelas_digital_id', $isEdit ? $perangkat->kelas_digital_id : '') === (string) $kd->id ? 'selected' : '' }}>
                            {{ $kd->nama_kelas }} ({{ $kd->mata_pelajaran }} • {{ $kd->kelas }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Status &amp; Berbagi</label>
                <div style="display:flex;gap:14px;align-items:center;padding-top:6px;flex-wrap:wrap">
                    <select name="status" style="padding:8px 10px;border:1px solid #E4E7EC;border-radius:8px">
                        <option value="final" {{ old('status', $isEdit ? $perangkat->status : 'final') === 'final' ? 'selected' : '' }}>Final</option>
                        <option value="draf" {{ old('status', $isEdit ? $perangkat->status : 'final') === 'draf' ? 'selected' : '' }}>Draf</option>
                    </select>
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                        <input type="hidden" name="dibagikan_ke_walas" value="0">
                        <input type="checkbox" name="dibagikan_ke_walas" value="1"
                               {{ old('dibagikan_ke_walas', $isEdit ? (int) $perangkat->dibagikan_ke_walas : 1) ? 'checked' : '' }}>
                        Bagikan baca-saja ke Wali Kelas
                    </label>
                </div>
            </div>
        </div>

        <div>
            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Ringkasan / Catatan Umum</label>
            <textarea name="ringkasan" rows="2" placeholder="Ringkasan singkat tujuan atau ruang lingkup dokumen..."
                      style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">{{ old('ringkasan', $isEdit ? $perangkat->ringkasan : '') }}</textarea>
        </div>
    </div>
</div>

{{-- 2. KONTEN TERSTRUKTUR PER JENIS --}}
<div class="card" style="margin-bottom:16px">
    <div class="card-header"><strong>2. Isi Terstruktur Dokumen</strong></div>
    <div class="card-body">

        {{-- JENIS: CP (Capaian Pembelajaran) --}}
        <div class="jenis-section" data-jenis="cp" style="{{ $selectedJenis === 'cp' ? '' : 'display:none' }}">
            <div style="margin-bottom:12px">
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Elemen Capaian Pembelajaran</label>
                <input type="text" name="konten[elemen]" value="{{ $konten['elemen'] ?? '' }}"
                       style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
            </div>
            <div style="margin-bottom:12px">
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Rumusan Capaian Pembelajaran (CP)</label>
                <textarea name="konten[capaian_pembelajaran]" rows="4" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">{{ $konten['capaian_pembelajaran'] ?? '' }}</textarea>
            </div>
            <div class="grid grid-2" style="gap:12px;margin-bottom:12px">
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Kompetensi Inti / Kata Kerja Operasional</label>
                    <textarea name="konten[kompetensi_inti]" rows="4" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">{{ $konten['kompetensi_inti'] ?? '' }}</textarea>
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Konten / Lingkup Materi Esensial</label>
                    <textarea name="konten[konten_materi]" rows="4" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">{{ $konten['konten_materi'] ?? '' }}</textarea>
                </div>
            </div>
            <div>
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Catatan Fase / Acuan Regulasi</label>
                <input type="text" name="konten[catatan_fase]" value="{{ $konten['catatan_fase'] ?? '' }}"
                       style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
            </div>
        </div>

        {{-- JENIS: TP (Tujuan Pembelajaran) --}}
        <div class="jenis-section" data-jenis="tp" style="{{ $selectedJenis === 'tp' ? '' : 'display:none' }}">
            <div class="grid grid-2" style="gap:12px;margin-bottom:12px">
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Elemen</label>
                    <input type="text" name="konten[elemen]" value="{{ $konten['elemen'] ?? '' }}"
                           style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Rujukan Capaian Pembelajaran (CP)</label>
                    <input type="text" name="konten[capaian_rujukan]" value="{{ $konten['capaian_rujukan'] ?? '' }}"
                           style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
            </div>
            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Daftar Tujuan Pembelajaran (TP)</label>
            <div class="table-wrapper" style="margin-bottom:10px">
                <table class="table" id="tbl-tp">
                    <thead>
                        <tr><th>Kode TP</th><th>Rumusan Tujuan Pembelajaran</th><th>Materi Pokok</th><th>JP</th></tr>
                    </thead>
                    <tbody>
                        @foreach($daftarTp as $idx => $row)
                            <tr>
                                <td><input type="text" name="konten[daftar_tp][{{ $idx }}][kode]" value="{{ $row['kode'] ?? '' }}" style="width:85px;padding:6px;border:1px solid #E4E7EC;border-radius:6px"></td>
                                <td><input type="text" name="konten[daftar_tp][{{ $idx }}][deskripsi]" value="{{ $row['deskripsi'] ?? '' }}" style="width:100%;padding:6px;border:1px solid #E4E7EC;border-radius:6px"></td>
                                <td><input type="text" name="konten[daftar_tp][{{ $idx }}][materi]" value="{{ $row['materi'] ?? '' }}" style="width:100%;padding:6px;border:1px solid #E4E7EC;border-radius:6px"></td>
                                <td><input type="text" name="konten[daftar_tp][{{ $idx }}][jp]" value="{{ $row['jp'] ?? '' }}" style="width:75px;padding:6px;border:1px solid #E4E7EC;border-radius:6px"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" onclick="addRow('tbl-tp', 'daftar_tp', ['kode','deskripsi','materi','jp'])" style="margin-bottom:12px">+ Tambah Baris TP</button>
            <div>
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Indikator Ketercapaian</label>
                <textarea name="konten[indikator_ketercapaian]" rows="2" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">{{ $konten['indikator_ketercapaian'] ?? '' }}</textarea>
            </div>
        </div>

        {{-- JENIS: ATP (Alur Tujuan Pembelajaran) --}}
        <div class="jenis-section" data-jenis="atp" style="{{ $selectedJenis === 'atp' ? '' : 'display:none' }}">
            <div class="grid grid-2" style="gap:12px;margin-bottom:12px">
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Elemen / Lingkup ATP</label>
                    <input type="text" name="konten[elemen]" value="{{ $konten['elemen'] ?? '' }}"
                           style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Kompetensi Prasyarat</label>
                    <input type="text" name="konten[prasyarat]" value="{{ $konten['prasyarat'] ?? '' }}"
                           style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
            </div>
            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Matriks Alur Tujuan Pembelajaran (ATP)</label>
            <div class="table-wrapper" style="margin-bottom:10px">
                <table class="table" id="tbl-atp">
                    <thead>
                        <tr><th>No</th><th>Kode TP</th><th>Tujuan Pembelajaran</th><th>Materi Pokok</th><th>JP</th><th>Sem</th><th>Dimensi Profil</th></tr>
                    </thead>
                    <tbody>
                        @foreach($alurItem as $idx => $row)
                            <tr>
                                <td><input type="text" name="konten[alur_item][{{ $idx }}][urutan]" value="{{ $row['urutan'] ?? ($idx + 1) }}" style="width:48px;padding:6px;border:1px solid #E4E7EC;border-radius:6px"></td>
                                <td><input type="text" name="konten[alur_item][{{ $idx }}][kode_tp]" value="{{ $row['kode_tp'] ?? '' }}" style="width:76px;padding:6px;border:1px solid #E4E7EC;border-radius:6px"></td>
                                <td><input type="text" name="konten[alur_item][{{ $idx }}][tujuan_pembelajaran]" value="{{ $row['tujuan_pembelajaran'] ?? '' }}" style="width:100%;min-width:180px;padding:6px;border:1px solid #E4E7EC;border-radius:6px"></td>
                                <td><input type="text" name="konten[alur_item][{{ $idx }}][materi_pokok]" value="{{ $row['materi_pokok'] ?? '' }}" style="width:100%;min-width:130px;padding:6px;border:1px solid #E4E7EC;border-radius:6px"></td>
                                <td><input type="text" name="konten[alur_item][{{ $idx }}][alokasi_jp]" value="{{ $row['alokasi_jp'] ?? '' }}" style="width:68px;padding:6px;border:1px solid #E4E7EC;border-radius:6px"></td>
                                <td><input type="text" name="konten[alur_item][{{ $idx }}][semester]" value="{{ $row['semester'] ?? '' }}" style="width:74px;padding:6px;border:1px solid #E4E7EC;border-radius:6px"></td>
                                <td><input type="text" name="konten[alur_item][{{ $idx }}][dimensi_profil]" value="{{ $row['dimensi_profil'] ?? '' }}" style="width:100%;min-width:130px;padding:6px;border:1px solid #E4E7EC;border-radius:6px"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" onclick="addRow('tbl-atp', 'alur_item', ['urutan','kode_tp','tujuan_pembelajaran','materi_pokok','alokasi_jp','semester','dimensi_profil'])">+ Tambah Baris Alur ATP</button>
        </div>

        {{-- JENIS: PROTA --}}
        <div class="jenis-section" data-jenis="prota" style="{{ $selectedJenis === 'prota' ? '' : 'display:none' }}">
            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Distribusi Program Tahunan (Prota)</label>
            <div class="table-wrapper" style="margin-bottom:10px">
                <table class="table" id="tbl-prota">
                    <thead>
                        <tr><th>Semester</th><th>Unit</th><th>Materi / Topik Pokok</th><th>Alokasi JP</th><th>Keterangan / Bulan</th></tr>
                    </thead>
                    <tbody>
                        @foreach($daftarUnit as $idx => $row)
                            <tr>
                                <td><input type="text" name="konten[daftar_unit][{{ $idx }}][semester]" value="{{ $row['semester'] ?? '' }}" style="width:90px;padding:6px;border:1px solid #E4E7EC;border-radius:6px"></td>
                                <td><input type="text" name="konten[daftar_unit][{{ $idx }}][nomor_unit]" value="{{ $row['nomor_unit'] ?? '' }}" style="width:85px;padding:6px;border:1px solid #E4E7EC;border-radius:6px"></td>
                                <td><input type="text" name="konten[daftar_unit][{{ $idx }}][materi_topik]" value="{{ $row['materi_topik'] ?? '' }}" style="width:100%;padding:6px;border:1px solid #E4E7EC;border-radius:6px"></td>
                                <td><input type="text" name="konten[daftar_unit][{{ $idx }}][alokasi_jp]" value="{{ $row['alokasi_jp'] ?? '' }}" style="width:80px;padding:6px;border:1px solid #E4E7EC;border-radius:6px"></td>
                                <td><input type="text" name="konten[daftar_unit][{{ $idx }}][keterangan]" value="{{ $row['keterangan'] ?? '' }}" style="width:100%;padding:6px;border:1px solid #E4E7EC;border-radius:6px"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" onclick="addRow('tbl-prota', 'daftar_unit', ['semester','nomor_unit','materi_topik','alokasi_jp','keterangan'])" style="margin-bottom:12px">+ Tambah Unit Prota</button>
            <div>
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Catatan Kalender Tahunan</label>
                <textarea name="konten[catatan_tahunan]" rows="2" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">{{ $konten['catatan_tahunan'] ?? '' }}</textarea>
            </div>
        </div>

        {{-- JENIS: PROMES --}}
        <div class="jenis-section" data-jenis="promes" style="{{ $selectedJenis === 'promes' ? '' : 'display:none' }}">
            <div style="margin-bottom:12px">
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Bulan Efektif Semester</label>
                <input type="text" name="konten[bulan_efektif]" value="{{ $konten['bulan_efektif'] ?? '' }}"
                       style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
            </div>
            <div class="table-wrapper" style="margin-bottom:10px">
                <table class="table" id="tbl-promes">
                    <thead>
                        <tr><th>Bulan</th><th>Minggu Ke-</th><th>Materi / Topik</th><th>JP</th><th>Keterangan</th></tr>
                    </thead>
                    <tbody>
                        @foreach($daftarMinggu as $idx => $row)
                            <tr>
                                <td><input type="text" name="konten[daftar_minggu][{{ $idx }}][bulan]" value="{{ $row['bulan'] ?? '' }}" style="width:100px;padding:6px;border:1px solid #E4E7EC;border-radius:6px"></td>
                                <td><input type="text" name="konten[daftar_minggu][{{ $idx }}][minggu_ke]" value="{{ $row['minggu_ke'] ?? '' }}" style="width:85px;padding:6px;border:1px solid #E4E7EC;border-radius:6px"></td>
                                <td><input type="text" name="konten[daftar_minggu][{{ $idx }}][materi_topik]" value="{{ $row['materi_topik'] ?? '' }}" style="width:100%;padding:6px;border:1px solid #E4E7EC;border-radius:6px"></td>
                                <td><input type="text" name="konten[daftar_minggu][{{ $idx }}][alokasi_jp]" value="{{ $row['alokasi_jp'] ?? '' }}" style="width:75px;padding:6px;border:1px solid #E4E7EC;border-radius:6px"></td>
                                <td><input type="text" name="konten[daftar_minggu][{{ $idx }}][keterangan]" value="{{ $row['keterangan'] ?? '' }}" style="width:100%;padding:6px;border:1px solid #E4E7EC;border-radius:6px"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" onclick="addRow('tbl-promes', 'daftar_minggu', ['bulan','minggu_ke','materi_topik','alokasi_jp','keterangan'])" style="margin-bottom:12px">+ Tambah Baris Promes</button>
            <div>
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Catatan Kalender Semester</label>
                <textarea name="konten[catatan_kalender]" rows="2" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">{{ $konten['catatan_kalender'] ?? '' }}</textarea>
            </div>
        </div>

        {{-- JENIS: MODUL AJAR / RPP (Pembelajaran Mendalam) --}}
        <div class="jenis-section" data-jenis="modul_ajar" style="{{ $selectedJenis === 'modul_ajar' ? '' : 'display:none' }}">
            <div style="margin-bottom:12px">
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Tujuan Pembelajaran</label>
                <textarea name="konten[tujuan_pembelajaran]" rows="2" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">{{ $konten['tujuan_pembelajaran'] ?? '' }}</textarea>
            </div>

            <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:10px;padding:12px;margin-bottom:14px">
                <div style="font-weight:700;font-size:13px;margin-bottom:8px;color:#0F172A">Prinsip &amp; 8 Dimensi Profil Lulusan (Pembelajaran Mendalam)</div>
                <div style="display:flex;flex-wrap:wrap;gap:14px;margin-bottom:10px">
                    <label style="font-size:13px;display:flex;align-items:center;gap:6px">
                        <input type="checkbox" name="konten[pm][berkesadaran]" value="1" {{ !empty($konten['pm']['berkesadaran']) ? 'checked' : '' }}> Berkesadaran (Mindful)
                    </label>
                    <label style="font-size:13px;display:flex;align-items:center;gap:6px">
                        <input type="checkbox" name="konten[pm][bermakna]" value="1" {{ !empty($konten['pm']['bermakna']) ? 'checked' : '' }}> Bermakna (Meaningful)
                    </label>
                    <label style="font-size:13px;display:flex;align-items:center;gap:6px">
                        <input type="checkbox" name="konten[pm][menggembirakan]" value="1" {{ !empty($konten['pm']['menggembirakan']) ? 'checked' : '' }}> Menggembirakan (Joyful)
                    </label>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:10px">
                    @foreach($dimensiOptions as $dim)
                        <label style="font-size:12px;background:#fff;border:1px solid #E4E7EC;border-radius:999px;padding:4px 10px;display:inline-flex;align-items:center;gap:5px">
                            <input type="checkbox" name="konten[dimensi][]" value="{{ $dim }}" {{ in_array($dim, $selectedDimensi, true) ? 'checked' : '' }}>
                            {{ $dim }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="grid grid-3" style="gap:12px;margin-bottom:12px">
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Pengalaman: Memahami</label>
                    <textarea name="konten[pengalaman][memahami]" rows="3" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">{{ $konten['pengalaman']['memahami'] ?? '' }}</textarea>
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Pengalaman: Mengaplikasi</label>
                    <textarea name="konten[pengalaman][mengaplikasi]" rows="3" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">{{ $konten['pengalaman']['mengaplikasi'] ?? '' }}</textarea>
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Pengalaman: Merefleksi</label>
                    <textarea name="konten[pengalaman][merefleksi]" rows="3" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">{{ $konten['pengalaman']['merefleksi'] ?? '' }}</textarea>
                </div>
            </div>

            <div class="grid grid-3" style="gap:12px;margin-bottom:12px">
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Kegiatan Pendahuluan</label>
                    <textarea name="konten[pendahuluan]" rows="3" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">{{ $konten['pendahuluan'] ?? '' }}</textarea>
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Kegiatan Inti</label>
                    <textarea name="konten[kegiatan_inti]" rows="3" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">{{ $konten['kegiatan_inti'] ?? '' }}</textarea>
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Kegiatan Penutup</label>
                    <textarea name="konten[penutup]" rows="3" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">{{ $konten['penutup'] ?? '' }}</textarea>
                </div>
            </div>

            <div class="grid grid-3" style="gap:12px">
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Asesmen Formatif &amp; Sumatif</label>
                    <textarea name="konten[asesmen]" rows="3" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">{{ $konten['asesmen'] ?? '' }}</textarea>
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Media &amp; Sumber Belajar</label>
                    <textarea name="konten[media_sumber]" rows="3" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">{{ $konten['media_sumber'] ?? '' }}</textarea>
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Refleksi Guru</label>
                    <textarea name="konten[refleksi_guru]" rows="3" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">{{ $konten['refleksi_guru'] ?? '' }}</textarea>
                </div>
            </div>
        </div>

        {{-- JENIS: KKM / KKTP --}}
        <div class="jenis-section" data-jenis="kkm" style="{{ $selectedJenis === 'kkm' ? '' : 'display:none' }}">
            <div class="grid grid-4" style="gap:12px;margin-bottom:14px">
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Kompleksitas (0-100)</label>
                    <input type="number" min="0" max="100" name="konten[kompleksitas]" value="{{ $konten['kompleksitas'] ?? 75 }}" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Daya Dukung (0-100)</label>
                    <input type="number" min="0" max="100" name="konten[daya_dukung]" value="{{ $konten['daya_dukung'] ?? 78 }}" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Intake Siswa (0-100)</label>
                    <input type="number" min="0" max="100" name="konten[intake]" value="{{ $konten['intake'] ?? 75 }}" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Nilai KKM / KKTP</label>
                    <input type="number" min="0" max="100" name="konten[nilai_kkm]" value="{{ $konten['nilai_kkm'] ?? 76 }}" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px;font-weight:700">
                </div>
            </div>
            <div class="grid grid-2" style="gap:12px;margin-bottom:12px">
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Interval Perlu Bimbingan</label>
                    <input type="text" name="konten[interval_perlu_bimbingan]" value="{{ $konten['interval_perlu_bimbingan'] ?? '' }}" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Interval Cukup</label>
                    <input type="text" name="konten[interval_cukup]" value="{{ $konten['interval_cukup'] ?? '' }}" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Interval Baik (Tuntas)</label>
                    <input type="text" name="konten[interval_baik]" value="{{ $konten['interval_baik'] ?? '' }}" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Interval Sangat Baik</label>
                    <input type="text" name="konten[interval_sangat_baik]" value="{{ $konten['interval_sangat_baik'] ?? '' }}" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
            </div>
            <div>
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Catatan Penilaian &amp; Tindak Lanjut</label>
                <textarea name="konten[catatan_penilaian]" rows="2" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">{{ $konten['catatan_penilaian'] ?? '' }}</textarea>
            </div>
        </div>

        <hr style="border:none;border-top:1px solid #EAECF0;margin:16px 0">
        <div>
            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Lampiran Dokumen Pendukung (Opsional — PDF/DOCX/XLSX/PPTX/Gambar, maks 10 MB)</label>
            <input type="file" name="lampiran" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.webp">
            @if($isEdit && $perangkat->lampiran_nama)
                <div style="font-size:12px;color:#667085;margin-top:6px">
                    Lampiran saat ini: <strong>{{ $perangkat->lampiran_nama }}</strong> (unggah berkas baru untuk mengganti)
                </div>
            @endif
        </div>
    </div>
</div>

<div style="display:flex;gap:10px">
    <button type="submit" class="btn"><i class="fas fa-save"></i> Simpan Perangkat Ajar</button>
    <a href="{{ route('guru.perangkat.index') }}" class="btn btn-ghost">Batal</a>
</div>

<script>
function toggleSectionByJenis(jenis) {
    document.querySelectorAll('.jenis-section').forEach(function (el) {
        var active = el.getAttribute('data-jenis') === jenis;
        el.style.display = active ? '' : 'none';
        el.querySelectorAll('input, textarea, select').forEach(function (field) {
            field.disabled = !active;
        });
    });
}
function addRow(tableId, groupName, fields) {
    var tbody = document.querySelector('#' + tableId + ' tbody');
    if (!tbody) return;
    var idx = tbody.querySelectorAll('tr').length;
    var tr = document.createElement('tr');
    fields.forEach(function (f) {
        var td = document.createElement('td');
        var inp = document.createElement('input');
        inp.type = 'text';
        inp.name = 'konten[' + groupName + '][' + idx + '][' + f + ']';
        inp.style.cssText = 'width:100%;padding:6px;border:1px solid #E4E7EC;border-radius:6px';
        if (f === 'urutan') inp.value = String(idx + 1);
        td.appendChild(inp);
        tr.appendChild(td);
    });
    tbody.appendChild(tr);
}
document.addEventListener('DOMContentLoaded', function () {
    var sel = document.getElementById('jenisPerangkatSelect');
    if (sel) toggleSectionByJenis(sel.value);
});
</script>
