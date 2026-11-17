@extends('layouts.app')

@section('title', $pertemuan->judul)
@section('sidebar') @include('siswa.partials.sidebar') @endsection

@php $sudahSelesai = (bool) ($progress?->selesai ?? false); @endphp

@section('content')
<div class="page-header">
    <div>
        <div style="display:flex;gap:.35rem;flex-wrap:wrap;margin-bottom:.3rem">
            <span class="badge badge-purple">{{ $kelasDigital->mata_pelajaran }}</span>
            <span class="badge badge-gray">{{ $pertemuan->modul->judul }}</span>
            @if($sudahSelesai)
                <span class="badge badge-green">Selesai Dibaca</span>
            @endif
        </div>
        <h1 class="page-title">{{ $pertemuan->judul }}</h1>
        @if($pertemuan->deskripsi)
            <p class="page-subtitle">{{ $pertemuan->deskripsi }}</p>
        @endif
    </div>
    <div class="page-actions" style="display:flex;gap:.5rem;flex-wrap:wrap">
        <a href="{{ route('siswa.kelas-digital.show', $kelasDigital) }}" class="btn btn-outline btn-sm">Kembali ke Modul</a>
        <form method="POST" action="{{ route('siswa.kelas-digital.pertemuan.selesai', [$kelasDigital, $pertemuan]) }}">
            @csrf
            <input type="hidden" name="selesai" value="{{ $sudahSelesai ? '0' : '1' }}">
            <button type="submit" class="btn {{ $sudahSelesai ? 'btn-outline' : 'btn-primary' }} btn-sm">
                {{ $sudahSelesai ? 'Batalkan Tanda Selesai' : 'Tandai Selesai Dibaca' }}
            </button>
        </form>
    </div>
</div>

{{-- Materi Ajar Eksisting yang Ditautkan --}}
@if($pertemuan->materi)
    <div class="card" style="margin-bottom:1.25rem;border-left:4px solid #7c3aed">
        <div class="card-header">
            <span class="card-title">Materi Ajar Terhubung: {{ $pertemuan->materi->judul }}</span>
        </div>
        <div class="card-body">
            @if($pertemuan->materi->tujuan_pembelajaran)
                <div style="font-size:.84rem;color:#475569;margin-bottom:.75rem"><strong>Tujuan Pembelajaran:</strong> {{ $pertemuan->materi->tujuan_pembelajaran }}</div>
            @endif
            @if($pertemuan->materi->materi_pokok)
                <div style="white-space:pre-line;font-size:.9rem;line-height:1.65;color:#1e293b;margin-bottom:.65rem">{{ $pertemuan->materi->materi_pokok }}</div>
            @endif
            @if($pertemuan->materi->kegiatan)
                <div style="white-space:pre-line;font-size:.86rem;line-height:1.55;color:#475569"><strong>Kegiatan Belajar:</strong> {{ $pertemuan->materi->kegiatan }}</div>
            @endif
        </div>
    </div>
@endif

{{-- Konten Butir Materi --}}
@if($pertemuan->items->isNotEmpty())
    <div style="display:flex;flex-direction:column;gap:1rem;margin-bottom:1.25rem">
        @foreach($pertemuan->items as $item)
            <div class="card">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:.5rem">
                    <span class="card-title">{{ $item->judul }}</span>
                    <span class="badge badge-gray">{{ strtoupper($item->tipe) }}</span>
                </div>
                <div class="card-body">
                    @if($item->tipe === 'teks' && $item->konten)
                        <div style="white-space:pre-line;font-size:.92rem;line-height:1.65;color:#1e293b">{{ $item->konten }}</div>
                    @elseif($item->tipe === 'video')
                        @php $embed = $item->youtubeEmbedUrl(); @endphp
                        @if($embed)
                            <div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:10px;background:#0f172a;margin-bottom:.5rem">
                                <iframe src="{{ $embed }}" title="{{ $item->judul }}" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0" allowfullscreen loading="lazy"></iframe>
                            </div>
                        @endif
                        @if($item->tautan)
                            <a href="{{ $item->tautan }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm">Buka Video di Tab Baru</a>
                        @endif
                    @elseif($item->tipe === 'file' && $item->file_path)
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:.75rem;flex-wrap:wrap">
                            <div>
                                <strong style="font-size:.9rem;color:#0f172a">{{ $item->file_nama_asli }}</strong>
                                @if($item->file_ukuran)
                                    <div style="font-size:.76rem;color:#64748b">{{ number_format($item->file_ukuran / 1024, 1) }} KB</div>
                                @endif
                            </div>
                            <a href="{{ route('siswa.kelas-digital.item.download', [$kelasDigital, $item]) }}" class="btn btn-primary btn-sm">Unduh Berkas</a>
                        </div>
                    @elseif($item->tipe === 'link' && $item->tautan)
                        @if($item->konten)
                            <p style="font-size:.88rem;color:#475569;margin-bottom:.6rem">{{ $item->konten }}</p>
                        @endif
                        <a href="{{ $item->tautan }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm">Buka Sumber Belajar</a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif

{{-- Kuis Terhubung (Pakai Ulang Mesin Kuis Eksisting) --}}
@if($pertemuan->kuis)
    <div class="card" style="margin-bottom:1.25rem;background:#fffbeb;border:1px solid #fde68a">
        <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
            <div>
                <span class="badge badge-amber">Kuis Pertemuan</span>
                <h3 style="font-size:1rem;font-weight:700;color:#78350f;margin:.35rem 0 .2rem">{{ $pertemuan->kuis->judul }}</h3>
                @if($hasilKuis)
                    <p style="font-size:.84rem;color:#15803d;margin:0">Sudah dikerjakan · Skor terakhir: <strong>{{ $hasilKuis->skor }}</strong></p>
                @else
                    <p style="font-size:.84rem;color:#92400e;margin:0">Kerjakan kuis untuk mengukur pemahamanmu pada pertemuan ini.</p>
                @endif
            </div>
            @if(\Illuminate\Support\Facades\Route::has('siswa.kuis.kerjakan') && $pertemuan->kuis->aktif)
                <a href="{{ route('siswa.kuis.kerjakan', $pertemuan->kuis->id) }}" class="btn btn-primary btn-sm">
                    {{ $hasilKuis ? 'Lihat / Kerjakan Kuis' : 'Kerjakan Kuis Sekarang' }}
                </a>
            @endif
        </div>
    </div>
@endif

{{-- Tugas Pertemuan --}}
@if($pertemuan->tugas->isNotEmpty())
    <h2 style="font-size:1.05rem;font-weight:700;color:#0f172a;margin:1.25rem 0 .75rem">Tugas Pertemuan</h2>
    @foreach($pertemuan->tugas as $tugas)
        @php $sub = $pengumpulanMap->get($tugas->id); @endphp
        <div class="card" style="margin-bottom:1rem">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:flex-start;gap:.75rem;flex-wrap:wrap">
                <div>
                    <h3 class="card-title" style="margin:0">{{ $tugas->judul }}</h3>
                    <div style="font-size:.78rem;color:#64748b;margin-top:.2rem">
                        Nilai Maksimal: {{ $tugas->nilai_maksimal }}
                        @if($tugas->batas_waktu) · Tenggat: <strong>{{ $tugas->batas_waktu->format('d M Y H:i') }}</strong> @endif
                    </div>
                </div>
                @if($sub)
                    <span class="badge {{ $sub->status === 'dinilai' ? 'badge-green' : ($sub->status === 'revisi' ? 'badge-red' : ($sub->status === 'terlambat' ? 'badge-amber' : 'badge-blue')) }}">
                        @if($sub->status === 'dinilai')
                            Dinilai: {{ number_format((float) $sub->nilai, 1) }}
                        @elseif($sub->status === 'revisi')
                            Perlu Revisi
                        @elseif($sub->status === 'terlambat')
                            Dikumpulkan (Terlambat)
                        @else
                            Sudah Dikumpulkan
                        @endif
                    </span>
                @else
                    <span class="badge badge-gray">Belum Dikumpulkan</span>
                @endif
            </div>

            <div class="card-body">
                <div style="white-space:pre-line;font-size:.9rem;color:#1e293b;margin-bottom:.75rem">{{ $tugas->instruksi }}</div>

                @if($tugas->lampiran_path)
                    <div style="margin-bottom:.85rem">
                        <a href="{{ route('siswa.kelas-digital.tugas.download', [$kelasDigital, $tugas]) }}" class="btn btn-outline btn-sm">
                            Unduh Lampiran Soal ({{ $tugas->lampiran_nama }})
                        </a>
                    </div>
                @endif

                @if($sub && ($sub->nilai !== null || $sub->catatan_guru))
                    <div style="padding:.75rem 1rem;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;margin-bottom:.85rem;font-size:.85rem">
                        @if($sub->nilai !== null)
                            <div><strong>Nilai Kamu:</strong> {{ number_format((float) $sub->nilai, 1) }} / {{ $tugas->nilai_maksimal }}</div>
                        @endif
                        @if($sub->catatan_guru)
                            <div style="margin-top:.25rem"><strong>Catatan Guru:</strong> {{ $sub->catatan_guru }}</div>
                        @endif
                    </div>
                @endif

                @if($sub && $sub->file_path)
                    <div style="margin-bottom:.75rem;font-size:.82rem;color:#475569">
                        Berkas terkirim:
                        <a href="{{ route('siswa.kelas-digital.pengumpulan.download', [$kelasDigital, $sub]) }}" style="color:#6d28d9;font-weight:600">
                            {{ $sub->file_nama_asli }}
                        </a>
                    </div>
                @endif

                <form method="POST" action="{{ route('siswa.kelas-digital.tugas.kumpul', [$kelasDigital, $tugas]) }}" enctype="multipart/form-data">
                    @csrf
                    @if(in_array($tugas->tipe_pengumpulan, ['teks', 'keduanya'], true))
                        <div class="form-group">
                            <label class="form-label">Jawaban Teks</label>
                            <textarea name="jawaban_teks" rows="3" class="form-textarea" placeholder="Tulis jawabanmu di sini...">{{ old('jawaban_teks', $sub?->jawaban_teks) }}</textarea>
                        </div>
                    @endif

                    @if(in_array($tugas->tipe_pengumpulan, ['file', 'keduanya'], true))
                        <div class="form-group">
                            <label class="form-label">Unggah Berkas Jawaban (Maks 5 MB: PDF, Dokumen, Gambar, Audio/Video)</label>
                            <input type="file" name="file" class="form-input">
                        </div>
                    @endif

                    <button type="submit" class="btn btn-primary btn-sm">
                        {{ $sub ? 'Perbarui Pengumpulan Tugas' : 'Kumpulkan Tugas' }}
                    </button>
                </form>
            </div>
        </div>
    @endforeach
@endif
@endsection
