@extends('layouts.app')

@section('title', 'Detail Dimensi TKA - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Detail Dimensi TKA')
@section('page_subtitle', $dimension->nama_dimensi)

@section('content')
    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Informasi Dimensi</h3>
            <div style="display:flex;gap:8px">
                <a href="{{ route('tka.dimensions.edit', $dimension) }}" class="btn btn-sm">Edit</a>
                <a href="{{ route('tka.dimensions.index') }}" class="btn btn-sm">Kembali</a>
            </div>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:150px 1fr;gap:8px 16px;font-size:13px">
                <div style="color:var(--muted)">Kode Dimensi</div>
                <div><strong>{{ $dimension->kode_dimensi }}</strong></div>

                <div style="color:var(--muted)">Nama Dimensi</div>
                <div>{{ $dimension->nama_dimensi }}</div>

                <div style="color:var(--muted)">Kategori</div>
                <div>{{ ucfirst(str_replace('_', ' ', $dimension->kategori ?? '-')) }}</div>

                <div style="color:var(--muted)">Semester</div>
                <div>{{ $dimension->semester->label ?? '-' }}</div>

                <div style="color:var(--muted)">Bobot</div>
                <div>{{ $dimension->bobot ?? '-' }}</div>

                <div style="color:var(--muted)">Threshold Siap</div>
                <div>{{ $dimension->threshold_siap ?? '-' }}</div>

                <div style="color:var(--muted)">Status</div>
                <div>
                    <span class="tag {{ $dimension->aktif ? 'tag-ok' : 'tag-mut' }}">
                        {{ $dimension->aktif ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>

                <div style="color:var(--muted)">Deskripsi</div>
                <div>{{ $dimension->deskripsi ?: '-' }}</div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Rubrik Penilaian</h3>
        </div>
        <div class="card-body tight">
            @php $rubrik = $dimension->rubrik_penilaian ?? []; @endphp
            @if(empty($rubrik))
                <div class="empty-state">
                    <div class="icon">📋</div>
                    <h4>Belum ada rubrik</h4>
                    <p>Dimensi ini belum memiliki rubrik penilaian.</p>
                </div>
            @else
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Level</th>
                                <th>Deskripsi Capaian</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rubrik as $index => $baris)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ is_array($baris) ? ($baris['level'] ?? '-') : $index }}</td>
                                    <td>{{ is_array($baris) ? ($baris['deskripsi'] ?? '-') : $baris }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Item Penilaian ({{ $dimension->assessmentItems->count() }})</h3>
        </div>
        <div class="card-body tight">
            @if($dimension->assessmentItems->isEmpty())
                <div class="empty-state">
                    <div class="icon">🧭</div>
                    <h4>Belum ada item penilaian</h4>
                    <p>Tambahkan item penilaian untuk dimensi ini.</p>
                </div>
            @else
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Indikator</th>
                                <th>Pertanyaan Penilaian</th>
                                <th>Tipe Jawaban</th>
                                <th>Bobot</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($dimension->assessmentItems->sortBy('urutan') as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $item->indikator ?? '-' }}</td>
                                    <td>{{ $item->pertanyaan_penilaian ?? '-' }}</td>
                                    <td><span class="tag tag-mut">{{ str_replace('_', ' ', $item->tipe_jawaban ?? '-') }}</span></td>
                                    <td>{{ $item->bobot_item ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
