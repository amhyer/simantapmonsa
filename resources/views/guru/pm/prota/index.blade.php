@extends('layouts.app')

@section('title', 'Program Tahunan (Prota) - Pembelajaran Mendalam')
@section('page_title', 'Program Tahunan (Prota)')
@section('page_subtitle', 'Rencana tahunan terintegrasi Kalender Pendidikan & ATP — dapat ditarik otomatis dari ATP atau disusun dengan AI')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('header_actions')
    <a href="{{ route('guru.pm.prota.pdf', ['tahun_ajaran' => $tahunAjaran, 'tingkat' => $tingkat, 'mapel' => $mapelAktif]) }}"
       target="_blank" class="btn btn-ghost btn-sm">
        <i class="fas fa-print"></i> Cetak Mapel Ini
    </a>
    <a href="{{ route('guru.pm.prota.pdf', ['tahun_ajaran' => $tahunAjaran, 'tingkat' => $tingkat]) }}"
       target="_blank" class="btn btn-ghost btn-sm">
        <i class="fas fa-print"></i> Cetak Semua Mapel
    </a>
@endsection

@section('content')
    @include('guru.pm._nav')

    {{-- Pilihan Kelas & Mapel --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-body" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between">
            <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">
                <span style="font-size:12px;font-weight:700;color:#667085;margin-right:4px">KELAS:</span>
                @foreach($pmAllowedTingkat ?? [1,2,3,4,5,6] as $t)
                    <a href="{{ route('guru.pm.prota.index', ['tahun_ajaran' => $tahunAjaran, 'tingkat' => $t, 'mapel' => $mapelAktif]) }}"
                       class="btn btn-sm {{ $tingkat === $t ? '' : 'btn-ghost' }}">Kelas {{ $t }}</a>
                @endforeach
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:6px">
                @foreach($mapelList as $m)
                    <a href="{{ route('guru.pm.prota.index', ['tahun_ajaran' => $tahunAjaran, 'tingkat' => $tingkat, 'mapel' => $m->nama]) }}"
                       class="btn btn-sm {{ $mapelAktif === $m->nama ? '' : 'btn-ghost' }}">
                        {{ $m->kode }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Ringkasan JP Tersedia vs Direncanakan --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px;margin-bottom:16px">
        <div class="card">
            <div class="card-body" style="padding:12px">
                <div style="font-size:12px;color:#667085;font-weight:600">Semester 1 · JP Tersedia</div>
                <div style="font-size:20px;font-weight:700;color:#101828">{{ $sem1['jp_tersedia'] }} JP</div>
                <div style="font-size:12px;color:#475467">{{ $sem1['total_efektif'] }} mgg efektif × {{ $jpPerMinggu }} JP/mgg · Prota: <strong>{{ $totalJpSem1 }} JP</strong></div>
            </div>
        </div>
        <div class="card">
            <div class="card-body" style="padding:12px">
                <div style="font-size:12px;color:#667085;font-weight:600">Semester 2 · JP Tersedia</div>
                <div style="font-size:20px;font-weight:700;color:#101828">{{ $sem2['jp_tersedia'] }} JP</div>
                <div style="font-size:12px;color:#475467">{{ $sem2['total_efektif'] }} mgg efektif × {{ $jpPerMinggu }} JP/mgg · Prota: <strong>{{ $totalJpSem2 }} JP</strong></div>
            </div>
        </div>
        <div class="card">
            <div class="card-body" style="padding:12px">
                <div style="font-size:12px;color:#667085;font-weight:600">Sesuaikan JP / Minggu</div>
                <form method="GET" action="{{ route('guru.pm.prota.index') }}" style="display:flex;gap:6px;margin-top:6px">
                    <input type="hidden" name="tahun_ajaran" value="{{ $tahunAjaran }}">
                    <input type="hidden" name="tingkat" value="{{ $tingkat }}">
                    <input type="hidden" name="mapel" value="{{ $mapelAktif }}">
                    <input type="number" name="jp" min="1" max="20" value="{{ $jpPerMinggu }}"
                           style="width:70px;padding:5px;border:1px solid #D0D5DD;border-radius:6px;text-align:center">
                    <button type="submit" class="btn btn-sm btn-ghost">Hitung Ulang</button>
                </form>
            </div>
        </div>
    </div>

    {{-- Aksi Cepat: Ambil dari ATP & Susun AI --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-body" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between">
            <form method="POST" action="{{ route('guru.pm.prota.ambil-atp') }}" style="display:inline"
                  onsubmit="return confirm('Ganti daftar Prota mapel ini dengan data dari ATP?');">
                @csrf
                <input type="hidden" name="tahun_ajaran" value="{{ $tahunAjaran }}">
                <input type="hidden" name="tingkat" value="{{ $tingkat }}">
                <input type="hidden" name="nama_mapel" value="{{ $mapelAktif }}">
                <button type="submit" class="btn btn-sm btn-ghost">
                    <i class="fas fa-download"></i> Ambil dari ATP
                </button>
            </form>

            <form method="POST" action="{{ route('guru.pm.prota.ai') }}" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
                @csrf
                <input type="hidden" name="tahun_ajaran" value="{{ $tahunAjaran }}">
                <input type="hidden" name="tingkat" value="{{ $tingkat }}">
                <input type="hidden" name="nama_mapel" value="{{ $mapelAktif }}">
                <input type="text" name="catatan" placeholder="Catatan opsional untuk AI..."
                       style="padding:6px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px;min-width:220px">
                <button type="submit" class="btn btn-sm" {{ !$aiQuota['configured'] ? 'disabled' : '' }}>
                    <i class="fas fa-magic"></i> Susun Prota dengan AI
                </button>
            </form>
        </div>
    </div>

    {{-- Tabel Editor Baris Prota --}}
    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <strong>Program Tahunan — {{ $mapelAktif }} Kelas {{ $tingkat }} (Fase {{ $fase }})</strong>
            <button type="button" class="btn btn-sm btn-ghost" onclick="tambahBarisProta()">
                <i class="fas fa-plus"></i> Tambah Baris
            </button>
        </div>
        <form method="POST" action="{{ route('guru.pm.prota.store') }}">
            @csrf
            <input type="hidden" name="tahun_ajaran" value="{{ $tahunAjaran }}">
            <input type="hidden" name="tingkat" value="{{ $tingkat }}">
            <input type="hidden" name="nama_mapel" value="{{ $mapelAktif }}">
            <div class="card-body" style="padding:0">
                <div class="table-wrapper">
                    <table class="table" id="protaTable">
                        <thead>
                            <tr>
                                <th style="width:115px">Semester</th>
                                <th style="width:240px">Tujuan Pembelajaran (opsional)</th>
                                <th>Materi / Bab *</th>
                                <th style="width:85px">JP</th>
                                <th style="width:190px">Keterangan</th>
                                <th style="width:55px"></th>
                            </tr>
                        </thead>
                        <tbody id="protaBody">
                            @forelse($protaRows as $i => $row)
                                <tr>
                                    <td>
                                        <select name="rows[{{ $i }}][semester]" style="width:100%;padding:6px;border:1px solid #D0D5DD;border-radius:6px;font-size:13px">
                                            <option value="1" {{ $row->semester === 1 ? 'selected' : '' }}>Sem 1</option>
                                            <option value="2" {{ $row->semester === 2 ? 'selected' : '' }}>Sem 2</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="rows[{{ $i }}][tp_id]" style="width:100%;padding:6px;border:1px solid #D0D5DD;border-radius:6px;font-size:12px">
                                            <option value="">— Tanpa tautan TP —</option>
                                            @foreach($tpOptions as $tp)
                                                <option value="{{ $tp->id }}" {{ (int) $row->tp_id === (int) $tp->id ? 'selected' : '' }}>
                                                    {{ $tp->kode_tp ? '['.$tp->kode_tp.'] ' : '' }}{{ \Illuminate\Support\Str::limit($tp->deskripsi, 55) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="rows[{{ $i }}][materi]" value="{{ $row->materi }}" required
                                               style="width:100%;padding:6px 8px;border:1px solid #D0D5DD;border-radius:6px;font-size:13px">
                                    </td>
                                    <td>
                                        <input type="number" min="0" max="200" name="rows[{{ $i }}][alokasi_jp]" value="{{ $row->alokasi_jp }}"
                                               style="width:68px;padding:6px;border:1px solid #D0D5DD;border-radius:6px;text-align:center">
                                    </td>
                                    <td>
                                        <input type="text" name="rows[{{ $i }}][keterangan]" value="{{ $row->keterangan }}"
                                               style="width:100%;padding:6px 8px;border:1px solid #D0D5DD;border-radius:6px;font-size:12px">
                                    </td>
                                    <td style="text-align:center">
                                        <button type="button" class="btn btn-sm btn-ghost" style="color:#D92D20" onclick="this.closest('tr').remove()">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td>
                                        <select name="rows[0][semester]" style="width:100%;padding:6px;border:1px solid #D0D5DD;border-radius:6px;font-size:13px">
                                            <option value="1">Sem 1</option>
                                            <option value="2">Sem 2</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="rows[0][tp_id]" style="width:100%;padding:6px;border:1px solid #D0D5DD;border-radius:6px;font-size:12px">
                                            <option value="">— Tanpa tautan TP —</option>
                                            @foreach($tpOptions as $tp)
                                                <option value="{{ $tp->id }}">
                                                    {{ $tp->kode_tp ? '['.$tp->kode_tp.'] ' : '' }}{{ \Illuminate\Support\Str::limit($tp->deskripsi, 55) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="rows[0][materi]" placeholder="Ketik materi/bab atau klik Ambil dari ATP..."
                                               style="width:100%;padding:6px 8px;border:1px solid #D0D5DD;border-radius:6px;font-size:13px">
                                    </td>
                                    <td>
                                        <input type="number" min="0" max="200" name="rows[0][alokasi_jp]" value="8"
                                               style="width:68px;padding:6px;border:1px solid #D0D5DD;border-radius:6px;text-align:center">
                                    </td>
                                    <td>
                                        <input type="text" name="rows[0][keterangan]"
                                               style="width:100%;padding:6px 8px;border:1px solid #D0D5DD;border-radius:6px;font-size:12px">
                                    </td>
                                    <td></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-body" style="border-top:1px solid #EAECF0;display:flex;justify-content:space-between;align-items:center">
                <a href="{{ route('guru.pm.prosem.index', ['tahun_ajaran' => $tahunAjaran, 'tingkat' => $tingkat, 'mapel' => $mapelAktif]) }}"
                   class="btn btn-sm btn-ghost">
                    Lanjut ke Program Semester <i class="fas fa-arrow-right"></i>
                </a>
                <button type="submit" class="btn"><i class="fas fa-save"></i> Simpan Program Tahunan</button>
            </div>
        </form>
    </div>

    <script>
        let protaIdx = {{ max(1, $protaRows->count()) }};
        function tambahBarisProta() {
            const tbody = document.getElementById('protaBody');
            const idx = protaIdx++;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <select name="rows[${idx}][semester]" style="width:100%;padding:6px;border:1px solid #D0D5DD;border-radius:6px;font-size:13px">
                        <option value="1">Sem 1</option>
                        <option value="2">Sem 2</option>
                    </select>
                </td>
                <td>
                    <select name="rows[${idx}][tp_id]" style="width:100%;padding:6px;border:1px solid #D0D5DD;border-radius:6px;font-size:12px">
                        <option value="">— Tanpa tautan TP —</option>
                        @foreach($tpOptions as $tp)
                            <option value="{{ $tp->id }}">{{ $tp->kode_tp ? '['.$tp->kode_tp.'] ' : '' }}{{ addslashes(\Illuminate\Support\Str::limit($tp->deskripsi, 55)) }}</option>
                        @endforeach
                    </select>
                </td>
                <td><input type="text" name="rows[${idx}][materi]" required style="width:100%;padding:6px 8px;border:1px solid #D0D5DD;border-radius:6px;font-size:13px"></td>
                <td><input type="number" min="0" max="200" name="rows[${idx}][alokasi_jp]" value="6" style="width:68px;padding:6px;border:1px solid #D0D5DD;border-radius:6px;text-align:center"></td>
                <td><input type="text" name="rows[${idx}][keterangan]" style="width:100%;padding:6px 8px;border:1px solid #D0D5DD;border-radius:6px;font-size:12px"></td>
                <td style="text-align:center"><button type="button" class="btn btn-sm btn-ghost" style="color:#D92D20" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td>
            `;
            tbody.appendChild(tr);
        }
    </script>
@endsection
