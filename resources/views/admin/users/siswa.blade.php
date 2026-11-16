@extends('layouts.admin-with-tabs')

@section('title', 'Data Siswa - SIMANTAP')

@section('page_title', 'Data Siswa')
@section('page_subtitle', 'Data peserta didik, biodata pelengkap rapor, serta status akunnya')

@section('header_actions')
    <a href="{{ route('admin.users.index') }}" class="btn btn-sm" style="background:#991B1B;color:#fff;border-color:#991B1B">
        <i class="fas fa-users"></i> Data Pengguna
    </a>
    <a href="{{ route('admin.users.akun-massal') }}" class="btn btn-sm">Akun Massal</a>
    <a href="{{ route('admin.dapodik.index') }}" class="btn btn-sm btn-ghost">Impor Dapodik</a>
@endsection

@section('content')
    @php
        $siswaLinkedSet = [];
        $siswaUsernameSet = [];
        $ortuLinkedSet = [];
        foreach ($users as $u) {
            if ($u->peran === 'siswa') {
                if (is_array($u->terhubung_dengan)) {
                    foreach ($u->terhubung_dengan as $sid) {
                        $siswaLinkedSet[(int) $sid] = true;
                    }
                }
                if (!empty($u->nama_pengguna)) {
                    $siswaUsernameSet[strtolower((string) $u->nama_pengguna)] = true;
                }
            } elseif ($u->peran === 'ortu' && is_array($u->terhubung_dengan)) {
                foreach ($u->terhubung_dengan as $sid) {
                    $ortuLinkedSet[(int) $sid] = true;
                }
            }
        }
        $punyaAkunSiswa = function ($s) use ($siswaLinkedSet, $siswaUsernameSet) {
            if (isset($siswaLinkedSet[(int) $s->id])) return true;
            $nisn = strtolower(trim((string) $s->nisn));
            $nis = strtolower(trim((string) $s->nis));
            if ($nisn !== '' && isset($siswaUsernameSet[$nisn])) return true;
            if ($nis !== '' && (isset($siswaUsernameSet[$nis]) || isset($siswaUsernameSet['siswa' . $nis]))) return true;
            return false;
        };
        $punyaAkunOrtu = function ($s) use ($ortuLinkedSet) {
            return isset($ortuLinkedSet[(int) $s->id]);
        };
        $dariDapodik = $siswa->whereNotNull('dapodik_id')->count();
        $tanpaAkun = $siswa->filter(fn($s) => !$punyaAkunSiswa($s))->count();
        $daftarKelas = $siswa->pluck('kelas')->filter()->unique()->sort()->values();
    @endphp

    <div class="grid grid-4" style="margin-bottom:16px">
        <div class="stat"><div class="label">Total siswa</div><div class="value">{{ $siswa->count() }}</div></div>
        <div class="stat ok"><div class="label">Dari Dapodik</div><div class="value">{{ $dariDapodik }}</div></div>
        <div class="stat gold"><div class="label">Aktif</div><div class="value">{{ $siswa->where('aktif', true)->count() }}</div></div>
        <div class="stat"><div class="label">Tanpa akun siswa</div><div class="value">{{ $tanpaAkun }}</div></div>
    </div>

    <div class="card">
        <div class="card-header" style="flex-wrap:wrap;gap:10px">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <h3 style="margin:0;margin-right:8px">DATA SISWA</h3>
                <button type="button" onclick="copySiswaTable()" class="btn btn-xs" style="background:#991B1B;color:#fff;border:none;padding:5px 10px;border-radius:6px">
                    <i class="fas fa-copy"></i> Copy
                </button>
                <button type="button" onclick="exportSiswaCsv()" class="btn btn-xs" style="background:#991B1B;color:#fff;border:none;padding:5px 10px;border-radius:6px">
                    <i class="fas fa-file-excel"></i> Excel
                </button>
                <button type="button" onclick="exportSiswaPdf()" class="btn btn-xs" style="background:#991B1B;color:#fff;border:none;padding:5px 10px;border-radius:6px">
                    <i class="fas fa-file-pdf"></i> Pdf
                </button>
                <button type="button" onclick="window.print()" class="btn btn-xs" style="background:#991B1B;color:#fff;border:none;padding:5px 10px;border-radius:6px">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <select id="filterKelasSiswa" onchange="applySiswaFilter()" style="padding:5px 10px;border:1px solid #E4E7EC;border-radius:6px;font-size:12px">
                    <option value="">Semua Kelas</option>
                    @foreach($daftarKelas as $kls)
                        <option value="{{ strtolower($kls) }}">{{ $kls }}</option>
                    @endforeach
                </select>
                <label style="font-size:12px;color:var(--muted);display:flex;align-items:center;gap:4px">
                    Show
                    <select id="siswaPageSize" onchange="applySiswaFilter()" style="padding:4px 8px;border:1px solid #E4E7EC;border-radius:6px;font-size:12px">
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="999999" selected>Semua</option>
                    </select>
                    rows
                </label>
                <input type="text" id="searchSiswa" placeholder="Cari nama, NIS, atau NISN..." style="padding:6px 12px;border:1px solid #E4E7EC;border-radius:8px;width:220px" oninput="applySiswaFilter()">
            </div>
        </div>
        <div class="card-body tight">
            @if($siswa->count())
                <div class="table-wrapper">
                    <table style="width:100%;border-collapse:collapse" id="tabelSiswa">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:10px 12px;text-align:center;font-size:11.5px;text-transform:uppercase;color:#667085;width:44px">No</th>
                                <th style="padding:10px 12px;text-align:left;font-size:11.5px;text-transform:uppercase;color:#667085">Nama Siswa</th>
                                <th style="padding:10px 12px;text-align:left;font-size:11.5px;text-transform:uppercase;color:#667085">NIS</th>
                                <th style="padding:10px 12px;text-align:left;font-size:11.5px;text-transform:uppercase;color:#667085">NISN</th>
                                <th style="padding:10px 12px;text-align:center;font-size:11.5px;text-transform:uppercase;color:#667085">JK</th>
                                <th style="padding:10px 12px;text-align:left;font-size:11.5px;text-transform:uppercase;color:#667085">TTL</th>
                                <th style="padding:10px 12px;text-align:center;font-size:11.5px;text-transform:uppercase;color:#667085">Agama</th>
                                <th style="padding:10px 12px;text-align:center;font-size:11.5px;text-transform:uppercase;color:#667085">Tingkat</th>
                                <th style="padding:10px 12px;text-align:left;font-size:11.5px;text-transform:uppercase;color:#667085">Kelas</th>
                                <th style="padding:10px 12px;text-align:right;font-size:11.5px;text-transform:uppercase;color:#667085">Edit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($siswa as $idx => $s)
                                @php
                                    $rek = is_array($s->rekaman) ? $s->rekaman : [];
                                    $pel = is_array($rek['pelengkap'] ?? null) ? $rek['pelengkap'] : [];
                                    $tingkat = '-';
                                    if ($s->kelas && preg_match('/([1-6])/', (string) $s->kelas, $m)) {
                                        $tingkat = $m[1];
                                    }
                                    $ttlText = ($s->tempat_lahir ?: ($rek['tempat_lahir'] ?? '-')) . ', ' . ($s->tanggal_lahir ? $s->tanggal_lahir->translatedFormat('d M Y') : ($rek['tanggal_lahir'] ?? '-'));
                                    $agamaText = $s->agama ?: ($rek['agama_id_str'] ?? $rek['agama'] ?? 'Islam');
                                    $siswaPayload = [
                                        'id' => $s->id,
                                        'nama' => $s->nama_peserta_didik,
                                        'nis' => $s->nis ?? '-',
                                        'nisn' => $s->nisn ?? '-',
                                        'nik' => $s->nik ?: ($rek['nik'] ?? '-'),
                                        'jk' => $s->jenis_kelamin === 'L' ? 'Laki-laki' : ($s->jenis_kelamin === 'P' ? 'Perempuan' : ($s->jenis_kelamin ?? '-')),
                                        'tempat_lahir' => $s->tempat_lahir ?: ($rek['tempat_lahir'] ?? ''),
                                        'tanggal_lahir' => $s->tanggal_lahir ? $s->tanggal_lahir->format('Y-m-d') : ($rek['tanggal_lahir'] ?? ''),
                                        'ttl_formatted' => $ttlText,
                                        'agama' => $agamaText,
                                        'kelas' => $s->kelas ?? '-',
                                        'alamat' => $s->alamat ?: ($rek['alamat_jalan'] ?? ''),
                                        'telepon' => $s->telepon ?: ($pel['telepon_ortu'] ?? ($rek['nomor_telepon_seluler'] ?? '')),
                                        'anak_ke' => $s->anak_ke ?: ($pel['anak_ke'] ?? ($rek['anak_keberapa'] ?? '')),
                                        'status_dalam_keluarga' => $pel['status_dalam_keluarga'] ?? 'Anak Kandung',
                                        'asal_sekolah' => $pel['asal_sekolah'] ?? ($rek['sekolah_asal'] ?? ''),
                                        'diterima_di_kelas' => $pel['diterima_di_kelas'] ?? ($s->kelas ?? ''),
                                        'tanggal_diterima' => $pel['tanggal_diterima'] ?? ($rek['tanggal_masuk_sekolah'] ?? ''),
                                        'nama_ayah' => $pel['nama_ayah'] ?? ($rek['nama_ayah'] ?? ''),
                                        'pekerjaan_ayah' => $pel['pekerjaan_ayah'] ?? ($rek['pekerjaan_ayah_id_str'] ?? ''),
                                        'nama_ibu' => $pel['nama_ibu'] ?? ($rek['nama_ibu_kandung'] ?? ($rek['nama_ibu'] ?? '')),
                                        'pekerjaan_ibu' => $pel['pekerjaan_ibu'] ?? ($rek['pekerjaan_ibu_id_str'] ?? ''),
                                        'alamat_ortu' => $pel['alamat_ortu'] ?? ($s->alamat ?: ($rek['alamat_jalan'] ?? '')),
                                        'telepon_ortu' => $pel['telepon_ortu'] ?? ($s->telepon ?: ($rek['nomor_telepon_seluler'] ?? '')),
                                        'nama_wali' => $pel['nama_wali'] ?? ($rek['nama_wali'] ?? ''),
                                        'pekerjaan_wali' => $pel['pekerjaan_wali'] ?? ($rek['pekerjaan_wali_id_str'] ?? ''),
                                        'alamat_wali' => $pel['alamat_wali'] ?? '',
                                        'telepon_wali' => $pel['telepon_wali'] ?? '',
                                    ];
                                @endphp
                                <tr data-kelas="{{ strtolower($s->kelas ?? '') }}">
                                    <td style="padding:10px 12px;border-bottom:1px solid #E4E7EC;text-align:center;font-size:12.5px" class="col-no-siswa">{{ $idx + 1 }}</td>
                                    <td style="padding:10px 12px;border-bottom:1px solid #E4E7EC">
                                        <div style="display:flex;align-items:center;gap:10px">
                                            <span class="avatar" style="width:30px;height:30px;font-size:11px">{{ substr($s->nama_peserta_didik, 0, 2) }}</span>
                                            <div>
                                                <b>{{ $s->nama_peserta_didik }}</b>
                                                @if($s->dapodik_id)
                                                    <span style="display:inline-block;background:#EEF2F9;color:var(--navy);font-size:9px;padding:1px 5px;border-radius:4px;margin-left:4px;font-weight:600" title="Data dari Dapodik">DAPODIK</span>
                                                @endif
                                                <div style="font-size:11px;color:#667085;margin-top:2px">
                                                    @if($punyaAkunSiswa($s))
                                                        <span class="tag tag-ok" style="font-size:9.5px;padding:1px 6px">Akun Siswa</span>
                                                    @else
                                                        <span class="tag tag-mut" style="font-size:9.5px;padding:1px 6px">Tanpa Akun</span>
                                                    @endif
                                                    @if($punyaAkunOrtu($s))
                                                        <span class="tag tag-primary" style="font-size:9.5px;padding:1px 6px">Ortu</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding:10px 12px;border-bottom:1px solid #E4E7EC;font-size:12.5px">{{ $s->nis ?? '-' }}</td>
                                    <td style="padding:10px 12px;border-bottom:1px solid #E4E7EC;font-size:12.5px"><code>{{ $s->nisn ?? '-' }}</code></td>
                                    <td style="padding:10px 12px;border-bottom:1px solid #E4E7EC;text-align:center;font-size:12.5px">{{ $s->jenis_kelamin }}</td>
                                    <td style="padding:10px 12px;border-bottom:1px solid #E4E7EC;font-size:12.5px">{{ $ttlText }}</td>
                                    <td style="padding:10px 12px;border-bottom:1px solid #E4E7EC;text-align:center;font-size:12.5px">{{ $agamaText }}</td>
                                    <td style="padding:10px 12px;border-bottom:1px solid #E4E7EC;text-align:center;font-size:12.5px">{{ $tingkat }}</td>
                                    <td style="padding:10px 12px;border-bottom:1px solid #E4E7EC;font-size:12.5px">{{ $s->kelas ? (str_starts_with($s->kelas, 'Kelas') ? $s->kelas : 'Kelas ' . $s->kelas) : '-' }}</td>
                                    <td style="padding:10px 12px;border-bottom:1px solid #E4E7EC;text-align:right;white-space:nowrap">
                                        <div style="position:relative;display:inline-block" class="opsi-siswa-wrap">
                                            <button type="button" onclick="toggleOpsiSiswa(this, event)" class="btn btn-sm" style="background:#1F3864;color:#fff;border:none;padding:5px 11px;border-radius:6px;font-size:12px">
                                                Opsi <i class="fas fa-caret-down" style="margin-left:4px"></i>
                                            </button>
                                            <div class="opsi-siswa-dropdown" style="display:none;position:absolute;right:0;top:100%;margin-top:4px;background:#fff;border:1px solid #E4E7EC;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,0.14);min-width:150px;z-index:120;text-align:left;overflow:hidden">
                                                <button type="button" onclick='bukaEditSiswa(@json($siswaPayload))' style="display:flex;align-items:center;gap:8px;width:100%;padding:8px 12px;border:none;background:none;font-size:12.5px;cursor:pointer;color:var(--text)">
                                                    <i class="fas fa-edit" style="width:14px;color:var(--navy)"></i> Edit
                                                </button>
                                                <button type="button" onclick='bukaDetailSiswa(@json($siswaPayload))' style="display:flex;align-items:center;gap:8px;width:100%;padding:8px 12px;border:none;background:none;font-size:12.5px;cursor:pointer;color:var(--text)">
                                                    <i class="fas fa-eye" style="width:14px;color:#0284C7"></i> Detail
                                                </button>
                                            </div>
                                        </div>
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

    {{-- Modal Edit Data Siswa & Data Pelengkap Rapor (setara e-Rapor SD /data_siswa) --}}
    <div id="modal_edit_siswa" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.55);z-index:1000;align-items:center;justify-content:center;padding:16px">
        <div style="background:#fff;border-radius:12px;max-width:720px;width:100%;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 20px 50px rgba(0,0,0,0.25);overflow:hidden">
            <div style="padding:14px 20px;background:var(--navy);color:#fff;display:flex;align-items:center;justify-content:space-between">
                <h3 style="margin:0;font-size:16px;color:#fff"><i class="fas fa-user-edit" style="margin-right:8px"></i>Edit Data Siswa & Pelengkap Rapor</h3>
                <button type="button" onclick="tutupModalEditSiswa()" style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer">&times;</button>
            </div>
            <form id="form_edit_siswa" method="POST" action="" style="padding:20px;overflow-y:auto">
                @csrf
                @method('PUT')
                <div style="font-size:12px;font-weight:700;color:var(--navy);text-transform:uppercase;letter-spacing:.6px;margin-bottom:10px;border-bottom:1px solid #E4E7EC;padding-bottom:4px">
                    DATA SISWA
                </div>
                <div class="grid grid-2" style="gap:12px;margin-bottom:12px">
                    <div>
                        <label class="form-label">Nama Siswa</label>
                        <input type="text" id="es_nama" class="form-control" readonly style="background:#F8FAFC">
                    </div>
                    <div>
                        <label class="form-label">Agama</label>
                        <input type="text" name="agama" id="es_agama" class="form-control" placeholder="Islam / Kristen / Katolik / Hindu / Buddha">
                    </div>
                    <div>
                        <label class="form-label">Tempat Lahir</label>
                        <input type="text" name="tempat_lahir" id="es_tempat_lahir" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" id="es_tanggal_lahir" class="form-control">
                    </div>
                </div>
                <div style="margin-bottom:16px">
                    <label class="form-label">Alamat Siswa</label>
                    <textarea name="alamat" id="es_alamat" rows="2" class="form-control" placeholder="Alamat lengkap peserta didik"></textarea>
                </div>

                <div style="font-size:12px;font-weight:700;color:var(--navy);text-transform:uppercase;letter-spacing:.6px;margin-bottom:10px;border-bottom:1px solid #E4E7EC;padding-bottom:4px">
                    DATA PELENGKAP RAPOR
                </div>
                <div class="grid grid-2" style="gap:12px;margin-bottom:12px">
                    <div>
                        <label class="form-label">Status dalam Keluarga</label>
                        <input type="text" name="status_dalam_keluarga" id="es_status_keluarga" class="form-control" placeholder="Anak Kandung">
                    </div>
                    <div>
                        <label class="form-label">Anak ke-</label>
                        <input type="number" name="anak_ke" id="es_anak_ke" class="form-control" min="1" max="30">
                    </div>
                    <div>
                        <label class="form-label">Asal Sekolah (TK / PAUD / SD)</label>
                        <input type="text" name="asal_sekolah" id="es_asal_sekolah" class="form-control" placeholder="Nama TK / Sekolah asal">
                    </div>
                    <div>
                        <label class="form-label">Diterima di Kelas</label>
                        <input type="text" name="diterima_di_kelas" id="es_diterima_kelas" class="form-control" placeholder="Contoh: Kelas 1.A">
                    </div>
                    <div>
                        <label class="form-label">Nama Ayah</label>
                        <input type="text" name="nama_ayah" id="es_nama_ayah" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Pekerjaan Ayah</label>
                        <input type="text" name="pekerjaan_ayah" id="es_pekerjaan_ayah" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Nama Ibu</label>
                        <input type="text" name="nama_ibu" id="es_nama_ibu" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Pekerjaan Ibu</label>
                        <input type="text" name="pekerjaan_ibu" id="es_pekerjaan_ibu" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Alamat Orang Tua</label>
                        <input type="text" name="alamat_ortu" id="es_alamat_ortu" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Telepon Orang Tua</label>
                        <input type="text" name="telepon_ortu" id="es_telepon_ortu" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Nama Wali</label>
                        <input type="text" name="nama_wali" id="es_nama_wali" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Pekerjaan Wali</label>
                        <input type="text" name="pekerjaan_wali" id="es_pekerjaan_wali" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Alamat Wali</label>
                        <input type="text" name="alamat_wali" id="es_alamat_wali" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Telepon Wali</label>
                        <input type="text" name="telepon_wali" id="es_telepon_wali" class="form-control">
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;border-top:1px solid #E4E7EC;padding-top:14px">
                    <button type="button" onclick="tutupModalEditSiswa()" class="btn btn-sm btn-ghost">Close</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-save"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Detail Data Siswa (Read-only, setara e-Rapor SD /data_siswa) --}}
    <div id="modal_detail_siswa" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.55);z-index:1000;align-items:center;justify-content:center;padding:16px">
        <div style="background:#fff;border-radius:12px;max-width:660px;width:100%;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 20px 50px rgba(0,0,0,0.25);overflow:hidden">
            <div style="padding:14px 20px;background:var(--navy);color:#fff;display:flex;align-items:center;justify-content:space-between">
                <h3 style="margin:0;font-size:16px;color:#fff"><i class="fas fa-id-card" style="margin-right:8px"></i>Detail Data Siswa</h3>
                <button type="button" onclick="document.getElementById('modal_detail_siswa').style.display='none'" style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer">&times;</button>
            </div>
            <div style="padding:20px;overflow-y:auto">
                <div class="table-wrapper">
                    <table style="width:100%;border-collapse:collapse;font-size:13px" id="detail_siswa_body"></table>
                </div>
                <div style="display:flex;justify-content:flex-end;margin-top:16px">
                    <button type="button" onclick="document.getElementById('modal_detail_siswa').style.display='none'" class="btn btn-sm btn-primary">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function applySiswaFilter() {
            const keyword = (document.getElementById('searchSiswa')?.value || '').toLowerCase();
            const kelas = (document.getElementById('filterKelasSiswa')?.value || '').toLowerCase();
            const limit = parseInt(document.getElementById('siswaPageSize')?.value || '999999', 10);
            let visible = 0;

            document.querySelectorAll('#tabelSiswa tbody tr').forEach(function (row) {
                const matchKeyword = row.textContent.toLowerCase().includes(keyword);
                const matchKelas = !kelas || (row.getAttribute('data-kelas') === kelas);
                if (matchKeyword && matchKelas && visible < limit) {
                    row.style.display = '';
                    visible++;
                    const noCell = row.querySelector('.col-no-siswa');
                    if (noCell) noCell.textContent = visible;
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function toggleOpsiSiswa(btn, e) {
            e.stopPropagation();
            const menu = btn.parentElement.querySelector('.opsi-siswa-dropdown');
            document.querySelectorAll('.opsi-siswa-dropdown').forEach(d => {
                if (d !== menu) d.style.display = 'none';
            });
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        }
        document.addEventListener('click', function () {
            document.querySelectorAll('.opsi-siswa-dropdown').forEach(d => d.style.display = 'none');
        });

        function bukaEditSiswa(data) {
            document.getElementById('form_edit_siswa').action = '/admin/users/siswa/' + data.id;
            document.getElementById('es_nama').value = data.nama || '';
            document.getElementById('es_agama').value = data.agama || '';
            document.getElementById('es_tempat_lahir').value = data.tempat_lahir || '';
            document.getElementById('es_tanggal_lahir').value = data.tanggal_lahir || '';
            document.getElementById('es_alamat').value = data.alamat || '';
            document.getElementById('es_status_keluarga').value = data.status_dalam_keluarga || 'Anak Kandung';
            document.getElementById('es_anak_ke').value = data.anak_ke || '';
            document.getElementById('es_asal_sekolah').value = data.asal_sekolah || '';
            document.getElementById('es_diterima_kelas').value = data.diterima_di_kelas || '';
            document.getElementById('es_nama_ayah').value = data.nama_ayah || '';
            document.getElementById('es_pekerjaan_ayah').value = data.pekerjaan_ayah || '';
            document.getElementById('es_nama_ibu').value = data.nama_ibu || '';
            document.getElementById('es_pekerjaan_ibu').value = data.pekerjaan_ibu || '';
            document.getElementById('es_alamat_ortu').value = data.alamat_ortu || '';
            document.getElementById('es_telepon_ortu').value = data.telepon_ortu || '';
            document.getElementById('es_nama_wali').value = data.nama_wali || '';
            document.getElementById('es_pekerjaan_wali').value = data.pekerjaan_wali || '';
            document.getElementById('es_alamat_wali').value = data.alamat_wali || '';
            document.getElementById('es_telepon_wali').value = data.telepon_wali || '';
            document.getElementById('modal_edit_siswa').style.display = 'flex';
        }

        function tutupModalEditSiswa() {
            document.getElementById('modal_edit_siswa').style.display = 'none';
        }

        function bukaDetailSiswa(d) {
            const items = [
                ['Nama Peserta Didik', d.nama],
                ['NIS / NISN', (d.nis || '-') + ' / ' + (d.nisn || '-')],
                ['NIK', d.nik || '-'],
                ['Tempat, Tanggal Lahir', d.ttl_formatted || '-'],
                ['Jenis Kelamin', d.jk || '-'],
                ['Agama', d.agama || '-'],
                ['Status dalam Keluarga', d.status_dalam_keluarga || '-'],
                ['Anak ke-', d.anak_ke || '-'],
                ['Alamat Peserta Didik', d.alamat || '-'],
                ['Sekolah Asal', d.asal_sekolah || '-'],
                ['Diterima di Kelas', d.diterima_di_kelas || d.kelas || '-'],
                ['Nama Ayah / Pekerjaan', (d.nama_ayah || '-') + ' (' + (d.pekerjaan_ayah || '-') + ')'],
                ['Nama Ibu / Pekerjaan', (d.nama_ibu || '-') + ' (' + (d.pekerjaan_ibu || '-') + ')'],
                ['Alamat Orang Tua', d.alamat_ortu || '-'],
                ['Telepon Orang Tua', d.telepon_ortu || '-'],
                ['Nama Wali / Pekerjaan', (d.nama_wali || '-') + ' (' + (d.pekerjaan_wali || '-') + ')'],
                ['Alamat & Telepon Wali', (d.alamat_wali || '-') + ' / ' + (d.telepon_wali || '-')]
            ];
            const tbody = document.getElementById('detail_siswa_body');
            tbody.innerHTML = items.map(([k, v], i) =>
                `<tr style="border-bottom:1px solid #F2F4F7">
                    <td style="padding:7px 10px;width:36px;color:#667085">${i + 1}.</td>
                    <td style="padding:7px 10px;width:210px;font-weight:600;color:#344054">${k}</td>
                    <td style="padding:7px 10px;color:#101828">${v || '-'}</td>
                </tr>`
            ).join('');
            document.getElementById('modal_detail_siswa').style.display = 'flex';
        }

        function getSiswaMatrix() {
            const rows = [['No', 'Nama Siswa', 'NIS', 'NISN', 'JK', 'TTL', 'Agama', 'Tingkat', 'Kelas']];
            document.querySelectorAll('#tabelSiswa tbody tr').forEach(tr => {
                if (tr.style.display === 'none') return;
                const tds = tr.querySelectorAll('td');
                if (tds.length >= 9) {
                    rows.push([
                        tds[0].innerText.trim(),
                        tds[1].querySelector('b')?.innerText.trim() || tds[1].innerText.trim(),
                        tds[2].innerText.trim(),
                        tds[3].innerText.trim(),
                        tds[4].innerText.trim(),
                        tds[5].innerText.trim(),
                        tds[6].innerText.trim(),
                        tds[7].innerText.trim(),
                        tds[8].innerText.trim()
                    ]);
                }
            });
            return rows;
        }

        function copySiswaTable() {
            const text = getSiswaMatrix().map(r => r.join('\t')).join('\n');
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text);
                if (typeof showToast === 'function') showToast('Data siswa disalin ke clipboard', 'success');
            }
        }

        function exportSiswaCsv() {
            const csv = getSiswaMatrix().map(r => r.map(c => '"' + String(c).replace(/"/g, '""') + '"').join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'Data_Siswa_SIMANTAP.csv';
            link.click();
        }

        function exportSiswaPdf() {
            const rows = getSiswaMatrix();
            const win = window.open('', '_blank');
            if (!win) { window.print(); return; }
            let html = '<!DOCTYPE html><html><head><title>Data Siswa</title><style>body{font-family:Arial,sans-serif;font-size:12px;padding:20px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #cbd5e1;padding:6px 8px;text-align:left}th{background:#f1f5f9}</style></head><body><h2>Data Siswa</h2><table><thead><tr>';
            rows[0].forEach(h => { html += '<th>' + h + '</th>'; });
            html += '</tr></thead><tbody>';
            rows.slice(1).forEach(r => {
                html += '<tr>';
                r.forEach(c => { html += '<td>' + c + '</td>'; });
                html += '</tr>';
            });
            html += '</tbody></table><script>window.onload=function(){window.print();}<\/script></body></html>';
            win.document.open(); win.document.write(html); win.document.close();
        }
    </script>
@endsection
