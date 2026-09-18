@extends('layouts.app')

@section('title', 'Data Guru - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Data Guru')
@section('page_subtitle', 'Data pendidik dan tenaga kependidikan dari Dapodik (read-only)')

@section('header_actions')
    <a href="{{ route('admin.dapodik.index') }}" class="btn btn-sm btn-ghost">Import Dapodik</a>
    <a href="{{ route('admin.peta-kelas.index') }}" class="btn btn-sm">Peta Kelas</a>
@endsection

@section('content')
    @php
        $guruList = $ptk->filter(fn($p) => $p->isGuru());
        $kepsekList = $ptk->filter(fn($p) => $p->isKepsek());
    @endphp

    <div class="grid grid-4" style="margin-bottom:16px">
        <div class="stat"><div class="label">Total PTK</div><div class="value">{{ $ptk->count() }}</div></div>
        <div class="stat ok"><div class="label">Guru</div><div class="value">{{ $guruList->count() }}</div></div>
        <div class="stat gold"><div class="label">Kepala Sekolah</div><div class="value">{{ $kepsekList->count() }}</div></div>
        <div class="stat"><div class="label">Dari Dapodik</div><div class="value">{{ $ptk->whereNotNull('dapodik_id')->count() }}</div></div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Daftar PTK</h3>
            <input type="text" id="searchPtk" placeholder="Cari nama atau NIP..." style="padding:6px 12px;border:1px solid #E4E7EC;border-radius:8px;width:200px" oninput="filterPtk(this.value)">
        </div>
        <div class="card-body tight">
            @if($ptk->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse" id="tabelPtk">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Nama</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">NIP</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Jenis PTK</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Jabatan</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Peran</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ptk as $p)
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        <div style="display:flex;align-items:center;gap:11px">
                                            <span class="avatar">{{ substr($p->nama, 0, 2) }}</span>
                                            <div>
                                                <b>{{ $p->nama }}</b>
                                                @if($p->dapodik_id)
                                                    <span style="display:inline-block;background:#EEF2F9;color:var(--navy);font-size:9px;padding:1px 5px;border-radius:4px;margin-left:4px;font-weight:600;vertical-align:middle" title="Data dari Dapodik">DAPODIK</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $p->nip ?? '-' }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $p->jenis_ptk ?? '-' }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $p->jabatan ?? '-' }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">
                                        @if($p->isKepsek())
                                            <span class="tag tag-gold">Kepsek</span>
                                        @elseif($p->isGuru())
                                            <span class="tag tag-ok">Guru</span>
                                        @else
                                            <span class="tag tag-mut">Tendik</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <div class="icon">👩‍🏫</div>
                    <h4>Belum ada data PTK</h4>
                    <p>Import dari Dapodik untuk mengisi data guru.</p>
                </div>
            @endif
        </div>
    </div>

    <script>
        function filterPtk(keyword) {
            keyword = keyword.toLowerCase();
            document.querySelectorAll('#tabelPtk tbody tr').forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().includes(keyword) ? '' : 'none';
            });
        }
    </script>
@endsection
