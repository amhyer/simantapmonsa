@extends('layouts.app')

@section('title', 'Anggota Rombel - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Anggota: ' . $rombel->nama_rombel)
@section('page_subtitle', ($rombel->kurikulum ?? 'Kurikulum -') . ' · ' . ($rombel->jenis_rombel ?? '-') . ' · Tingkat ' . ($rombel->tingkat ?? '-'))

@section('header_actions')
    <a href="{{ route('admin.peta-kelas.daftar') }}" class="btn btn-sm btn-ghost">← Kembali</a>
@endsection

@section('content')
    <div class="grid grid-4" style="margin-bottom:16px">
        <div class="stat"><div class="label">Wali Kelas</div><div class="value" style="font-size:18px">{{ $rombel->guru?->nama_lengkap ?? '-' }}</div></div>
        <div class="stat ok"><div class="label">Jumlah Siswa</div><div class="value">{{ $rombel->siswa->count() }}</div></div>
        <div class="stat gold"><div class="label">Semester</div><div class="value" style="font-size:16px">{{ $rombel->semester ? $rombel->semester->tahun_ajaran . ' ' . ucfirst($rombel->semester->nama_semester) : '-' }}</div></div>
        <div class="stat"><div class="label">Dapodik ID</div><div class="value" style="font-size:14px">{{ $rombel->dapodik_id ? substr($rombel->dapodik_id, 0, 8) . '…' : '-' }}</div></div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Daftar Anggota</h3>
            <input type="text" id="searchAnggota" placeholder="Cari nama atau NIS..." style="padding:6px 12px;border:1px solid #E4E7EC;border-radius:8px;width:200px" oninput="filterAnggota(this.value)">
        </div>
        <div class="card-body tight">
            @if($rombel->siswa->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse" id="tabelAnggota">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085;width:50px">No</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Nama</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">NIS / NISN</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">L/P</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rombel->siswa as $i => $s)
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $i + 1 }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC"><b>{{ $s->nama_peserta_didik }}</b></td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $s->nis }}<div style="font-size:12px;color:#667085">{{ $s->nisn ?? '-' }}</div></td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $s->jenis_kelamin }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $s->aktif ? 'Aktif' : 'Nonaktif' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <div class="icon">👥</div>
                    <h4>Belum ada anggota</h4>
                    <p>Tugaskan siswa ke rombel ini melalui Papan Pemetaan atau sinkron Dapodik.</p>
                </div>
            @endif
        </div>
    </div>

    <script>
        function filterAnggota(keyword) {
            keyword = keyword.toLowerCase();
            document.querySelectorAll('#tabelAnggota tbody tr').forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().includes(keyword) ? '' : 'none';
            });
        }
    </script>
@endsection
