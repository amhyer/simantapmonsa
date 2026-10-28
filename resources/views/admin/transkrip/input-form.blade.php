@extends('layouts.admin-with-tabs')

@section('title', 'Input Nilai Transkrip - SIMANTAP')

@section('page_title', 'Input Nilai: ' . $siswa->nama_peserta_didik)
@section('page_subtitle', 'NISN ' . ($siswa->nisn ?? '-'))

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Nilai Akhir Transkrip</h3>
            <a href="{{ route('admin.transkrip.input') }}" class="btn btn-sm">Kembali</a>
        </div>
        <div class="card-body">
            @if($mapel->isEmpty())
                <p style="color:#667085">Belum ada mapel bertanda masuk transkrip. Atur di Data Referensi &gt; Mapping Rapor.</p>
            @else
                <form action="{{ route('admin.transkrip.input.store', $siswa) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Mata Pelajaran</th>
                                    <th>Kelompok</th>
                                    <th>Nilai Akhir (0-100)</th>
                                    <th>Predikat Tersimpan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($mapel as $m)
                                    <tr>
                                        <td>{{ $m->nama }}</td>
                                        <td>{{ $m->kelompok ?? '-' }}</td>
                                        <td>
                                            <input type="number" name="nilai[{{ $m->id }}]" min="0" max="100" step="0.01"
                                                value="{{ old('nilai.' . $m->id, optional($nilai->get($m->id))->nilai_akhir) }}"
                                                class="form-control" style="width:120px">
                                        </td>
                                        <td>{{ optional($nilai->get($m->id))->predikat ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top:12px">
                        <button type="submit" class="btn btn-sm">Simpan Nilai</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection

