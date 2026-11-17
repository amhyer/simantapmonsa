@extends('layouts.app')

@section('title', 'Pengumpulan Tugas — ' . $tugas->judul)
@section('sidebar') @include('guru.partials.sidebar') @endsection

@section('content')
<div class="page-header">
    <div>
        <div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:.3rem">
            <span class="badge badge-blue">{{ $kelasDigital->mata_pelajaran }}</span>
            <span class="badge badge-gray">{{ $kelasDigital->rombel?->nama_rombel ?? $kelasDigital->kelas }}</span>
            @if($tugas->sinkron_ke_nilai)
                <span class="badge badge-green">Sinkron ke Daftar Nilai (Tugas)</span>
            @endif
        </div>
        <h1 class="page-title">{{ $tugas->judul }}</h1>
        <p class="page-subtitle">
            {{ $tugas->pertemuan?->modul?->judul }} · {{ $tugas->pertemuan?->judul }}
            @if($tugas->batas_waktu) · Tenggat: {{ $tugas->batas_waktu->format('d M Y H:i') }} @endif
        </p>
    </div>
    <div class="page-actions" style="display:flex;gap:.5rem;flex-wrap:wrap">
        @if($tugas->lampiran_path)
            <a href="{{ route('guru.kelas-digital.tugas.download', [$kelasDigital, $tugas]) }}" class="btn btn-outline btn-sm">Unduh Lampiran Soal</a>
        @endif
        <a href="{{ route('guru.kelas-digital.show', $kelasDigital) }}" class="btn btn-outline btn-sm">Kembali ke Kelas</a>
    </div>
</div>

<div class="card" style="margin-bottom:1.25rem">
    <div class="card-body">
        <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;color:#64748b;margin-bottom:.35rem">Instruksi Tugas</div>
        <div style="white-space:pre-line;font-size:.9rem;color:#1e293b;line-height:1.55">{{ $tugas->instruksi }}</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Daftar Pengumpulan &amp; Penilaian (Nilai Maksimal: {{ $tugas->nilai_maksimal }})</span>
    </div>
    <div class="card-body" style="padding:0">
        @if($peserta->isEmpty() && $pengumpulanTambahan->isEmpty())
            <p style="padding:1.25rem;color:#64748b;font-size:.875rem">Belum ada siswa pada kelas ini maupun pengumpulan tugas.</p>
        @else
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Peserta Didik</th>
                            <th>Status</th>
                            <th>Jawaban &amp; Berkas</th>
                            <th>Penilaian Guru</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $rowNo = 1; @endphp
                        @foreach($peserta as $s)
                            @php $sub = $pengumpulanMap->get($s->id); @endphp
                            <tr>
                                <td>{{ $rowNo++ }}</td>
                                <td>
                                    <strong>{{ $s->nama_peserta_didik }}</strong>
                                    <div style="font-size:.75rem;color:#64748b">{{ $s->nisn ?: 'Tanpa NISN' }}</div>
                                </td>
                                <td>
                                    @if(!$sub)
                                        <span class="badge badge-gray">Belum Mengumpulkan</span>
                                    @elseif($sub->status === 'dinilai')
                                        <span class="badge badge-green">Dinilai ({{ number_format((float) $sub->nilai, 1) }})</span>
                                    @elseif($sub->status === 'terlambat')
                                        <span class="badge badge-amber">Terlambat</span>
                                    @elseif($sub->status === 'revisi')
                                        <span class="badge badge-red">Perlu Revisi</span>
                                    @else
                                        <span class="badge badge-blue">Dikumpulkan</span>
                                    @endif
                                    @if($sub?->dikumpulkan_pada)
                                        <div style="font-size:.73rem;color:#64748b;margin-top:.2rem">{{ $sub->dikumpulkan_pada->format('d M Y H:i') }}</div>
                                    @endif
                                </td>
                                <td style="max-width:300px">
                                    @if($sub)
                                        @if($sub->jawaban_teks)
                                            <div style="font-size:.82rem;color:#1e293b;white-space:pre-line;margin-bottom:.35rem">{{ \Illuminate\Support\Str::limit($sub->jawaban_teks, 250) }}</div>
                                        @endif
                                        @if($sub->file_path)
                                            <a href="{{ route('guru.kelas-digital.pengumpulan.download', [$kelasDigital, $sub]) }}" class="btn btn-outline btn-sm">
                                                Unduh: {{ \Illuminate\Support\Str::limit($sub->file_nama_asli, 32) }}
                                            </a>
                                        @endif
                                    @else
                                        <span style="font-size:.8rem;color:#94a3b8">-</span>
                                    @endif
                                </td>
                                <td style="min-width:260px">
                                    @if($sub)
                                        <form method="POST" action="{{ route('guru.kelas-digital.tugas.nilai', [$kelasDigital, $tugas, $sub]) }}" style="display:flex;flex-direction:column;gap:.4rem">
                                            @csrf
                                            <div style="display:flex;gap:.4rem">
                                                <input type="number" step="0.01" min="0" max="{{ $tugas->nilai_maksimal }}" name="nilai" value="{{ old('nilai', $sub->nilai) }}" class="form-input" style="width:95px" placeholder="Nilai" required>
                                                <select name="status" class="form-select" style="flex:1">
                                                    <option value="dinilai" @selected($sub->status !== 'revisi')>Dinilai</option>
                                                    <option value="revisi" @selected($sub->status === 'revisi')>Minta Revisi</option>
                                                </select>
                                            </div>
                                            <input type="text" name="catatan_guru" value="{{ old('catatan_guru', $sub->catatan_guru) }}" class="form-input" placeholder="Catatan umpan balik untuk siswa...">
                                            <button type="submit" class="btn btn-primary btn-sm">Simpan Nilai</button>
                                        </form>
                                    @else
                                        <span style="font-size:.8rem;color:#94a3b8">Menunggu pengumpulan siswa</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach

                        @foreach($pengumpulanTambahan as $sub)
                            <tr>
                                <td>{{ $rowNo++ }}</td>
                                <td><strong>{{ $sub->siswa?->nama_peserta_didik ?? 'Siswa #' . $sub->siswa_id }}</strong></td>
                                <td><span class="badge badge-blue">{{ ucfirst($sub->status) }}</span></td>
                                <td>
                                    @if($sub->jawaban_teks)
                                        <div style="font-size:.82rem;white-space:pre-line">{{ \Illuminate\Support\Str::limit($sub->jawaban_teks, 250) }}</div>
                                    @endif
                                    @if($sub->file_path)
                                        <a href="{{ route('guru.kelas-digital.pengumpulan.download', [$kelasDigital, $sub]) }}" class="btn btn-outline btn-sm">Unduh Berkas</a>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('guru.kelas-digital.tugas.nilai', [$kelasDigital, $tugas, $sub]) }}" style="display:flex;flex-direction:column;gap:.4rem">
                                        @csrf
                                        <div style="display:flex;gap:.4rem">
                                            <input type="number" step="0.01" min="0" max="{{ $tugas->nilai_maksimal }}" name="nilai" value="{{ $sub->nilai }}" class="form-input" style="width:95px" required>
                                            <select name="status" class="form-select" style="flex:1">
                                                <option value="dinilai" @selected($sub->status !== 'revisi')>Dinilai</option>
                                                <option value="revisi" @selected($sub->status === 'revisi')>Minta Revisi</option>
                                            </select>
                                        </div>
                                        <input type="text" name="catatan_guru" value="{{ $sub->catatan_guru }}" class="form-input" placeholder="Catatan guru...">
                                        <button type="submit" class="btn btn-primary btn-sm">Simpan Nilai</button>
                                    </form>
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
