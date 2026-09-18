@extends('layouts.app')

@section('title', 'Data Siswa - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Data Siswa')
@section('page_subtitle', 'Kelola peserta didik di kelas Anda')

@section('header_actions')
    <a href="{{ route('guru.siswa.pungut') }}" class="btn btn-ghost btn-sm" style="color:#B8860B">
        <i class="fas fa-file-import"></i> Ambil dari Kelas Lain
    </a>
    <button class="btn btn-ghost btn-sm" onclick="document.getElementById('imporForm').style.display='block'">
        <i class="fas fa-upload"></i> Impor CSV
    </button>
    <a href="{{ route('guru.siswa.unduh') }}" class="btn btn-ghost btn-sm">
        <i class="fas fa-download"></i> Unduh CSV
    </a>
    <a href="{{ route('guru.siswa.create') }}" class="btn btn-sm">
        <i class="fas fa-plus"></i> Tambah Siswa
    </a>
@endsection

@section('content')
    <div id="imporForm" style="display:none;margin-bottom:16px">
        <div class="card">
            <div class="card-body">
                <form action="{{ route('guru.siswa.impor') }}" method="POST">
                    @csrf
                    <div style="margin-bottom:12px">
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Tempel data CSV</label>
                        <textarea name="csv" rows="6" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px;font-family:monospace;font-size:13px"
                            placeholder="Nama,NIS,L/P,Kelas&#10;Andi Nurul,2401001,P,V A&#10;Muh. Rizky,2401002,L,V A"></textarea>
                        <div style="font-size:12px;color:#667085;margin-top:6px">Format: Nama, NIS, L/P (opsional), Kelas (opsional). Header akan diabaikan.</div>
                    </div>
                    <div style="display:flex;gap:10px">
                        <button type="submit" class="btn">Impor</button>
                        <button type="button" class="btn btn-ghost" onclick="document.getElementById('imporForm').style.display='none'">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Daftar Siswa</h3>
            <span class="tag tag-mut">{{ $siswa->where('aktif', true)->count() }} aktif</span>
        </div>
        <div class="card-body tight">
            @if($siswa->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Nama</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">NIS</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">L/P</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Kelas</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Kode Orang Tua</th>
                                <th style="padding:11px 14px;text-align:right;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Nilai Akhir</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Status</th>
                                <th style="padding:11px 14px;text-align:right;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $nilaiService = app(\App\Services\NilaiService::class); @endphp
                            @foreach($siswa as $s)
                                @php
                                    $na = $nilaiService->hitungNilaiAkhir($s->id);
                                    $predikat = $na['nilai_akhir'] > 0 ? $nilaiService->getPredikat($na['nilai_akhir'], 70) : null;
                                @endphp
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        <div style="display:flex;align-items:center;gap:11px">
                                            <span class="avatar">{{ substr($s->nama_peserta_didik, 0, 2) }}</span>
                                            <div>
                                                <b>{{ $s->nama_peserta_didik }}</b>
                                                @if($s->dapodik_id)
                                                    <span style="display:inline-block;background:#EEF2F9;color:var(--navy);font-size:9px;padding:1px 5px;border-radius:4px;margin-left:4px;font-weight:600;vertical-align:middle" title="Data dari Dapodik">DAPODIK</span>
                                                @endif
                                                <div style="font-size:12px;color:#667085">{{ $s->aktif ? 'Aktif' : 'Nonaktif' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $s->nis }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $s->jenis_kelamin }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $s->kelas }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        <code style="background:#EEF2F9;padding:2px 8px;border-radius:6px;font-size:12.5px">{{ $s->nama_orang_tua }}</code>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:right">
                                        @if($na['nilai_akhir'] > 0)
                                            <b>{{ number_format($na['nilai_akhir'], 1) }}</b>
                                        @else
                                            <span style="color:#667085">—</span>
                                        @endif
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">
                                        @if($predikat)
                                            <span class="tag {{ $predikat['kelas'] === 'ok' ? 'tag-ok' : ($predikat['kelas'] === 'warn' ? 'tag-warn' : 'tag-bad') }}">{{ $predikat['huruf'] }}</span>
                                        @else
                                            <span class="tag tag-mut">—</span>
                                        @endif
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:right;white-space:nowrap">
                                        <a href="{{ route('guru.siswa.edit', $s) }}" class="btn btn-ghost btn-sm">Ubah</a>
                                        <button onclick="hapusSiswa('{{ $s->id }}', '{{ $s->nama_peserta_didik }}')" class="btn btn-ghost btn-sm" style="color:#B42318">Hapus</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty">
                    <div class="icon">👥</div>
                    <b>Belum ada siswa</b>
                    <div>Tambahkan siswa satu per satu atau impor dari berkas CSV.</div>
                </div>
            @endif
        </div>
    </div>

    <div class="note" style="margin-top:16px">
        <b>Kode orang tua bersifat pribadi.</b> Bagikan kepada wali murid agar mereka dapat memantau perkembangan anaknya tanpa dapat mengubah data.
    </div>

    <script>
        function hapusSiswa(id, nama) {
            if (confirm(`Apakah yakin ingin menghapus ${nama}?`)) {
                fetch(`/guru/siswa/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                }).then(() => location.reload());
            }
        }
    </script>
@endsection
