@extends('layouts.admin-with-tabs')
@section('title', 'Teknik Penilaian - SIMANTAP')
@section('page_title', 'Teknik Penilaian')
@section('page_subtitle', 'Kelola daftar teknik penilaian yang digunakan pada asesmen Kurikulum Merdeka')

@section('content')
    <div class="card" style="max-width:720px">
        <div class="card-header">
            <h3>Daftar Teknik Penilaian Aktif</h3>
            <button type="button" class="btn btn-sm btn-secondary" onclick="tambahTeknikRow()">
                <i class="fas fa-plus"></i> Tambah Teknik
            </button>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.rapor.teknik-penilaian.simpan') }}">
                @csrf
                <div id="teknikList" style="display:flex;flex-direction:column;gap:10px;margin-bottom:18px">
                    @foreach($teknik as $idx => $nama)
                        <div class="teknik-row" style="display:flex;gap:8px;align-items:center">
                            <input type="text" name="teknik[]" value="{{ $nama }}" class="form-control" maxlength="80" required placeholder="Nama teknik penilaian...">
                            <button type="button" class="btn btn-sm btn-ghost" style="color:var(--bad)" onclick="this.closest('.teknik-row').remove()" title="Hapus">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    @endforeach
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Teknik Penilaian
                </button>
            </form>
        </div>
    </div>

    <script>
    function tambahTeknikRow() {
        const list = document.getElementById('teknikList');
        const row = document.createElement('div');
        row.className = 'teknik-row';
        row.style.cssText = 'display:flex;gap:8px;align-items:center';
        row.innerHTML = `
            <input type="text" name="teknik[]" class="form-control" maxlength="80" required placeholder="Nama teknik penilaian baru...">
            <button type="button" class="btn btn-sm btn-ghost" style="color:var(--bad)" onclick="this.closest('.teknik-row').remove()" title="Hapus">
                <i class="fas fa-trash"></i>
            </button>
        `;
        list.appendChild(row);
    }
    </script>
@endsection
