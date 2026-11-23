@extends('layouts.app')

@section('title', $kelasDigital->nama_kelas)
@section('sidebar') @include('guru.partials.sidebar') @endsection

@php
    $dimensiLabels = [
        0 => 'Keimanan & Ketakwaan',
        1 => 'Kewargaan',
        2 => 'Penalaran Kritis',
        3 => 'Kreativitas',
        4 => 'Kolaborasi',
        5 => 'Kemandirian',
        6 => 'Kesehatan',
        7 => 'Komunikasi',
    ];
    $totalPertemuan = $kelasDigital->modul->sum(fn ($m) => $m->pertemuan->count());
    $totalTugas = $kelasDigital->modul->sum(fn ($m) => $m->pertemuan->sum(fn ($p) => $p->tugas->count()));
@endphp

@section('content')
<div class="page-header">
    <div>
        <div style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap;margin-bottom:.35rem">
            <span class="badge badge-blue">{{ $kelasDigital->mata_pelajaran }}</span>
            <span class="badge badge-gray">{{ $kelasDigital->rombel?->nama_rombel ?? $kelasDigital->kelas }}</span>
            <span class="badge {{ $kelasDigital->aktif ? 'badge-green' : 'badge-amber' }}">{{ $kelasDigital->aktif ? 'Aktif' : 'Arsip' }}</span>
        </div>
        <h1 class="page-title">{{ $kelasDigital->nama_kelas }}</h1>
        @if($kelasDigital->deskripsi)
            <p class="page-subtitle">{{ $kelasDigital->deskripsi }}</p>
        @endif
    </div>
    <div class="page-actions" style="display:flex;gap:.45rem;flex-wrap:wrap">
        <a href="{{ route('guru.kelas-digital.index') }}" class="btn btn-outline btn-sm">Semua Kelas</a>
        <a href="{{ route('guru.kelas-digital.edit', $kelasDigital) }}" class="btn btn-outline btn-sm">Pengaturan</a>
        <form method="POST" action="{{ route('guru.kelas-digital.destroy', $kelasDigital) }}" onsubmit="return confirm('Hapus kelas digital ini?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
        </form>
    </div>
</div>

{{-- Statistik Ringkas --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.85rem;margin-bottom:1.25rem">
    <div class="card"><div class="card-body" style="padding:1rem">
        <div style="font-size:.75rem;color:#64748b">Total Modul / Bab</div>
        <div style="font-size:1.45rem;font-weight:800;color:#0f172a">{{ $kelasDigital->modul->count() }}</div>
    </div></div>
    <div class="card"><div class="card-body" style="padding:1rem">
        <div style="font-size:.75rem;color:#64748b">Total Pertemuan</div>
        <div style="font-size:1.45rem;font-weight:800;color:#0f172a">{{ $totalPertemuan }}</div>
    </div></div>
    <div class="card"><div class="card-body" style="padding:1rem">
        <div style="font-size:.75rem;color:#64748b">Total Tugas</div>
        <div style="font-size:1.45rem;font-weight:800;color:#0f172a">{{ $totalTugas }}</div>
    </div></div>
    <div class="card"><div class="card-body" style="padding:1rem">
        <div style="font-size:.75rem;color:#64748b">Peserta Kelas</div>
        <div style="font-size:1.45rem;font-weight:800;color:#0f172a">{{ $peserta->count() }}</div>
    </div></div>
</div>

{{-- Form Tambah Modul / Bab --}}
<details class="card" style="margin-bottom:1.25rem" @if($kelasDigital->modul->isEmpty()) open @endif>
    <summary class="card-header" style="cursor:pointer;list-style:none;display:flex;justify-content:space-between;align-items:center">
        <span class="card-title">+ Tambah Modul / Bab Pembelajaran Mendalam</span>
        <span style="font-size:.8rem;color:#64748b">Klik untuk buka/tutup formulir</span>
    </summary>
    <div class="card-body">
        <form method="POST" action="{{ route('guru.kelas-digital.modul.store', $kelasDigital) }}">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 120px;gap:.75rem">
                <div class="form-group">
                    <label for="fld-kelas-digital-show-judul" class="form-label">Judul Modul / Bab <span style="color:var(--bad)">*</span></label>
                    <input id="fld-kelas-digital-show-judul" type="text" name="judul" class="form-input" placeholder="Contoh: Bab 1 — Pecahan dan Desimal" required>
                </div>
                <div class="form-group">
                    <label for="fld-kelas-digital-show-urutan" class="form-label">Urutan</label>
                    <input id="fld-kelas-digital-show-urutan" type="number" name="urutan" min="1" class="form-input" value="{{ $kelasDigital->modul->count() + 1 }}">
                </div>
            </div>

            <div class="form-group">
                <label for="fld-kelas-digital-show-tujuan-pembelajaran" class="form-label">Tujuan Pembelajaran (TP)</label>
                <textarea id="fld-kelas-digital-show-tujuan-pembelajaran" name="tujuan_pembelajaran" rows="2" class="form-textarea" placeholder="Peserta didik mampu..."></textarea>
            </div>

            <div class="form-group">
                <div id="lbl-kd-pm" class="form-label">Prinsip Pembelajaran Mendalam</div>
                <div role="group" aria-labelledby="lbl-kd-pm" style="display:flex;gap:1rem;flex-wrap:wrap;font-size:.85rem">
                    <label style="display:flex;align-items:center;gap:.35rem"><input type="checkbox" name="pm[]" value="berkesadaran"> Berkesadaran</label>
                    <label style="display:flex;align-items:center;gap:.35rem"><input type="checkbox" name="pm[]" value="bermakna"> Bermakna</label>
                    <label style="display:flex;align-items:center;gap:.35rem"><input type="checkbox" name="pm[]" value="menggembirakan"> Menggembirakan</label>
                </div>
            </div>

            <div class="form-group">
                <div id="lbl-kd-dimensi" class="form-label">8 Dimensi Profil Lulusan</div>
                <div role="group" aria-labelledby="lbl-kd-dimensi" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:.4rem;font-size:.82rem">
                    @foreach($dimensiLabels as $idx => $labelDim)
                        <label style="display:flex;align-items:center;gap:.35rem">
                            <input type="checkbox" name="dimensi[]" value="{{ $idx }}"> {{ $labelDim }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:.75rem">
                <div class="form-group">
                    <label for="fld-kelas-digital-show-pengalaman-memahami" class="form-label">Pengalaman: Memahami</label>
                    <input id="fld-kelas-digital-show-pengalaman-memahami" type="text" name="pengalaman_memahami" class="form-input" placeholder="Aktivitas membangun konsep...">
                </div>
                <div class="form-group">
                    <label for="fld-kelas-digital-show-pengalaman-mengaplikasi" class="form-label">Pengalaman: Mengaplikasi</label>
                    <input id="fld-kelas-digital-show-pengalaman-mengaplikasi" type="text" name="pengalaman_mengaplikasi" class="form-input" placeholder="Penerapan kontekstual...">
                </div>
                <div class="form-group">
                    <label for="fld-kelas-digital-show-pengalaman-merefleksi" class="form-label">Pengalaman: Merefleksi</label>
                    <input id="fld-kelas-digital-show-pengalaman-merefleksi" type="text" name="pengalaman_merefleksi" class="form-input" placeholder="Refleksi diri siswa...">
                </div>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
                <label style="display:flex;align-items:center;gap:.4rem;font-size:.85rem">
                    <input type="hidden" name="dipublikasikan" value="0">
                    <input type="checkbox" name="dipublikasikan" value="1" checked> Langsung tampilkan ke siswa
                </label>
                <button type="submit" class="btn btn-primary btn-sm">Simpan Modul</button>
            </div>
        </form>
    </div>
</details>

{{-- Daftar Modul & Pertemuan --}}
@forelse($kelasDigital->modul as $modul)
    <div class="card" style="margin-bottom:1.25rem">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:flex-start;gap:.75rem;flex-wrap:wrap">
            <div>
                <div style="display:flex;gap:.35rem;flex-wrap:wrap;align-items:center;margin-bottom:.25rem">
                    <span class="badge badge-blue">Modul {{ $modul->urutan }}</span>
                    <span class="badge {{ $modul->dipublikasikan ? 'badge-green' : 'badge-gray' }}">
                        {{ $modul->dipublikasikan ? 'Dipublikasikan' : 'Draf' }}
                    </span>
                    @foreach((array) $modul->pm as $pmKey => $pmVal)
                        @if($pmVal)
                            <span class="badge badge-purple">{{ ucfirst($pmKey) }}</span>
                        @endif
                    @endforeach
                </div>
                <h2 style="font-size:1.05rem;font-weight:700;color:#0f172a;margin:0">{{ $modul->judul }}</h2>
                @if($modul->tujuan_pembelajaran)
                    <p style="font-size:.82rem;color:#475569;margin:.3rem 0 0"><strong>TP:</strong> {{ $modul->tujuan_pembelajaran }}</p>
                @endif
            </div>
            <form method="POST" action="{{ route('guru.kelas-digital.modul.destroy', [$kelasDigital, $modul]) }}" onsubmit="return confirm('Hapus modul beserta pertemuan di dalamnya?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-outline btn-sm" style="color:var(--bad)">Hapus Modul</button>
            </form>
        </div>

        <div class="card-body">
            @forelse($modul->pertemuan as $pertemuan)
                <div style="border:1px solid #e2e8f0;border-radius:10px;padding:1rem;margin-bottom:1rem;background:#ffffff">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.75rem;flex-wrap:wrap;margin-bottom:.65rem">
                        <div>
                            <div style="display:flex;gap:.35rem;flex-wrap:wrap;align-items:center;margin-bottom:.2rem">
                                <span class="badge badge-gray">Pertemuan {{ $pertemuan->urutan }}</span>
                                @if($pertemuan->tanggal)
                                    <span style="font-size:.78rem;color:#64748b">{{ $pertemuan->tanggal->format('d M Y') }}</span>
                                @endif
                                @if($pertemuan->materi)
                                    <span class="badge badge-blue">Materi Ajar: {{ $pertemuan->materi->judul }}</span>
                                @endif
                                @if($pertemuan->kuis)
                                    <span class="badge badge-amber">Kuis: {{ $pertemuan->kuis->judul }}</span>
                                @endif
                            </div>
                            <h3 style="font-size:.98rem;font-weight:700;color:#1e293b;margin:0">{{ $pertemuan->judul }}</h3>
                            @if($pertemuan->deskripsi)
                                <p style="font-size:.82rem;color:#64748b;margin:.25rem 0 0">{{ $pertemuan->deskripsi }}</p>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('guru.kelas-digital.pertemuan.destroy', [$kelasDigital, $pertemuan]) }}" onsubmit="return confirm('Hapus pertemuan ini?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--bad)">Hapus</button>
                        </form>
                    </div>

                    {{-- Butir Konten Materi --}}
                    @if($pertemuan->items->isNotEmpty())
                        <div style="margin-bottom:.75rem">
                            <div style="font-size:.76rem;font-weight:700;text-transform:uppercase;color:#64748b;margin-bottom:.35rem">Konten Materi</div>
                            <div style="display:flex;flex-direction:column;gap:.4rem">
                                @foreach($pertemuan->items as $item)
                                    <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;padding:.5rem .75rem;background:#f8fafc;border-radius:8px;font-size:.85rem">
                                        <div>
                                            <span class="badge badge-gray" style="margin-right:.35rem">{{ strtoupper($item->tipe) }}</span>
                                            <strong>{{ $item->judul }}</strong>
                                            @if($item->file_path)
                                                <a href="{{ route('guru.kelas-digital.item.download', [$kelasDigital, $item]) }}" style="margin-left:.5rem;color:#2563eb;font-size:.8rem">Unduh ({{ $item->file_nama_asli }})</a>
                                            @elseif($item->tautan)
                                                <a href="{{ $item->tautan }}" target="_blank" rel="noopener noreferrer" style="margin-left:.5rem;color:#2563eb;font-size:.8rem">Buka Tautan</a>
                                            @endif
                                        </div>
                                        <form method="POST" action="{{ route('guru.kelas-digital.item.destroy', [$kelasDigital, $item]) }}" onsubmit="return confirm('Hapus konten ini?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-outline btn-sm">Hapus</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Daftar Tugas pada Pertemuan --}}
                    @if($pertemuan->tugas->isNotEmpty())
                        <div style="margin-bottom:.75rem">
                            <div style="font-size:.76rem;font-weight:700;text-transform:uppercase;color:#64748b;margin-bottom:.35rem">Tugas Pertemuan</div>
                            <div style="display:flex;flex-direction:column;gap:.45rem">
                                @foreach($pertemuan->tugas as $tugas)
                                    <div style="display:flex;justify-content:space-between;align-items:center;gap:.75rem;flex-wrap:wrap;padding:.6rem .75rem;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;font-size:.85rem">
                                        <div>
                                            <strong>{{ $tugas->judul }}</strong>
                                            <div style="font-size:.78rem;color:#78350f">
                                                Dikumpulkan: <strong>{{ $tugas->pengumpulan_count }}</strong> siswa
                                                @if($tugas->batas_waktu) · Tenggat: {{ $tugas->batas_waktu->format('d M Y H:i') }} @endif
                                                · Nilai maks: {{ $tugas->nilai_maksimal }}
                                                @if($tugas->sinkron_ke_nilai) · Sinkron Daftar Nilai @endif
                                            </div>
                                        </div>
                                        <div style="display:flex;gap:.35rem;flex-wrap:wrap">
                                            <a href="{{ route('guru.kelas-digital.tugas.pengumpulan', [$kelasDigital, $tugas]) }}" class="btn btn-primary btn-sm">Periksa &amp; Nilai</a>
                                            <form method="POST" action="{{ route('guru.kelas-digital.tugas.destroy', [$kelasDigital, $tugas]) }}" onsubmit="return confirm('Hapus tugas ini?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-outline btn-sm" style="color:var(--bad)">Hapus</button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Aksi Tambah Konten / Tugas pada Pertemuan --}}
                    <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:.5rem">
                        <details style="flex:1;min-width:260px;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px;padding:.6rem .8rem">
                            <summary style="cursor:pointer;font-size:.82rem;font-weight:600;color:#1d4ed8">+ Tambah Konten (Teks / Video / File / Link)</summary>
                            <form method="POST" action="{{ route('guru.kelas-digital.item.store', [$kelasDigital, $pertemuan]) }}" enctype="multipart/form-data" style="margin-top:.65rem">
                                @csrf
                                <div style="display:grid;grid-template-columns:130px 1fr;gap:.5rem;margin-bottom:.5rem">
                                    <select name="tipe" class="form-select" required>
                                        <option value="teks">Teks</option>
                                        <option value="video">Video (YouTube)</option>
                                        <option value="file">Berkas (PDF/Doc/Img)</option>
                                        <option value="link">Tautan Web</option>
                                    </select>
                                    <input type="text" name="judul" class="form-input" placeholder="Judul konten..." required>
                                </div>
                                <div class="form-group" style="margin-bottom:.5rem">
                                    <textarea name="konten" rows="2" class="form-textarea" placeholder="Isi ringkasan/teks materi (wajib untuk tipe Teks)"></textarea>
                                </div>
                                <div class="form-group" style="margin-bottom:.5rem">
                                    <input type="url" name="tautan" class="form-input" placeholder="https://... (wajib untuk Video atau Tautan)">
                                </div>
                                <div class="form-group" style="margin-bottom:.5rem">
                                    <input type="file" name="file" class="form-input">
                                    <small style="color:#64748b;font-size:.73rem">Maks 10 MB (PDF, Office, Gambar, MP3/MP4, TXT).</small>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">Simpan Konten</button>
                            </form>
                        </details>

                        <details style="flex:1;min-width:260px;background:#fefce8;border:1px dashed #eab308;border-radius:8px;padding:.6rem .8rem">
                            <summary style="cursor:pointer;font-size:.82rem;font-weight:600;color:#a16207">+ Tambah Tugas Pertemuan</summary>
                            <form method="POST" action="{{ route('guru.kelas-digital.tugas.store', [$kelasDigital, $pertemuan]) }}" enctype="multipart/form-data" style="margin-top:.65rem">
                                @csrf
                                <div class="form-group" style="margin-bottom:.5rem">
                                    <input type="text" name="judul" class="form-input" placeholder="Judul tugas..." required>
                                </div>
                                <div class="form-group" style="margin-bottom:.5rem">
                                    <textarea name="instruksi" rows="2" class="form-textarea" placeholder="Instruksi pengerjaan tugas..." required></textarea>
                                </div>
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-bottom:.5rem">
                                    <select name="tipe_pengumpulan" class="form-select">
                                        <option value="keduanya">Teks atau Berkas</option>
                                        <option value="teks">Jawaban Teks</option>
                                        <option value="file">Unggah Berkas</option>
                                    </select>
                                    <input type="number" name="nilai_maksimal" min="1" max="100" value="100" class="form-input" placeholder="Nilai maks">
                                </div>
                                <div class="form-group" style="margin-bottom:.5rem">
                                    <label class="form-label" for="pertemuan_{{ $pertemuan->id }}_batas_waktu" style="font-size:.75rem">Batas Waktu (opsional)</label>
                                    <input type="datetime-local" id="pertemuan_{{ $pertemuan->id }}_batas_waktu" name="batas_waktu" class="form-input">
                                </div>
                                <div class="form-group" style="margin-bottom:.5rem">
                                    <label class="form-label" for="pertemuan_{{ $pertemuan->id }}_lampiran" style="font-size:.75rem">Lampiran Soal (opsional)</label>
                                    <input type="file" id="pertemuan_{{ $pertemuan->id }}_lampiran" name="lampiran" class="form-input">
                                </div>
                                <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap">
                                    <label style="font-size:.78rem;display:flex;align-items:center;gap:.35rem">
                                        <input type="hidden" name="sinkron_ke_nilai" value="0">
                                        <input type="checkbox" name="sinkron_ke_nilai" value="1" checked> Sinkron ke Daftar Nilai
                                    </label>
                                    <button type="submit" class="btn btn-primary btn-sm">Simpan Tugas</button>
                                </div>
                            </form>
                        </details>
                    </div>
                </div>
            @empty
                <p style="font-size:.85rem;color:#64748b;margin-bottom:.75rem">Belum ada pertemuan pada modul ini.</p>
            @endforelse

            {{-- Form Tambah Pertemuan di Modul --}}
            <details style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:.75rem 1rem">
                <summary style="cursor:pointer;font-size:.85rem;font-weight:700;color:#0f172a">+ Tambah Pertemuan ke "{{ $modul->judul }}"</summary>
                <form method="POST" action="{{ route('guru.kelas-digital.pertemuan.store', [$kelasDigital, $modul]) }}" style="margin-top:.75rem">
                    @csrf
                    <div style="display:grid;grid-template-columns:1fr 160px 100px;gap:.65rem">
                        <div class="form-group">
                            <label class="form-label" for="modul_{{ $modul->id }}_judul">Judul Pertemuan <span style="color:var(--bad)">*</span></label>
                            <input type="text" id="modul_{{ $modul->id }}_judul" name="judul" class="form-input" placeholder="Contoh: Pertemuan 1 — Mengenal Pecahan Senilai" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="modul_{{ $modul->id }}_tanggal">Tanggal</label>
                            <input type="date" id="modul_{{ $modul->id }}_tanggal" name="tanggal" class="form-input" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="modul_{{ $modul->id }}_urutan">Urutan</label>
                            <input type="number" id="modul_{{ $modul->id }}_urutan" name="urutan" min="1" class="form-input" value="{{ $modul->pertemuan->count() + 1 }}">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="modul_{{ $modul->id }}_deskripsi">Ringkasan Pertemuan</label>
                        <textarea id="modul_{{ $modul->id }}_deskripsi" name="deskripsi" rows="2" class="form-textarea" placeholder="Arahan kegiatan belajar pertemuan ini..."></textarea>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.65rem">
                        <div class="form-group">
                            <label class="form-label" for="modul_{{ $modul->id }}_materi_id">Tautkan Materi Ajar Eksisting (opsional)</label>
                            <select id="modul_{{ $modul->id }}_materi_id" name="materi_id" class="form-select">
                                <option value="">-- Tidak ditautkan --</option>
                                @foreach($materiOptions as $mOpt)
                                    <option value="{{ $mOpt->id }}">{{ $mOpt->judul }} ({{ $mOpt->mata_pelajaran }} · {{ $mOpt->kelas }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="modul_{{ $modul->id }}_kuis_id">Tautkan Kuis Eksisting (opsional)</label>
                            <select id="modul_{{ $modul->id }}_kuis_id" name="kuis_id" class="form-select">
                                <option value="">-- Tidak ditautkan --</option>
                                @foreach($kuisOptions as $kOpt)
                                    <option value="{{ $kOpt->id }}">{{ $kOpt->judul }} ({{ $kOpt->mata_pelajaran }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <label style="display:flex;align-items:center;gap:.35rem;font-size:.83rem">
                            <input type="hidden" name="dipublikasikan" value="0">
                            <input type="checkbox" name="dipublikasikan" value="1" checked> Publikasikan ke siswa
                        </label>
                        <button type="submit" class="btn btn-primary btn-sm">Simpan Pertemuan</button>
                    </div>
                </form>
            </details>
        </div>
    </div>
@empty
    <div class="card" style="margin-bottom:1.25rem">
        <div class="card-body" style="text-align:center;color:#64748b">
            Belum ada modul/bab. Tambahkan modul pertama menggunakan formulir di atas.
        </div>
    </div>
@endforelse

{{-- Rekap Progress Siswa --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">Rekap Progress Peserta Kelas ({{ $peserta->count() }} Siswa)</span>
    </div>
    <div class="card-body" style="padding:0">
        @if($peserta->isEmpty())
            <p style="padding:1.25rem;color:#64748b;font-size:.875rem">Belum ada siswa aktif yang cocok dengan rombel/kelas "{{ $kelasDigital->rombel?->nama_rombel ?? $kelasDigital->kelas }}".</p>
        @else
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Peserta Didik</th>
                            <th>NISN</th>
                            <th>Pertemuan Selesai</th>
                            <th>Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($peserta as $i => $s)
                            @php $prog = $progressPerSiswa[$s->id] ?? ['selesai' => 0, 'total' => $totalPertemuan, 'persen' => 0]; @endphp
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td><strong>{{ $s->nama_peserta_didik }}</strong></td>
                                <td>{{ $s->nisn ?: '-' }}</td>
                                <td>{{ $prog['selesai'] }} / {{ $prog['total'] }}</td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:.5rem;min-width:130px">
                                        <div style="flex:1;height:8px;background:#e2e8f0;border-radius:999px;overflow:hidden">
                                            <div style="width:{{ $prog['persen'] }}%;height:100%;background:#10b981"></div>
                                        </div>
                                        <span style="font-size:.78rem;font-weight:700;color:#334155">{{ $prog['persen'] }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
