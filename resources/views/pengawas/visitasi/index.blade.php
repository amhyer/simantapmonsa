@extends('layouts.app')

@section('title', 'E-Visitasi & Observasi Kelas Pembelajaran Mendalam — Pengawas Sekolah')
@section('page_title', 'E-Visitasi & Instrumen Observasi Kelas')
@section('page_subtitle', 'Pencatatan pendampingan, rubrik observasi Pembelajaran Mendalam (Mindful, Meaningful, Joyful) & Berita Acara PDF')

@section('sidebar')
    @include('pengawas.partials.sidebar')
@endsection

@section('content')
    {{-- Form Tambah Berita Acara Visitasi & Instrumen Observasi Kelas --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <strong><i class="fas fa-plus-circle"></i> Catat Visitasi / Observasi Kelas Baru</strong>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('pengawas.visitasi.store') }}" style="display:grid;gap:14px">
                @csrf
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
                    <div>
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Tahun Ajaran *</label>
                        <input type="text" name="tahun_ajaran" value="{{ $tahunAjaran }}" required
                               style="width:100%;padding:8px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Semester *</label>
                        <select name="semester" required style="width:100%;padding:8px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                            <option value="1" {{ $semester === 1 ? 'selected' : '' }}>Semester 1 (Ganjil)</option>
                            <option value="2" {{ $semester === 2 ? 'selected' : '' }}>Semester 2 (Genap)</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Tanggal Visitasi *</label>
                        <input type="date" name="tanggal_visitasi" value="{{ old('tanggal_visitasi', now()->toDateString()) }}" required
                               style="width:100%;padding:8px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Jenis Pendampingan *</label>
                        <select name="jenis_pendampingan" required style="width:100%;padding:8px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                            @foreach($jenisLabels as $kode => $lbl)
                                <option value="{{ $kode }}">{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Guru Diobservasi (Opsional)</label>
                        <select name="guru_id" style="width:100%;padding:8px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                            <option value="">— Seluruh Satuan Pendidikan (Manajerial) —</option>
                            @foreach($guruList as $g)
                                <option value="{{ $g->id }}">{{ $g->nama_lengkap }} {{ $g->kelas_mata_pelajaran ? '(' . $g->kelas_mata_pelajaran . ')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Rubrik 6 Indikator Pembelajaran Mendalam (Skala 1-4) --}}
                <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:10px;padding:14px">
                    <div style="font-size:12px;font-weight:700;color:#1E293B;margin-bottom:10px">
                        <i class="fas fa-star"></i> RUBRIK INSTRUMEN OBSERVASI PEMBELAJARAN MENDALAM (SKALA 1–4)
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:10px">
                        @foreach([
                            'skor_berkesadaran' => '1. Prinsip Berkesadaran (Mindful)',
                            'skor_bermakna' => '2. Prinsip Bermakna (Meaningful)',
                            'skor_menggembirakan' => '3. Prinsip Menggembirakan (Joyful)',
                            'skor_memahami' => '4. Pengalaman Belajar: Memahami',
                            'skor_mengaplikasi' => '5. Pengalaman Belajar: Mengaplikasi',
                            'skor_merefleksi' => '6. Pengalaman Belajar: Merefleksi',
                        ] as $field => $judul)
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">{{ $judul }}</label>
                                <select name="{{ $field }}" required style="width:100%;padding:7px 10px;border:1px solid #CBD5E1;border-radius:8px;font-size:12.5px">
                                    @foreach($skalaRubrik as $nilai => $ket)
                                        <option value="{{ $nilai }}" {{ $nilai === 3 ? 'selected' : '' }}>{{ $ket }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px">
                    <div>
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Temuan Lapangan / Praktik Baik *</label>
                        <textarea name="temuan" rows="3" required placeholder="Catat hasil pengamatan kelas, keterlibatan murid, atau tata kelola sekolah..."
                                  style="width:100%;padding:8px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">{{ old('temuan') }}</textarea>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Rekomendasi Pembinaan Pengawas *</label>
                        <textarea name="rekomendasi" rows="3" required placeholder="Saran penguatan strategi pembelajaran mendalam atau asesmen..."
                                  style="width:100%;padding:8px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">{{ old('rekomendasi') }}</textarea>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Rencana Tindak Lanjut (RTL)</label>
                        <textarea name="tindak_lanjut" rows="3" placeholder="Kesepakatan tindak lanjut bersama Guru & Kepala Sekolah..."
                                  style="width:100%;padding:8px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">{{ old('tindak_lanjut') }}</textarea>
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end">
                    <button type="submit" class="btn btn-sm"><i class="fas fa-save"></i> Simpan Berita Acara Visitasi</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Daftar Riwayat Visitasi --}}
    <div class="card">
        <div class="card-header">
            <strong>Daftar Berita Acara Visitasi &amp; Observasi Kelas ({{ $visitasiList->total() }})</strong>
        </div>
        <div class="card-body" style="padding:0">
            @if($visitasiList->isEmpty())
                <div style="padding:32px;text-align:center;color:#667085">Belum ada catatan visitasi pada periode ini.</div>
            @else
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Jenis Pendampingan</th>
                                <th>Sasaran / Guru</th>
                                <th style="text-align:center">Skor Rubrik PM</th>
                                <th>Temuan &amp; Rekomendasi</th>
                                <th style="text-align:right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($visitasiList as $v)
                                <tr>
                                    <td style="white-space:nowrap">{{ $v->tanggal_visitasi->format('d/m/Y') }}</td>
                                    <td>{{ $v->jenisLabel() }}</td>
                                    <td style="font-weight:600">{{ $v->guru?->nama_lengkap ?? 'Satuan Pendidikan (Manajerial)' }}</td>
                                    <td style="text-align:center">
                                        <div style="font-weight:700;color:#175CD3">{{ $v->rataRataSkor() }} / 4.00</div>
                                        <div style="font-size:11px;color:#667085">{{ $v->predikatObservasi() }}</div>
                                    </td>
                                    <td style="font-size:12px;max-width:340px">
                                        <div><strong>Temuan:</strong> {{ \Illuminate\Support\Str::limit($v->temuan, 110) }}</div>
                                        <div style="color:#475467;margin-top:2px"><strong>Rekomendasi:</strong> {{ \Illuminate\Support\Str::limit($v->rekomendasi, 110) }}</div>
                                    </td>
                                    <td style="text-align:right;white-space:nowrap">
                                        <a href="{{ route('pengawas.visitasi.pdf', $v->uuid) }}" target="_blank" class="btn btn-sm btn-ghost">
                                            <i class="fas fa-file-pdf"></i> Berita Acara PDF
                                        </a>
                                        <form method="POST" action="{{ route('pengawas.visitasi.destroy', $v->uuid) }}" style="display:inline"
                                              onsubmit="return confirm('Hapus catatan visitasi ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-ghost" style="color:#B42318">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="padding:12px 16px">{{ $visitasiList->links() }}</div>
            @endif
        </div>
    </div>
@endsection
