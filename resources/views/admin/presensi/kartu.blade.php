@extends('layouts.admin-with-tabs')

@section('title', 'Pemetaan QR Kartu Siswa - SIMANTAP')
@section('page_title', 'Pemetaan QR Kartu Siswa')
@section('page_subtitle', 'Hubungkan QR yang sudah tercetak ke data siswa tanpa menyimpan isi QR mentah')

@section('content')
    <div class="note note-warn" style="margin-bottom:16px">
        Pilih siswa pada tabel, lalu scan QR dari kartu fisiknya. Sistem hanya menyimpan fingerprint terenkripsi aplikasi dari isi QR; isi QR mentah tidak disimpan atau ditampilkan. Jika kartu diganti, hubungkan QR baru atau lepaskan kartu lama.
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="card-header"><h3>Scan / Hubungkan Kartu</h3></div>
        <div class="card-body">
            <div data-presensi-qr data-mode="bind-card"
                 data-endpoint-template="{{ route('admin.presensi.kartu.bind', ['siswa' => '__SISWA_ID__']) }}">
                <p><strong>Siswa terpilih:</strong> <span data-qr-selected-name>Belum dipilih</span></p>
                <div data-qr-reader style="width:100%;max-width:520px;margin:12px auto"></div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                    <button type="button" class="btn btn-sm" data-qr-start>Aktifkan kamera</button>
                    <button type="button" class="btn btn-ghost btn-sm" data-qr-stop disabled>Hentikan kamera</button>
                    <input type="text" data-qr-payload autocomplete="off" placeholder="Atau scan/tempel isi QR di sini" style="flex:1;min-width:220px">
                    <button type="button" class="btn btn-ghost btn-sm" data-qr-submit-manual>Hubungkan</button>
                </div>
                <div data-qr-feedback role="status" aria-live="polite" style="margin-top:10px;padding:10px;border-radius:8px;background:#F2F4F7" data-kind="info">Pilih siswa dari tabel, lalu pindai kartunya.</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="gap:10px;flex-wrap:wrap">
            <h3 style="margin-right:auto">Daftar Siswa</h3>
            <form method="GET" action="{{ route('admin.presensi.kartu.index') }}" style="display:flex;gap:6px">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama atau NIS…">
                <button type="submit" class="btn btn-sm">Cari</button>
            </form>
        </div>
        <div class="card-body tight">
            <div class="table-wrapper"><table>
                <thead><tr><th>Nama Siswa</th><th>NIS</th><th>Kelas</th><th>Status Kartu</th><th>Aksi</th></tr></thead>
                <tbody>
                @forelse($students as $student)
                    <tr>
                        <td>{{ $student->nama_peserta_didik }}</td>
                        <td>{{ $student->nis }}</td>
                        <td>{{ $student->rombel?->nama_rombel ?? $student->kelas ?? '—' }}</td>
                        <td>@if($student->qr_token_hash)<span class="tag tag-ok">Terhubung</span>@else<span class="tag tag-warn">Belum terhubung</span>@endif</td>
                        <td style="white-space:nowrap">
                            <button type="button" class="btn btn-ghost btn-sm" data-qr-select-student="{{ $student->id }}" data-student-name="{{ $student->nama_peserta_didik }}">Pilih / ganti QR</button>
                            @if($student->qr_token_hash)
                                <form method="POST" action="{{ route('admin.presensi.kartu.reset', $student) }}" style="display:inline" onsubmit="return confirm('Lepaskan QR kartu {{ addslashes($student->nama_peserta_didik) }}? Kartu tidak dapat dipindai sampai dihubungkan lagi.')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-ghost btn-sm" style="color:#B42318">Lepaskan</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;padding:24px">Siswa tidak ditemukan.</td></tr>
                @endforelse
                </tbody>
            </table></div>
            <div style="padding:14px">{{ $students->links() }}</div>
        </div>
    </div>
@endsection
