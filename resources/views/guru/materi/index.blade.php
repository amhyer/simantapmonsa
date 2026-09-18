@extends('layouts.app')

@section('title', 'Materi Ajar - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Materi Ajar')
@section('page_subtitle', 'Kelola materi pembelajaran mendalam')

@section('header_actions')
    <a href="{{ route('guru.materi.create') }}" class="btn btn-sm">+ Tambah materi</a>
@endsection

@section('content')
    @if($materi->count())
        <div class="grid grid-2">
            @foreach($materi as $m)
                @php
                    $dimensiList = ['Keimanan dan Ketakwaan', 'Kewargaan', 'Penalaran Kritis', 'Kreativitas', 'Kolaborasi', 'Kemandirian', 'Kesehatan', 'Komunikasi'];
                    $pmLabels = ['berkesadaran' => 'Berkesadaran', 'bermakna' => 'Bermakna', 'menggembirakan' => 'Menggembirakan'];
                @endphp
                <div class="card">
                    <div class="card-header">
                        <h3>{{ $m->judul }}</h3>
                        <span class="tag tag-gold">{{ $m->mata_pelajaran }}</span>
                    </div>
                    <div class="card-body">
                        <div style="font-size:12.5px;color:#667085;margin-bottom:10px">
                            {{ \Carbon\Carbon::parse($m->tanggal)->translatedFormat('l, d F Y') }} · Kelas {{ $m->kelas }}
                        </div>

                        @if($m->pm)
                            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">
                                @foreach($pmLabels as $key => $label)
                                    @if($m->pm[$key] ?? false)
                                        <span class="tag tag-gold">{{ $label }}</span>
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        @if($m->dimensi)
                            <div style="margin-bottom:12px">
                                <div style="font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085;font-weight:700;margin-bottom:6px">Dimensi Profil Lulusan</div>
                                <div style="display:flex;gap:6px;flex-wrap:wrap">
                                    @foreach($m->dimensi as $d)
                                        <span class="tag tag-gold">{{ $dimensiList[$d] ?? $d }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="note" style="margin-bottom:12px">
                            <b>Tujuan pembelajaran</b><br>{{ $m->tujuan_pembelajaran }}
                        </div>

                        <div style="font-size:14px;color:#344054;max-height:110px;overflow:hidden;position:relative">
                            {{ Str::limit($m->materi_pokok, 200) }}
                        </div>

                        <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap">
                            <a href="{{ route('guru.materi.edit', $m) }}" class="btn btn-ghost btn-sm">Ubah</a>
                            <button onclick="hapusMateri('{{ $m->id }}')" class="btn btn-ghost btn-sm" style="color:#B42318">Hapus</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="card">
            <div class="card-body">
                <div class="empty">
                    <div class="icon">📚</div>
                    <b>Belum ada materi ajar</b>
                    <div>Susun materi agar siswa dapat membacanya.</div>
                    <div style="margin-top:14px"><a href="{{ route('guru.materi.create') }}" class="btn">+ Tambah materi</a></div>
                </div>
            </div>
        </div>
    @endif

    <script>
        function hapusMateri(id) {
            if (confirm('Apakah yakin ingin menghapus materi ini?')) {
                fetch(`/guru/materi/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                }).then(() => location.reload());
            }
        }
    </script>
@endsection
