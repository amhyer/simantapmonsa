@extends('layouts.app')

@section('title', 'Anggota Kelompok - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Anggota: ' . $kelompok->nama)
@section('page_subtitle', ($kelompok->kegiatan?->nama ?? '-') . ' · ' . ($kelompok->kegiatan?->tema?->nama ?? '-'))

@section('header_actions')
    <a href="{{ route('admin.kokurikuler.kelompok') }}" class="btn btn-sm btn-ghost">← Kembali</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Pilih Anggota ({{ count($anggotaIds) }} terpilih)</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.kokurikuler.anggota.update', $kelompok) }}" method="POST">
                @csrf
                @method('PUT')
                <div style="margin-bottom:12px">
                    <input type="text" id="searchAnggota" placeholder="Cari nama atau NIS..." style="padding:6px 12px;border:1px solid #E4E7EC;border-radius:8px;width:240px" oninput="filterAnggota(this.value)">
                </div>
                <div id="daftarAnggota" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:8px;max-height:420px;overflow-y:auto;border:1px solid #E4E7EC;border-radius:8px;padding:12px">
                    @foreach($siswa as $s)
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px;padding:6px 8px;border-radius:6px;cursor:pointer" onmouseover="this.style.background='#F6F8FB'" onmouseout="this.style.background=''">
                            <input type="checkbox" name="anggota[]" value="{{ $s->id }}" {{ in_array($s->id, $anggotaIds) ? 'checked' : '' }}>
                            <span><b>{{ $s->nama_peserta_didik }}</b> <span style="color:#667085">({{ $s->nis }} · {{ $s->kelas }})</span></span>
                        </label>
                    @endforeach
                </div>
                <div style="margin-top:12px">
                    <button type="submit" class="btn btn-sm">Simpan Anggota</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function filterAnggota(keyword) {
            keyword = keyword.toLowerCase();
            document.querySelectorAll('#daftarAnggota label').forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().includes(keyword) ? '' : 'none';
            });
        }
    </script>
@endsection
