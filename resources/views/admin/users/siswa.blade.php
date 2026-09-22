@extends('layouts.app')

@section('title', 'Data Siswa - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Data Siswa')
@section('page_subtitle', 'Data peserta didik dari Dapodik beserta status akunnya (berbeda dengan Kelola Pengguna yang berisi akun login)')

@section('header_actions')
    <a href="{{ route('admin.users.akun-massal') }}" class="btn btn-sm">Akun Massal</a>
    <a href="{{ route('admin.dapodik.index') }}" class="btn btn-sm btn-ghost">Import Dapodik</a>
@endsection

@section('content')
    @php
        $punyaAkunSiswa = function ($s) use ($users) {
            return $users->contains(function ($u) use ($s) {
                return $u->peran === 'siswa' && is_array($u->terhubung_dengan) && in_array($s->id, $u->terhubung_dengan);
            });
        };
        $punyaAkunOrtu = function ($s) use ($users) {
            return $users->contains(function ($u) use ($s) {
                return $u->peran === 'ortu' && is_array($u->terhubung_dengan) && in_array($s->id, $u->terhubung_dengan);
            });
        };
        $dariDapodik = $siswa->whereNotNull('dapodik_id')->count();
        $tanpaAkun = $siswa->filter(function ($s) use ($punyaAkunSiswa) {
            return !$punyaAkunSiswa($s);
        })->count();
    @endphp

    <div class="grid grid-4" style="margin-bottom:16px">
        <div class="stat"><div class="label">Total siswa</div><div class="value">{{ $siswa->count() }}</div></div>
        <div class="stat ok"><div class="label">Dari Dapodik</div><div class="value">{{ $dariDapodik }}</div></div>
        <div class="stat gold"><div class="label">Aktif</div><div class="value">{{ $siswa->where('aktif', true)->count() }}</div></div>
        <div class="stat"><div class="label">Tanpa akun siswa</div><div class="value">{{ $tanpaAkun }}</div></div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Daftar Siswa (Data Dapodik)</h3>
            <input type="text" id="searchSiswa" placeholder="Cari nama, NIS, atau NISN..." style="padding:6px 12px;border:1px solid #E4E7EC;border-radius:8px;width:220px" oninput="filterSiswa(this.value)">
        </div>
        <div class="card-body tight">
            @if($siswa->count())
                <div class="table-wrapper">
                    <table style="width:100%;border-collapse:collapse" id="tabelSiswa">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Nama</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">NIS / NISN</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">L/P</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Kelas</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Tempat, Tanggal Lahir</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Wali Kelas</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Akun</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($siswa as $s)
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        <div style="display:flex;align-items:center;gap:11px">
                                            <span class="avatar">{{ substr($s->nama_peserta_didik, 0, 2) }}</span>
                                            <div>
                                                <b>{{ $s->nama_peserta_didik }}</b>
                                                @if($s->dapodik_id)
                                                    <span style="display:inline-block;background:#EEF2F9;color:var(--navy);font-size:9px;padding:1px 5px;border-radius:4px;margin-left:4px;font-weight:600;vertical-align:middle" title="Data dari Dapodik">DAPODIK</span>
                                                @endif
                                                <div style="font-size:12px;color:#667085">{{ $s->aktif ? 'Aktif' : 'Nonaktif' }}{{ $s->nama_orang_tua ? ' · Ortu: ' . $s->nama_orang_tua : '' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        <div>{{ $s->nis }}</div>
                                        <div style="font-size:12px;color:#667085">{{ $s->nisn ?? '-' }}</div>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $s->jenis_kelamin }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $s->kelas }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $s->tempat_lahir ?? '-' }}, {{ $s->tanggal_lahir ? $s->tanggal_lahir->translatedFormat('d M Y') : '-' }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $s->nama_guru ?? $s->guru?->nama_lengkap ?? '-' }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">
                                        @if($punyaAkunSiswa($s))
                                            <span class="tag tag-ok">Siswa</span>
                                        @else
                                            <span class="tag tag-mut">Tanpa akun</span>
                                        @endif
                                        @if($punyaAkunOrtu($s))
                                            <span class="tag tag-primary">Ortu</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <div class="icon">🎓</div>
                    <h4>Belum ada data siswa</h4>
                    <p>Import dari Dapodik atau tambah manual melalui menu guru.</p>
                </div>
            @endif
        </div>
    </div>

    <script>
        function filterSiswa(keyword) {
            keyword = keyword.toLowerCase();
            document.querySelectorAll('#tabelSiswa tbody tr').forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().includes(keyword) ? '' : 'none';
            });
        }
    </script>
@endsection
