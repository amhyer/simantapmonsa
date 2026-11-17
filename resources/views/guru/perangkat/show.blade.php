@extends('layouts.app')

@section('title', $perangkat->judul.' - SIMANTAP')
@section('page_title', $perangkat->judul)
@section('page_subtitle', $perangkat->jenisLabel().' • '.$perangkat->mata_pelajaran.' • Kelas '.($perangkat->rombel?->nama_rombel ?? $perangkat->kelas))

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('header_actions')
    <a href="{{ route('guru.perangkat.index') }}" class="btn btn-ghost btn-sm">← Kembali</a>
    <a href="{{ route('guru.perangkat.pdf', $perangkat) }}" target="_blank" class="btn btn-sm">
        <i class="fas fa-file-pdf"></i> Cetak PDF
    </a>
    @if($isOwner)
        <a href="{{ route('guru.perangkat.edit', $perangkat) }}" class="btn btn-ghost btn-sm">
            <i class="fas fa-edit"></i> Ubah
        </a>
    @endif
@endsection

@section('content')
    @if(session('success'))
        <div class="alert alert-ok" style="margin-bottom:14px">{{ session('success') }}</div>
    @endif

    @if(!$isOwner)
        <div class="alert alert-info" style="margin-bottom:14px">
            <i class="fas fa-eye"></i> Mode Baca-Saja (Wali Kelas / Supervisi) — Anda dapat meninjau dokumen dan mencetak PDF tanpa mengubah isi asli guru penyusun.
        </div>
    @endif

    @php $k = $perangkat->konten ?? []; @endphp

    <div class="card" style="margin-bottom:16px">
        <div class="card-header"><strong>Identitas Dokumen</strong></div>
        <div class="card-body">
            <div class="grid grid-3" style="gap:12px;font-size:13px">
                <div><span style="color:#667085">Jenis:</span><br><strong>{{ $perangkat->jenisLabel() }}</strong></div>
                <div><span style="color:#667085">Mata Pelajaran:</span><br><strong>{{ $perangkat->mata_pelajaran }}</strong></div>
                <div><span style="color:#667085">Kelas / Fase:</span><br><strong>{{ $perangkat->rombel?->nama_rombel ?? $perangkat->kelas }}{{ $perangkat->fase ? ' • Fase '.$perangkat->fase : '' }}</strong></div>
                <div><span style="color:#667085">Penyusun:</span><br><strong>{{ $perangkat->guru?->nama_lengkap ?? '-' }}</strong></div>
                <div><span style="color:#667085">Tahun Ajaran / Semester:</span><br><strong>{{ $perangkat->tahun_ajaran ?? '-' }} • {{ $perangkat->semester ?? '-' }}</strong></div>
                <div><span style="color:#667085">Alokasi Waktu:</span><br><strong>{{ $perangkat->alokasi_waktu ?: '-' }}</strong></div>
            </div>
            @if($perangkat->ringkasan)
                <p style="margin:14px 0 0;font-size:13px;color:#344054">{{ $perangkat->ringkasan }}</p>
            @endif
            @if($perangkat->kelasDigital)
                <div style="margin-top:12px">
                    <a href="{{ route('guru.kelas-digital.show', $perangkat->kelasDigital) }}" class="btn btn-ghost btn-sm">
                        <i class="fas fa-chalkboard"></i> Buka Kelas Digital: {{ $perangkat->kelasDigital->nama_kelas }}
                    </a>
                </div>
            @endif
            @if($perangkat->lampiran_path)
                <div style="margin-top:12px">
                    <a href="{{ route('guru.perangkat.lampiran', $perangkat) }}" class="btn btn-ghost btn-sm">
                        <i class="fas fa-paperclip"></i> Unduh Lampiran: {{ $perangkat->lampiran_nama }}
                    </a>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Isi {{ $perangkat->jenisLabel() }}</strong></div>
        <div class="card-body" style="font-size:14px;line-height:1.6">
            @if($perangkat->jenis === 'cp')
                <p><strong>Elemen:</strong> {{ $k['elemen'] ?? '-' }}</p>
                <p><strong>Rumusan Capaian Pembelajaran:</strong><br>{!! nl2br(e($k['capaian_pembelajaran'] ?? '-')) !!}</p>
                <div class="grid grid-2" style="gap:14px;margin-top:12px">
                    <div><strong>Kompetensi Inti:</strong><br>{!! nl2br(e($k['kompetensi_inti'] ?? '-')) !!}</div>
                    <div><strong>Konten / Materi Esensial:</strong><br>{!! nl2br(e($k['konten_materi'] ?? '-')) !!}</div>
                </div>
                @if(!empty($k['catatan_fase']))
                    <p style="margin-top:12px;color:#667085"><em>{{ $k['catatan_fase'] }}</em></p>
                @endif

            @elseif($perangkat->jenis === 'tp')
                <p><strong>Elemen:</strong> {{ $k['elemen'] ?? '-' }} • <strong>Rujukan CP:</strong> {{ $k['capaian_rujukan'] ?? '-' }}</p>
                <div class="table-wrapper" style="margin:12px 0">
                    <table class="table">
                        <thead><tr><th>Kode</th><th>Rumusan Tujuan Pembelajaran</th><th>Materi Pokok</th><th>Alokasi JP</th></tr></thead>
                        <tbody>
                            @forelse($k['daftar_tp'] ?? [] as $row)
                                <tr>
                                    <td><strong>{{ $row['kode'] ?? '-' }}</strong></td>
                                    <td>{{ $row['deskripsi'] ?? '-' }}</td>
                                    <td>{{ $row['materi'] ?? '-' }}</td>
                                    <td>{{ $row['jp'] ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" style="text-align:center;color:#667085">Belum ada baris TP.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p><strong>Indikator Ketercapaian:</strong> {{ $k['indikator_ketercapaian'] ?? '-' }}</p>

            @elseif($perangkat->jenis === 'atp')
                <p><strong>Elemen:</strong> {{ $k['elemen'] ?? '-' }} • <strong>Prasyarat:</strong> {{ $k['prasyarat'] ?? '-' }}</p>
                <div class="table-wrapper" style="margin:12px 0">
                    <table class="table">
                        <thead><tr><th>No</th><th>Kode TP</th><th>Tujuan Pembelajaran</th><th>Materi Pokok</th><th>JP</th><th>Semester</th><th>Dimensi Profil</th></tr></thead>
                        <tbody>
                            @forelse($k['alur_item'] ?? [] as $row)
                                <tr>
                                    <td>{{ $row['urutan'] ?? '-' }}</td>
                                    <td><strong>{{ $row['kode_tp'] ?? '-' }}</strong></td>
                                    <td>{{ $row['tujuan_pembelajaran'] ?? '-' }}</td>
                                    <td>{{ $row['materi_pokok'] ?? '-' }}</td>
                                    <td>{{ $row['alokasi_jp'] ?? '-' }}</td>
                                    <td>{{ $row['semester'] ?? '-' }}</td>
                                    <td>{{ $row['dimensi_profil'] ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" style="text-align:center;color:#667085">Belum ada alur tujuan pembelajaran.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            @elseif($perangkat->jenis === 'prota')
                <div class="table-wrapper" style="margin-bottom:12px">
                    <table class="table">
                        <thead><tr><th>Semester</th><th>Unit</th><th>Materi / Topik Pokok</th><th>Alokasi JP</th><th>Keterangan</th></tr></thead>
                        <tbody>
                            @forelse($k['daftar_unit'] ?? [] as $row)
                                <tr>
                                    <td>{{ $row['semester'] ?? '-' }}</td>
                                    <td><strong>{{ $row['nomor_unit'] ?? '-' }}</strong></td>
                                    <td>{{ $row['materi_topik'] ?? '-' }}</td>
                                    <td>{{ $row['alokasi_jp'] ?? '-' }}</td>
                                    <td>{{ $row['keterangan'] ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" style="text-align:center;color:#667085">Belum ada unit Prota.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if(!empty($k['catatan_tahunan']))
                    <p style="color:#667085">{{ $k['catatan_tahunan'] }}</p>
                @endif

            @elseif($perangkat->jenis === 'promes')
                <p><strong>Bulan Efektif:</strong> {{ $k['bulan_efektif'] ?? '-' }}</p>
                <div class="table-wrapper" style="margin:12px 0">
                    <table class="table">
                        <thead><tr><th>Bulan</th><th>Minggu Ke-</th><th>Materi / Topik</th><th>JP</th><th>Keterangan</th></tr></thead>
                        <tbody>
                            @forelse($k['daftar_minggu'] ?? [] as $row)
                                <tr>
                                    <td><strong>{{ $row['bulan'] ?? '-' }}</strong></td>
                                    <td>{{ $row['minggu_ke'] ?? '-' }}</td>
                                    <td>{{ $row['materi_topik'] ?? '-' }}</td>
                                    <td>{{ $row['alokasi_jp'] ?? '-' }}</td>
                                    <td>{{ $row['keterangan'] ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" style="text-align:center;color:#667085">Belum ada jadwal Promes.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if(!empty($k['catatan_kalender']))
                    <p style="color:#667085">{{ $k['catatan_kalender'] }}</p>
                @endif

            @elseif($perangkat->jenis === 'modul_ajar')
                <p><strong>Tujuan Pembelajaran:</strong><br>{!! nl2br(e($k['tujuan_pembelajaran'] ?? '-')) !!}</p>
                <p><strong>Prinsip Pembelajaran Mendalam:</strong>
                    @if(!empty($k['pm']['berkesadaran'])) <span class="badge badge-ok">Berkesadaran</span> @endif
                    @if(!empty($k['pm']['bermakna'])) <span class="badge badge-ok">Bermakna</span> @endif
                    @if(!empty($k['pm']['menggembirakan'])) <span class="badge badge-ok">Menggembirakan</span> @endif
                </p>
                @if(!empty($k['dimensi']))
                    <p><strong>Dimensi Profil Lulusan:</strong> {{ implode(', ', $k['dimensi']) }}</p>
                @endif
                <div class="grid grid-3" style="gap:12px;margin:12px 0">
                    <div><strong>Memahami:</strong><br>{!! nl2br(e($k['pengalaman']['memahami'] ?? '-')) !!}</div>
                    <div><strong>Mengaplikasi:</strong><br>{!! nl2br(e($k['pengalaman']['mengaplikasi'] ?? '-')) !!}</div>
                    <div><strong>Merefleksi:</strong><br>{!! nl2br(e($k['pengalaman']['merefleksi'] ?? '-')) !!}</div>
                </div>
                <div class="grid grid-3" style="gap:12px;margin:12px 0">
                    <div><strong>Pendahuluan:</strong><br>{!! nl2br(e($k['pendahuluan'] ?? '-')) !!}</div>
                    <div><strong>Kegiatan Inti:</strong><br>{!! nl2br(e($k['kegiatan_inti'] ?? '-')) !!}</div>
                    <div><strong>Penutup:</strong><br>{!! nl2br(e($k['penutup'] ?? '-')) !!}</div>
                </div>
                <div class="grid grid-3" style="gap:12px">
                    <div><strong>Asesmen:</strong><br>{!! nl2br(e($k['asesmen'] ?? '-')) !!}</div>
                    <div><strong>Media &amp; Sumber:</strong><br>{!! nl2br(e($k['media_sumber'] ?? '-')) !!}</div>
                    <div><strong>Refleksi Guru:</strong><br>{!! nl2br(e($k['refleksi_guru'] ?? '-')) !!}</div>
                </div>

            @elseif($perangkat->jenis === 'kkm')
                <div class="grid grid-4" style="gap:12px;margin-bottom:14px">
                    <div>Kompleksitas: <strong>{{ $k['kompleksitas'] ?? '-' }}</strong></div>
                    <div>Daya Dukung: <strong>{{ $k['daya_dukung'] ?? '-' }}</strong></div>
                    <div>Intake: <strong>{{ $k['intake'] ?? '-' }}</strong></div>
                    <div>Nilai KKM/KKTP: <strong style="font-size:18px;color:#0F766E">{{ $k['nilai_kkm'] ?? '-' }}</strong></div>
                </div>
                <ul style="padding-left:18px">
                    <li><strong>Perlu Bimbingan:</strong> {{ $k['interval_perlu_bimbingan'] ?? '-' }}</li>
                    <li><strong>Cukup:</strong> {{ $k['interval_cukup'] ?? '-' }}</li>
                    <li><strong>Baik (Tuntas):</strong> {{ $k['interval_baik'] ?? '-' }}</li>
                    <li><strong>Sangat Baik:</strong> {{ $k['interval_sangat_baik'] ?? '-' }}</li>
                </ul>
                @if(!empty($k['catatan_penilaian']))
                    <p style="color:#667085">{{ $k['catatan_penilaian'] }}</p>
                @endif
            @endif
        </div>
    </div>
@endsection
