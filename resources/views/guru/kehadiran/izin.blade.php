@extends('layouts.app')

@section('title', 'Pengajuan Izin Siswa')
@section('sidebar') @include('guru.partials.sidebar') @endsection
@section('page_title', 'Pengajuan Izin Siswa')
@section('page_subtitle', 'Verifikasi izin dan sakit siswa yang menjadi tanggung jawab wali kelas')

@section('content')
    <div class="card">
        <div class="card-header"><h3>Pengajuan orang tua</h3><a href="{{ route('guru.kehadiran.index') }}" class="btn btn-ghost btn-sm">Kembali</a></div>
        <div class="card-body">
            <p>Pengajuan yang belum diperiksa tidak otomatis menjadi izin, sakit, atau alpa. Maksimal 100 pengajuan terbaru ditampilkan.</p>
            @if($errors->any()) <div class="alert alert-warning" role="alert">{{ $errors->first() }}</div> @endif
            <div class="table-wrapper"><table>
                <thead><tr><th>Siswa</th><th>Tanggal</th><th>Jenis / alasan</th><th>Status</th><th>Verifikasi</th></tr></thead>
                <tbody>
                @forelse($izin as $item)
                    <tr>
                        <td>{{ $item->siswa?->nama_peserta_didik ?? '—' }}</td>
                        <td>{{ $item->tanggal->format('d/m/Y') }}</td>
                        <td>{{ $item->jenis === 'S' ? 'Sakit' : 'Izin' }}<br>{{ $item->alasan }}</td>
                        <td>{{ ucfirst($item->status) }}<br>{{ $item->catatan_pemeriksa ?? '' }}</td>
                        <td>
                            @if($item->status === 'menunggu')
                                <form method="POST" action="{{ route('guru.kehadiran.izin.review', $item->id) }}" style="display:grid;gap:5px;min-width:190px">
                                    @csrf
                                    <label>Catatan <input name="catatan" maxlength="500" placeholder="Wajib jika ditolak"></label>
                                    <div><button class="btn btn-sm" name="keputusan" value="setujui">Setujui</button>
                                    <button class="btn btn-ghost btn-sm" name="keputusan" value="tolak">Tolak</button></div>
                                </form>
                            @else
                                Sudah diproses
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">Belum ada pengajuan dari siswa yang menjadi tanggung jawab Anda.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
@endsection
