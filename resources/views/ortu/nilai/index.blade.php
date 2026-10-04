@extends('layouts.app')
@section('title', 'Perkembangan Nilai - SIMANTAP')
@section('sidebar')
    @include('ortu.partials.sidebar')
@endsection
@section('page_title', 'Perkembangan Nilai')
@section('content')
    @if(isset($anakList) && $anakList->count() > 1)
        <div style="margin-bottom:16px">
            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Pilih Anak</label>
            <select onchange="window.location.href='{{ route('ortu.nilai.index') }}?siswa_id='+this.value" style="padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px;min-width:180px">
                @foreach($anakList as $a)
                    <option value="{{ $a->id }}" {{ $siswa && $a->id == $siswa->id ? 'selected' : '' }}>{{ $a->nama_peserta_didik }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if(!$siswa)
        <div class="card"><div class="card-body"><div class="empty"><b>Belum ada data</b></div></div></div>
    @else
        @php $nilaiService = app(\App\Services\NilaiService::class); $na = $nilaiService->hitungNilaiAkhir($siswa->id); @endphp
        <div class="card">
            <div class="card-header"><h3>Nilai {{ $siswa->nama_peserta_didik }}</h3></div>
            <div class="card-body">
                <div class="stat" style="max-width:300px"><div class="label">Nilai Akhir</div><div class="value">{{ $na['nilai_akhir'] > 0 ? number_format($na['nilai_akhir'], 1) : '—' }}</div></div>
            </div>
        </div>
    @endif
@endsection
