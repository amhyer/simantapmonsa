@extends('layouts.app')

@section('title', 'Data Pembelajaran - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Data Pembelajaran')
@section('page_subtitle', 'Jadwal pembelajaran (rombongan belajar × mapel × guru); baris DAPODIK berasal dari hasil sinkronisasi')

@section('header_actions')
    <a href="{{ route('admin.dapodik.index') }}" class="btn btn-sm btn-ghost">Import Dapodik</a>
    <a href="{{ route('admin.peta-kelas.index') }}" class="btn btn-sm">Peta Kelas</a>
@endsection

@section('content')
    <div class="grid grid-4" style="margin-bottom:16px">        <div class="stat"><div class="label">Total jadwal</div><div class="value">{{ $jadwal->count() }}</div></div>
        <div class="stat ok"><div class="label">Rombel terisi</div><div class="value">{{ $jadwal->pluck('kelas')->filter()->unique()->count() }}</div></div>
        <div class="stat gold"><div class="label">Mapel terisi</div><div class="value">{{ $jadwal->pluck('mata_pelajaran')->filter()->unique()->count() }}</div></div>
        <div class="stat"><div class="label">Dari Dapodik</div><div class="value">{{ $jadwal->whereNotNull('dapodik_id')->count() }}</div></div>
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Tambah Jadwal Pembelajaran</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.referensi.pembelajaran.store') }}" method="POST" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;align-items:end">
                @csrf
                <div>
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Hari</label>
                    <select name="hari" required style="width:100%;padding:8px 10px;border:1px solid var(--line);border-radius:8px">
                        @foreach(['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'] as $h)
                            <option value="{{ $h }}">{{ $h }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Mata Pelajaran</label>
                    <select name="mata_pelajaran" required style="width:100%;padding:8px 10px;border:1px solid var(--line);border-radius:8px">
                        @foreach($daftarMapel as $m)
                            <option value="{{ $m->nama }}">{{ $m->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Kelas</label>
                    <input type="text" name="kelas" required placeholder="cth: Kelas 1.A" style="width:100%;padding:8px 10px;border:1px solid var(--line);border-radius:8px">
                </div>
                <div>
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Guru</label>
                    <select name="guru_id" required style="width:100%;padding:8px 10px;border:1px solid var(--line);border-radius:8px">
                        <option value="">— Pilih guru —</option>
                        @foreach($daftarGuru as $g)
                            <option value="{{ $g->id }}">{{ $g->nama_lengkap }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Jam</label>
                    <div style="display:flex;gap:6px;align-items:center">
                        <input type="time" name="jam_mulai" required style="width:100%;padding:8px 10px;border:1px solid var(--line);border-radius:8px">
                        <span style="color:var(--muted)">–</span>
                        <input type="time" name="jam_selesai" required style="width:100%;padding:8px 10px;border:1px solid var(--line);border-radius:8px">
                    </div>
                </div>
                <div>
                    <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Ruangan</label>
                    <input type="text" name="ruangan" placeholder="cth: R-01" style="width:100%;padding:8px 10px;border:1px solid var(--line);border-radius:8px">
                </div>
                <div>
                    <button type="submit" class="btn btn-sm">Tambah</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Daftar Pembelajaran</h3>
            <input type="text" id="searchJadwal" placeholder="Cari mapel, kelas, atau guru..." style="padding:6px 12px;border:1px solid #E4E7EC;border-radius:8px;width:220px" oninput="filterJadwal(this.value)">
        </div>
        <div class="card-body tight">
            @if($jadwal->count())
                <div class="table-wrapper">
                    <table style="width:100%;border-collapse:collapse" id="tabelJadwal">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Hari / Jam</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Mata Pelajaran</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Kelas</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Guru</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Ruangan</th>
                                <th style="padding:11px 14px;text-align:right;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($jadwal as $j)
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        <b>{{ $j->hari ?? '-' }}</b>
                                        <div style="font-size:12px;color:#667085">{{ $j->jam_mulai ?? '-' }} – {{ $j->jam_selesai ?? '-' }}</div>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        {{ $j->mata_pelajaran ?? '-' }}
                                        @if($j->dapodik_id)
                                            <span style="display:inline-block;background:#EEF2F9;color:var(--navy);font-size:9px;padding:1px 5px;border-radius:4px;margin-left:4px;font-weight:600;vertical-align:middle" title="Data dari Dapodik">DAPODIK</span>
                                        @endif
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $j->rombel?->nama_rombel ?? $j->kelas ?? '-' }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $j->guru?->nama_lengkap ?? '-' }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $j->ruangan ?? '-' }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:right">
                                        <form action="{{ route('admin.referensi.pembelajaran.destroy', $j) }}" method="POST" style="display:inline" onsubmit="return confirm('Hapus jadwal {{ $j->mata_pelajaran }} ({{ $j->kelas }})? Data Dapodik dapat ditarik ulang bila dibutuhkan.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <div class="icon">📅</div>
                    <h4>Belum ada data pembelajaran</h4>
                    <p>Import dari Dapodik untuk mengisi jadwal pembelajaran.</p>
                </div>
            @endif
        </div>
    </div>

    <script>
        function filterJadwal(keyword) {
            keyword = keyword.toLowerCase();
            document.querySelectorAll('#tabelJadwal tbody tr').forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().includes(keyword) ? '' : 'none';
            });
        }
    </script>
@endsection
