@extends('layouts.admin-with-tabs')

@section('title', 'Foto Siswa - SIMANTAP')

@section('page_title', 'Foto Siswa')
@section('page_subtitle', 'Unggah foto siswa satuan maupun massal (.zip / multi-file) berdasarkan NISN atau NIS')

@section('header_actions')
    <button type="button" onclick="bukaModalMasalFoto()" class="btn btn-sm" style="background:#991B1B;color:#fff;border-color:#991B1B">
        <i class="fas fa-upload"></i> Reset dan Upload Masal Foto Siswa
    </button>
    @if($denganFoto > 0)
        <form action="{{ route('admin.referensi.foto-siswa.reset-semua') }}" method="POST" style="display:inline" onsubmit="return confirm('Reset / hapus seluruh foto siswa ({{ $denganFoto }} foto)?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-ghost" style="color:#B42318;border:1px solid #FECDCA">
                <i class="fas fa-trash"></i> Reset Semua Foto
            </button>
        </form>
    @endif
@endsection

@section('content')
    <div class="grid grid-4" style="margin-bottom:16px">
        <div class="stat"><div class="label">Total siswa</div><div class="value">{{ $total }}</div></div>
        <div class="stat ok"><div class="label">Sudah berfoto</div><div class="value">{{ $denganFoto }}</div></div>
        <div class="stat gold"><div class="label">Belum berfoto</div><div class="value">{{ $total - $denganFoto }}</div></div>
    </div>

    @if(session('foto_tidak_cocok'))
        <div class="card" style="margin-bottom:16px;border-left:4px solid #B8860B">
            <div class="card-body">
                <b>File yang tidak cocok dengan NIS/NISN mana pun:</b>
                <div style="font-size:13px;color:#667085;margin-top:6px">{{ implode(', ', session('foto_tidak_cocok')) }}</div>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header" style="flex-wrap:wrap;gap:10px">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <h3 style="margin:0;margin-right:8px">FOTO SISWA</h3>
                <button type="button" onclick="copyFotoTable()" class="btn btn-xs" style="background:#991B1B;color:#fff;border:none;padding:5px 10px;border-radius:6px">
                    <i class="fas fa-copy"></i> Copy
                </button>
                <button type="button" onclick="exportFotoCsv()" class="btn btn-xs" style="background:#991B1B;color:#fff;border:none;padding:5px 10px;border-radius:6px">
                    <i class="fas fa-file-excel"></i> Excel
                </button>
                <button type="button" onclick="exportFotoPdf()" class="btn btn-xs" style="background:#991B1B;color:#fff;border:none;padding:5px 10px;border-radius:6px">
                    <i class="fas fa-file-pdf"></i> Pdf
                </button>
                <button type="button" onclick="window.print()" class="btn btn-xs" style="background:#991B1B;color:#fff;border:none;padding:5px 10px;border-radius:6px">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <form method="GET" action="{{ route('admin.referensi.foto-siswa') }}" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:0">
                    <select name="kelas" onchange="this.form.submit()" aria-label="Filter kelas" style="padding:6px 10px;border:1px solid #E4E7EC;border-radius:8px;font-size:12.5px">
                        <option value="">Semua Kelas</option>
                        @foreach($daftarKelas ?? [] as $namaKelas)
                            <option value="{{ $namaKelas }}" @selected(($kelasDipilih ?? '') === $namaKelas)>{{ str_starts_with($namaKelas, 'Kelas') ? $namaKelas : 'Kelas ' . $namaKelas }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="q" id="searchFoto" value="{{ $cari ?? '' }}" placeholder="Cari nama, NISN, atau NIS..." style="padding:6px 12px;border:1px solid #E4E7EC;border-radius:8px;width:220px">
                    <button type="submit" class="btn btn-sm btn-ghost" style="border:1px solid #E4E7EC">Cari</button>
                </form>
            </div>
        </div>
        <div class="card-body tight">
            @if($daftar->count())
                <div class="table-wrapper">
                    <table style="width:100%;border-collapse:collapse" id="tabelFoto">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085;width:48px">No</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Nama Siswa</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085;width:130px">Foto Siswa</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Ubah Foto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($daftar as $idx => $s)
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center;font-size:12.5px" class="col-no-foto">{{ ($daftar->firstItem() ?? 1) + $idx }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        <b>{{ $s->nama_peserta_didik }}</b>
                                        <div style="font-size:12px;color:#667085;margin-top:2px">
                                            Username : <code>{{ $s->nisn ?: $s->nis }}</code>
                                            @if($s->nis && $s->nisn)
                                                &bull; NIS: {{ $s->nis }}
                                            @endif
                                            @if($s->kelas)
                                                &bull; {{ str_starts_with($s->kelas, 'Kelas') ? $s->kelas : 'Kelas ' . $s->kelas }}
                                            @endif
                                        </div>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">
                                        @if($s->foto)
                                            <img src="{{ $s->fotoUrl() }}" alt="Foto {{ $s->nama_peserta_didik }}" loading="lazy" style="width:56px;height:56px;object-fit:cover;border-radius:8px;border:1px solid #E4E7EC">
                                        @else
                                            <span class="avatar" style="width:48px;height:48px;margin:0 auto;font-size:14px">{{ substr($s->nama_peserta_didik, 0, 2) }}</span>
                                            <div style="font-size:10.5px;color:#98A2B3;margin-top:2px">Belum ada</div>
                                        @endif
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                                            <form action="{{ route('admin.referensi.foto-siswa.satuan', $s) }}" method="POST" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center">
                                                @csrf
                                                <input type="file" name="foto" accept=".jpeg,.jpg,.png" required style="font-size:12px;max-width:210px;padding:4px;border:1px solid #E4E7EC;border-radius:6px">
                                                <button type="submit" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-upload"></i> Upload
                                                </button>
                                            </form>
                                            @if($s->foto)
                                                <form action="{{ route('admin.referensi.foto-siswa.destroy', $s) }}" method="POST" onsubmit="return confirm('Hapus foto {{ $s->nama_peserta_didik }}?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-ghost" style="color:#B42318;border:1px solid #FECDCA" title="Hapus Foto">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($daftar->hasPages())
                    <div style="padding:14px 16px;border-top:1px solid #E4E7EC">
                        {{ $daftar->links() }}
                    </div>
                @endif
            @else
                <div class="empty-state">
                    <div class="icon">📸</div>
                    <h4>Belum ada data siswa</h4>
                </div>
            @endif
        </div>
    </div>

    {{-- Modal #modal_masal: Reset Foto dan Upload Masal Foto Siswa (setara e-Rapor SD /foto_siswa) --}}
    <div id="modal_masal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.55);z-index:1000;align-items:center;justify-content:center;padding:16px">
        <div style="background:#fff;border-radius:12px;max-width:540px;width:100%;box-shadow:0 20px 50px rgba(0,0,0,0.25);overflow:hidden">
            <div style="padding:14px 20px;background:var(--navy);color:#fff;display:flex;align-items:center;justify-content:space-between">
                <h3 style="margin:0;font-size:16px;color:#fff"><i class="fas fa-file-archive" style="margin-right:8px"></i>Reset Foto dan Upload Masal Foto Siswa</h3>
                <button type="button" onclick="tutupModalMasalFoto()" style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer">&times;</button>
            </div>
            <form action="{{ route('admin.referensi.foto-siswa.store') }}" method="POST" enctype="multipart/form-data" style="padding:20px">
                @csrf
                <div style="background:#EFF8FF;border:1px solid #B2DDFF;border-radius:8px;padding:12px;margin-bottom:14px;font-size:12.5px;color:#175CD3;line-height:1.5">
                    <b>Petunjuk Upload Massal:</b><br>
                    1. Anda dapat mengunggah file arsip <b><code>.zip</code></b> yang berisi foto siswa, atau memilih banyak file foto sekaligus (<code>.jpg</code> / <code>.png</code>).<br>
                    2. Nama setiap file foto wajib sesuai <b>NISN</b> atau <b>NIS</b> siswa (contoh: <code>0012345678.jpg</code>).
                </div>

                <div style="margin-bottom:14px">
                    <label class="form-label">Opsi 1: Pilih File Arsip (.zip)</label>
                    <input type="file" name="file_zip" accept=".zip" class="form-control">
                </div>

                <div style="margin-bottom:14px">
                    <label class="form-label">Opsi 2: Atau Pilih Banyak File Foto Sekaligus (.jpg / .png)</label>
                    <input type="file" name="foto[]" accept=".jpeg,.jpg,.png" multiple class="form-control">
                </div>

                <div style="margin-bottom:16px">
                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
                        <input type="checkbox" name="reset_semua" value="1">
                        <span>Reset (hapus) seluruh foto lama sebelum memasangkan foto baru</span>
                    </label>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;border-top:1px solid #E4E7EC;padding-top:14px">
                    <button type="button" onclick="tutupModalMasalFoto()" class="btn btn-sm btn-ghost">Close</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-upload"></i> Upload & Ekstrak
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function bukaModalMasalFoto() {
            document.getElementById('modal_masal').style.display = 'flex';
        }
        function tutupModalMasalFoto() {
            document.getElementById('modal_masal').style.display = 'none';
        }

        function applyFotoFilter() {
            const keyword = (document.getElementById('searchFoto')?.value || '').toLowerCase();
            const limit = parseInt(document.getElementById('fotoPageSize')?.value || '999999', 10);
            let visible = 0;
            document.querySelectorAll('#tabelFoto tbody tr').forEach(function (row) {
                const match = row.textContent.toLowerCase().includes(keyword);
                if (match && visible < limit) {
                    row.style.display = '';
                    visible++;
                    const noCell = row.querySelector('.col-no-foto');
                    if (noCell) noCell.textContent = visible;
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function getFotoMatrix() {
            const rows = [['No', 'Nama Siswa', 'Identitas / Kelas', 'Status Foto']];
            document.querySelectorAll('#tabelFoto tbody tr').forEach(tr => {
                if (tr.style.display === 'none') return;
                const tds = tr.querySelectorAll('td');
                if (tds.length >= 3) {
                    rows.push([
                        tds[0].innerText.trim(),
                        tds[1].querySelector('b')?.innerText.trim() || '',
                        tds[1].querySelector('div')?.innerText.replace(/\s+/g, ' ').trim() || '',
                        tds[2].querySelector('img') ? 'Sudah Berfoto' : 'Belum Ada'
                    ]);
                }
            });
            return rows;
        }

        function copyFotoTable() {
            const text = getFotoMatrix().map(r => r.join('\t')).join('\n');
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text);
                if (typeof showToast === 'function') showToast('Data foto siswa disalin ke clipboard', 'success');
            }
        }

        function exportFotoCsv() {
            const csv = getFotoMatrix().map(r => r.map(c => '"' + String(c).replace(/"/g, '""') + '"').join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'Foto_Siswa_SIMANTAP.csv';
            link.click();
        }

        function exportFotoPdf() {
            const rows = getFotoMatrix();
            const win = window.open('', '_blank');
            if (!win) { window.print(); return; }
            let html = '<!DOCTYPE html><html><head><title>Daftar Foto Siswa</title><style>body{font-family:Arial,sans-serif;font-size:12px;padding:20px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #cbd5e1;padding:6px 8px;text-align:left}th{background:#f1f5f9}</style></head><body><h2>Daftar Foto Siswa</h2><div class="table-wrapper"><table><thead><tr>';
            rows[0].forEach(h => { html += '<th>' + h + '</th>'; });
            html += '</tr></thead><tbody>';
            rows.slice(1).forEach(r => {
                html += '<tr>';
                r.forEach(c => { html += '<td>' + c + '</td>'; });
                html += '</tr>';
            });
            html += '</tbody></table></div><script>window.onload=function(){window.print();}<\/script></body></html>';
            win.document.open(); win.document.write(html); win.document.close();
        }
    </script>
@endsection
