@extends('layouts.app')

@section('title', 'Buat Modul & Jurnal Sekaligus - Pembelajaran Mendalam')
@section('page_title', 'Buat Modul Ajar (RPM), Jurnal & Bundel Administrasi')
@section('page_subtitle', 'Terhubung otomatis ke Jadwal Pelajaran, Hari Libur, Prota & Prosem — mendukung Pratinjau Rencana & Cetak Bundel 1 Semester')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('header_actions')
    <a href="{{ route('guru.perangkat.index', ['jenis' => 'modul_ajar']) }}" class="btn btn-ghost btn-sm">
        <i class="fas fa-folder-open"></i> Lihat Modul Ajar
    </a>
    <a href="{{ route('guru.perangkat.jurnal.index') }}" class="btn btn-ghost btn-sm">
        <i class="fas fa-book-reader"></i> Lihat Jurnal Mengajar
    </a>
@endsection

@section('content')
    @include('guru.pm._nav')

    {{-- 0. Cetak Bundel Administrasi 1 Semester (1-Klik PDF Akreditasi) --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <strong><i class="fas fa-file-pdf"></i> Cetak Bundel Administrasi Guru 1 Semester (Siap Supervisi &amp; Akreditasi)</strong>
        </div>
        <div class="card-body">
            @php($daftarTingkatGen = \App\Support\SchoolLevel::allowedTingkatForUser(auth()->user()))
            <form method="GET" action="{{ route('guru.pm.bundel.pdf') }}" target="_blank" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
                <input type="hidden" name="tahun_ajaran" value="{{ $tahunAjaran }}">
                <div>
                    <label for="fld-pm-generate-tingkat" style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Kelas</label>
                    <select id="fld-pm-generate-tingkat" name="tingkat" style="padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        @foreach($daftarTingkatGen as $t)
                            <option value="{{ $t }}">Kelas {{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="fld-pm-generate-semester" style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Semester</label>
                    <select id="fld-pm-generate-semester" name="semester" style="padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        <option value="1">Semester 1 (Ganjil)</option>
                        <option value="2">Semester 2 (Genap)</option>
                    </select>
                </div>
                <div>
                    <label for="fld-pm-generate-mapel" style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Mata Pelajaran</label>
                    <select id="fld-pm-generate-mapel" name="mapel" style="padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        <option value="">Semua Mata Pelajaran</option>
                        @foreach($mapelList as $m)
                            <option value="{{ $m->nama }}">{{ $m->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-sm">
                    <i class="fas fa-print"></i> Cetak Bundel Lengkap PDF
                </button>
                <span style="font-size:12px;color:#667085">Berisi: Cover + Alokasi + CP + TP/ATP + KKTP + Prota + Prosem + Daftar RPM &amp; Jurnal + TTD &amp; Stempel.</span>
            </form>
        </div>
    </div>

    {{-- 1. Generator Batch --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <strong><i class="fas fa-bolt"></i> Generator Batch — Modul Ajar (RPM) &amp; Jurnal Mengajar Sesuai Jadwal</strong>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('guru.pm.generate.batch') }}" style="display:grid;gap:14px">
                @csrf
                <input type="hidden" name="tahun_ajaran" value="{{ $tahunAjaran }}">

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px">
                    <div>
                        <label for="fld-pm-generate-tingkat-2" style="font-size:12px;font-weight:600">Tingkat Kelas *</label>
                        <select id="fld-pm-generate-tingkat-2" name="tingkat" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                            @foreach($daftarTingkatGen as $t)
                                <option value="{{ $t }}" {{ (int) ($inputPreview['tingkat'] ?? ($daftarTingkatGen[0] ?? 1)) === $t ? 'selected' : '' }}>Kelas {{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="fld-pm-generate-kelas" style="font-size:12px;font-weight:600">Nama Rombel / Kelas</label>
                        <input id="fld-pm-generate-kelas" type="text" name="kelas" value="{{ $inputPreview['kelas'] ?? '' }}" placeholder="Misal: 1A" list="rombelOptions"
                               style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        <datalist id="rombelOptions">
                            @foreach($rombelList as $r)
                                <option value="{{ $r->nama_rombel }}">
                            @endforeach
                        </datalist>
                    </div>
                    <div>
                        <label for="fld-pm-generate-semester-2" style="font-size:12px;font-weight:600">Semester *</label>
                        <select id="fld-pm-generate-semester-2" name="semester" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                            <option value="1" {{ (int) ($inputPreview['semester'] ?? 1) === 1 ? 'selected' : '' }}>Semester 1 (Ganjil)</option>
                            <option value="2" {{ (int) ($inputPreview['semester'] ?? 1) === 2 ? 'selected' : '' }}>Semester 2 (Genap)</option>
                        </select>
                    </div>
                    <div>
                        <label for="fld-pm-generate-dari-tanggal" style="font-size:12px;font-weight:600">Dari Tanggal *</label>
                        <input id="fld-pm-generate-dari-tanggal" type="date" name="dari_tanggal" value="{{ $inputPreview['dari_tanggal'] ?? $defaultDari }}" required
                               style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    </div>
                    <div>
                        <label for="fld-pm-generate-sampai-tanggal" style="font-size:12px;font-weight:600">Sampai Tanggal *</label>
                        <input id="fld-pm-generate-sampai-tanggal" type="date" name="sampai_tanggal" value="{{ $inputPreview['sampai_tanggal'] ?? $defaultSampai }}" required
                               style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    </div>
                </div>

                <div>
                    <div id="lbl-gen-mapel" style="font-size:12px;font-weight:600;display:block;margin-bottom:6px">Pilih Mata Pelajaran * <span style="font-weight:400;color:#667085">(otomatis mengikuti Jadwal Pelajaran bila tersedia)</span></div>
                    <div role="group" aria-labelledby="lbl-gen-mapel" style="display:flex;flex-wrap:wrap;gap:8px">
                        @php $selectedMapel = $inputPreview['mapel'] ?? []; @endphp
                        @foreach($mapelList as $m)
                            @php
                                $jItems = $jadwalMap[mb_strtolower(trim($m->nama))] ?? [];
                                $hariList = collect($jItems)->pluck('hari')->implode(', ');
                            @endphp
                            <label style="display:flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px;cursor:pointer">
                                <input type="checkbox" name="mapel[]" value="{{ $m->nama }}" {{ in_array($m->nama, $selectedMapel, true) ? 'checked' : '' }}>
                                <span>{{ $m->nama }}</span>
                                @if($hariList !== '')
                                    <span class="badge badge-success" style="font-size:10px">{{ $hariList }}</span>
                                @else
                                    <span class="badge badge-warning" style="font-size:10px">Fallback Sen–Jum</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;padding:12px;background:#F9FAFB;border:1px solid #EAECF0;border-radius:8px">
                    <div>
                        <div style="font-size:12px;font-weight:700;color:#344054;margin-bottom:6px">Keluaran Dokumen</div>
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;margin-bottom:4px">
                            <input type="checkbox" name="buat_modul" value="1" {{ ($inputPreview['buat_modul'] ?? 1) ? 'checked' : '' }}>
                            Buat Modul Ajar / RPM (1 modul per materi Prosem/Prota)
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px">
                            <input type="checkbox" name="buat_jurnal" value="1" {{ ($inputPreview['buat_jurnal'] ?? 1) ? 'checked' : '' }}>
                            Buat Jurnal Mengajar setiap pertemuan (hari libur otomatis dilewati)
                        </label>
                    </div>
                    <div>
                        <div style="font-size:12px;font-weight:700;color:#344054;margin-bottom:6px">Mode Penyusunan RPM</div>
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;margin-bottom:4px">
                            <input type="radio" name="mode" value="cepat" {{ ($inputPreview['mode'] ?? 'cepat') === 'cepat' ? 'checked' : '' }}>
                            <strong>Cepat (tanpa AI)</strong> — instan dari CP, TP &amp; Prosem
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;margin-bottom:4px">
                            <input type="radio" name="mode" value="ai" {{ ($inputPreview['mode'] ?? 'cepat') === 'ai' ? 'checked' : '' }} {{ !$aiQuota['configured'] ? 'disabled' : '' }}>
                            <strong>Lengkap dengan AI (Gemini)</strong> — maks 5 modul/batch
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:#475467;margin-left:18px">
                            <input type="checkbox" name="sertakan_lkpd" value="1" {{ !empty($inputPreview['sertakan_lkpd']) ? 'checked' : '' }}>
                            Sertakan LKPD &amp; Rubrik Penilaian (mode AI)
                        </label>
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap">
                    <button type="submit" formaction="{{ route('guru.pm.generate.pratinjau') }}" class="btn btn-ghost">
                        <i class="fas fa-eye"></i> Susun Pratinjau Rencana
                    </button>
                    <button type="submit" class="btn">
                        <i class="fas fa-bolt"></i> Generate Modul &amp; Jurnal Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Pratinjau Rencana Pertemuan (bila tombol Pratinjau diklik) --}}
    @if($rencanaPreview !== null)
        <div class="card" style="margin-bottom:16px;border:1px solid #2E90FA">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;background:#EFF8FF">
                <strong><i class="fas fa-calendar-check"></i> Pratinjau Rencana Pertemuan ({{ $rencanaPreview['ringkasan']['total_pertemuan'] }} pertemuan)</strong>
                <span style="font-size:12px;color:#175CD3">
                    Sesuai Jadwal: {{ $rencanaPreview['ringkasan']['pakai_jadwal'] }} · Fallback Efektif: {{ $rencanaPreview['ringkasan']['fallback_efektif'] }} · Hari Libur Dilewati: {{ $rencanaPreview['ringkasan']['libur_dilewati'] }}
                </span>
            </div>
            <div class="card-body" style="padding:0">
                @if(empty($rencanaPreview['items']))
                    <div style="padding:24px;text-align:center;color:#667085">Tidak ada pertemuan efektif dalam rentang tanggal ini.</div>
                @else
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width:42px">No</th>
                                    <th style="width:140px">Tanggal &amp; Hari</th>
                                    <th style="width:100px">Jam</th>
                                    <th style="width:160px">Mata Pelajaran</th>
                                    <th>Materi / Topik</th>
                                    <th style="width:55px;text-align:center">JP</th>
                                    <th style="width:130px">Sumber Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rencanaPreview['items'] as $idx => $it)
                                    <tr>
                                        <td>{{ $idx + 1 }}</td>
                                        <td style="white-space:nowrap"><strong>{{ $it['tanggal_id'] }}</strong> ({{ $it['hari'] }})</td>
                                        <td>{{ $it['jam_ke'] }}</td>
                                        <td style="font-weight:600">{{ $it['mapel'] }}</td>
                                        <td>{{ $it['materi'] }}</td>
                                        <td style="text-align:center">{{ $it['jp'] }}</td>
                                        <td>
                                            @if($it['sumber_jadwal'] === 'jadwal_pelajaran')
                                                <span class="badge badge-success">Jadwal Asli</span>
                                            @else
                                                <span class="badge badge-warning">Efektif Sen–Jum</span>
                                            @endif
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

    {{-- 2. Pembuat Satuan dengan AI: RPM & Jurnal --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px">
        <div class="card">
            <div class="card-header"><strong><i class="fas fa-magic"></i> Buat 1 Modul Ajar / RPM dengan AI</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('guru.pm.generate.rpm-ai') }}" style="display:grid;gap:10px">
                    @csrf
                    <div style="display:grid;grid-template-columns:1fr 110px;gap:8px">
                        <div>
                            <label for="fld-pm-generate-nama-mapel" style="font-size:12px;font-weight:600">Mata Pelajaran *</label>
                            <select id="fld-pm-generate-nama-mapel" name="nama_mapel" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                                @foreach($mapelList as $m)
                                    <option value="{{ $m->nama }}">{{ $m->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="fld-pm-generate-tingkat-3" style="font-size:12px;font-weight:600">Kelas *</label>
                            <select id="fld-pm-generate-tingkat-3" name="tingkat" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                                @foreach($daftarTingkatGen as $t)
                                    <option value="{{ $t }}">Kelas {{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="fld-pm-generate-materi" style="font-size:12px;font-weight:600">Materi / Topik Pertemuan *</label>
                        <input id="fld-pm-generate-materi" type="text" name="materi" required placeholder="Misal: Penjumlahan Bilangan Cacah dengan Benda Konkret"
                               style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 120px;gap:8px">
                        <div>
                            <label for="fld-pm-generate-model-pembelajaran" style="font-size:12px;font-weight:600">Model Pembelajaran</label>
                            <select id="fld-pm-generate-model-pembelajaran" name="model_pembelajaran" style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                                @foreach($modelList as $mdl)
                                    <option value="{{ $mdl }}">{{ $mdl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="fld-pm-generate-alokasi-waktu" style="font-size:12px;font-weight:600">Alokasi Waktu</label>
                            <input id="fld-pm-generate-alokasi-waktu" type="text" name="alokasi_waktu" value="2 x 35 menit"
                                   style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                    </div>
                    <div>
                        <label for="fld-pm-generate-catatan" style="font-size:12px;font-weight:600">Catatan untuk AI (opsional)</label>
                        <textarea id="fld-pm-generate-catatan" name="catatan" rows="2" placeholder="Misal: Gunakan konteks lingkungan sekitar sekolah..."
                                  style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px"></textarea>
                    </div>
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px">
                        <input type="checkbox" name="sertakan_lkpd" value="1" checked>
                        Sertakan LKPD (ringan tanpa gambar, siap cetak) &amp; Rubrik Penilaian
                    </label>
                    <div>
                        <button type="submit" class="btn btn-sm" {{ !$aiQuota['configured'] ? 'disabled' : '' }}>
                            <i class="fas fa-sparkles"></i> Buat RPM dengan AI
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong><i class="fas fa-book-open"></i> Isi 1 Jurnal Mengajar PM dengan AI</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('guru.pm.generate.jurnal-ai') }}" style="display:grid;gap:10px">
                    @csrf
                    <div style="display:grid;grid-template-columns:1fr 90px 100px;gap:8px">
                        <div>
                            <label for="fld-pm-generate-tanggal" style="font-size:12px;font-weight:600">Tanggal *</label>
                            <input id="fld-pm-generate-tanggal" type="date" name="tanggal" value="{{ now()->toDateString() }}" required
                                   style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                        <div>
                            <label for="fld-pm-generate-jam-ke" style="font-size:12px;font-weight:600">Jam Ke</label>
                            <input id="fld-pm-generate-jam-ke" type="text" name="jam_ke" value="1-2"
                                   style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                        <div>
                            <label for="fld-pm-generate-kelas-2" style="font-size:12px;font-weight:600">Kelas *</label>
                            <input id="fld-pm-generate-kelas-2" type="text" name="kelas" value="1A" required
                                   style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                        </div>
                    </div>
                    <div>
                        <label for="fld-pm-generate-nama-mapel-2" style="font-size:12px;font-weight:600">Mata Pelajaran *</label>
                        <select id="fld-pm-generate-nama-mapel-2" name="nama_mapel" required style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                            @foreach($mapelList as $m)
                                <option value="{{ $m->nama }}">{{ $m->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="fld-pm-generate-topik" style="font-size:12px;font-weight:600">Materi / Topik *</label>
                        <input id="fld-pm-generate-topik" type="text" name="topik" required placeholder="Misal: Mengenal Pecahan Sederhana"
                               style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    </div>
                    <div>
                        <label for="fld-pm-generate-kejadian-kelas" style="font-size:12px;font-weight:600">Apa yang terjadi di kelas hari ini?</label>
                        <textarea id="fld-pm-generate-kejadian-kelas" name="kejadian_kelas" rows="3" placeholder="Misal: Murid sangat antusias praktik memotong kertas..."
                                  style="width:100%;padding:7px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px"></textarea>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-sm" {{ !$aiQuota['configured'] ? 'disabled' : '' }}>
                            <i class="fas fa-sparkles"></i> Isi Jurnal dengan AI
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
