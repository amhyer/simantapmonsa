@extends('layouts.app')

@section('title', 'Input Nilai Transkrip - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Input Nilai: ' . $siswa->nama_peserta_didik)
@section('page_subtitle', 'NISN ' . ($siswa->nisn ?? '-'))

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Nilai Akhir Transkrip</h3>
            <a href="{{ route('admin.transkrip.input') }}" class="btn btn-sm">Kembali</a>
        </div>
        <div class="card-body">
            @if($errors->any())
                <div style="background:#FDECEC;border:1px solid #F5C6C6;color:#B42318;padding:10px 14px;border-radius:8px;margin-bottom:12px">
                    <ul style="margin:0;padding-left:18px">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if($mapel->isEmpty())
                <p style="color:#667085">Belum ada mapel bertanda masuk transkrip. Atur di Data Referensi &gt; Mapping Rapor.</p>
            @else
                <form action="{{ route('admin.transkrip.input.store', $siswa) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div style="overflow-x:auto">
                        <table style="width:100%;border-collapse:collapse;font-size:14px">
                            <thead>
                                <tr style="text-align:left;border-bottom:2px solid #EAECF0">
                                    <th style="padding:10px">Mata Pelajaran</th>
                                    <th style="padding:10px">Kelompok</th>
                                    <th style="padding:10px">Nilai Akhir (0-100)</th>
                                    <th style="padding:10px">Predikat Tersimpan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($mapel as $m)
                                    <tr style="border-bottom:1px solid #EAECF0">
                                        <td style="padding:10px">{{ $m->nama }}</td>
                                        <td style="padding:10px">{{ $m->kelompok ?? '-' }}</td>
                                        <td style="padding:10px">
                                            <input type="number" name="nilai[{{ $m->id }}]" min="0" max="100" step="0.01"
                                                value="{{ old('nilai.' . $m->id, optional($nilai->get($m->id))->nilai_akhir) }}"
                                                style="width:120px;padding:6px 10px;border:1px solid #E4E7EC;border-radius:8px">
                                        </td>
                                        <td style="padding:10px">{{ optional($nilai->get($m->id))->predikat ?? '-' }}</td>
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
