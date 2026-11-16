@extends('layouts.admin-with-tabs')

@section('title', 'Data Pengguna - SIMANTAP')

@section('page_title', 'Data Pengguna')
@section('page_subtitle', 'Kelola akun Administrator, Guru, Siswa, Orang Tua, Kepala Sekolah, dan Tendik')

@section('header_actions')
    <button type="button" onclick="bukaModalTambah()" class="btn btn-sm" style="background:#991B1B;color:#fff;border-color:#991B1B">
        <i class="fas fa-user"></i> Tambah Pengguna
    </button>
    <span style="color:var(--muted);font-weight:700">||</span>
    <button type="button" onclick="generateSemuaPengguna()" class="btn btn-sm" style="background:#991B1B;color:#fff;border-color:#991B1B">
        <i class="fas fa-user-plus"></i> Generate Semua Pengguna
    </button>
    <a href="{{ route('admin.users.akun-massal') }}" class="btn btn-sm btn-ghost">Akun Massal</a>
@endsection

@section('content')
    @php
        $adminUsers = $users->where('peran', 'admin');
        $guruUsers = $users->where('peran', 'guru');
        $siswaUsers = $users->where('peran', 'siswa');
        $ortuUsers = $users->where('peran', 'ortu');
        $kepsekUsers = $users->where('peran', 'kepsek');
        $tendikUsers = $users->where('peran', 'tendik');
        $siswaLinkedSet = [];
        $siswaUsernameSet = [];
        foreach ($siswaUsers as $u) {
            if (is_array($u->terhubung_dengan)) {
                foreach ($u->terhubung_dengan as $sId) {
                    $siswaLinkedSet[(int) $sId] = true;
                }
            }
            if (!empty($u->nama_pengguna)) {
                $siswaUsernameSet[strtolower((string) $u->nama_pengguna)] = true;
            }
        }
        $siswaWithoutUser = $siswa->filter(function ($s) use ($siswaLinkedSet, $siswaUsernameSet) {
            if (isset($siswaLinkedSet[(int) $s->id])) return false;
            $nisn = strtolower(trim((string) $s->nisn));
            $nis = strtolower(trim((string) $s->nis));
            if ($nisn !== '' && isset($siswaUsernameSet[$nisn])) return false;
            if ($nis !== '' && (isset($siswaUsernameSet[$nis]) || isset($siswaUsernameSet['siswa' . $nis]))) return false;
            return true;
        });
        $ptkData = isset($ptkList) ? $ptkList : collect();
    @endphp

    <div class="grid grid-4" style="margin-bottom:16px">
        <div class="stat"><div class="label">Total akun</div><div class="value">{{ $users->count() }}</div></div>
        <div class="stat gold"><div class="label">Guru</div><div class="value">{{ $guruUsers->count() }}</div></div>
        <div class="stat ok"><div class="label">Siswa</div><div class="value">{{ $siswaUsers->count() }}</div></div>
        <div class="stat"><div class="label">Tanpa akun</div><div class="value">{{ $siswaWithoutUser->count() }}</div></div>
    </div>

    {{-- Navigasi Tab Role (Administrator, Guru, Siswa, dll.) --}}
    <div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
        <button onclick="switchTab('all')" id="tab-all" class="btn btn-sm tab-btn active-tab">Semua ({{ $users->count() }})</button>
        <button onclick="switchTab('admin')" id="tab-admin" class="btn btn-sm btn-ghost tab-btn">Administrator ({{ $adminUsers->count() }})</button>
        <button onclick="switchTab('guru')" id="tab-guru" class="btn btn-sm btn-ghost tab-btn">Guru ({{ $guruUsers->count() }})</button>
        <button onclick="switchTab('siswa')" id="tab-siswa" class="btn btn-sm btn-ghost tab-btn">Siswa ({{ $siswaUsers->count() }})</button>
        <button onclick="switchTab('ortu')" id="tab-ortu" class="btn btn-sm btn-ghost tab-btn">Orang Tua ({{ $ortuUsers->count() }})</button>
        <button onclick="switchTab('kepsek')" id="tab-kepsek" class="btn btn-sm btn-ghost tab-btn">Kepala Sekolah ({{ $kepsekUsers->count() }})</button>
        <button onclick="switchTab('tendik')" id="tab-tendik" class="btn btn-sm btn-ghost tab-btn">Tendik ({{ $tendikUsers->count() }})</button>
        <button onclick="switchTab('tanpa-akun')" id="tab-tanpa-akun" class="btn btn-sm btn-ghost tab-btn" style="border:1px dashed #B8860B;color:#B8860B">Belum Punya Akun ({{ $siswaWithoutUser->count() }})</button>

        @if($guruUsers->count() && !\App\Modules\Tenant\Contexts\TenantContext::check())
            <button onclick="hapusSemua('guru', {{ $guruUsers->count() }})" class="btn btn-sm btn-ghost" style="color:#B42318;border:1px solid #B42318;margin-left:auto">Hapus Semua Guru</button>
        @endif
        @if($siswaUsers->count() && !\App\Modules\Tenant\Contexts\TenantContext::check())
            <button onclick="hapusSemua('siswa', {{ $siswaUsers->count() }})" class="btn btn-sm btn-ghost" style="color:#B42318;border:1px solid #B42318">Hapus Semua Siswa</button>
        @endif
        @if($ortuUsers->count() && !\App\Modules\Tenant\Contexts\TenantContext::check())
            <button onclick="hapusSemua('ortu', {{ $ortuUsers->count() }})" class="btn btn-sm btn-ghost" style="color:#B42318;border:1px solid #B42318">Hapus Semua Orang Tua</button>
        @endif
    </div>

    <div class="card">
        <div class="card-header" style="flex-wrap:wrap;gap:10px">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <h3 id="tableTitle" style="margin:0;margin-right:8px">Semua Akun</h3>
                <button type="button" onclick="copyTableData()" class="btn btn-xs" style="background:#991B1B;color:#fff;border:none;padding:5px 10px;border-radius:6px">
                    <i class="fas fa-copy"></i> Copy
                </button>
                <button type="button" onclick="exportTableCsv()" class="btn btn-xs" style="background:#991B1B;color:#fff;border:none;padding:5px 10px;border-radius:6px">
                    <i class="fas fa-file-excel"></i> Excel
                </button>
                <button type="button" onclick="exportTablePdf()" class="btn btn-xs" style="background:#991B1B;color:#fff;border:none;padding:5px 10px;border-radius:6px">
                    <i class="fas fa-file-pdf"></i> Pdf
                </button>
                <button type="button" onclick="printTableData()" class="btn btn-xs" style="background:#991B1B;color:#fff;border:none;padding:5px 10px;border-radius:6px">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <label style="font-size:12px;color:var(--muted);display:flex;align-items:center;gap:6px;font-weight:500">
                    Tampilkan
                    <select id="pageSizeSelect" onchange="onChangePageSize()" style="padding:5px 10px;border:1px solid #CBD5E1;border-radius:6px;font-size:12.5px;font-weight:600;color:var(--text);background:#fff;cursor:pointer">
                        <option value="10" selected>10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="999999">Semua</option>
                    </select>
                    data
                </label>
                <input type="text" id="searchUser" placeholder="Cari username, nama, email..." style="padding:6px 12px;border:1px solid #E4E7EC;border-radius:8px;width:220px" oninput="filterUsers(this.value)">
            </div>
        </div>
        <div class="card-body tight">
            <div class="table-wrapper">
                <table style="width:100%;border-collapse:collapse" id="userTable">
                    <thead>
                        <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                            <th style="padding:10px 12px;text-align:center;font-size:11.5px;text-transform:uppercase;color:#667085;width:48px">No</th>
                            <th style="padding:10px 12px;text-align:left;font-size:11.5px;text-transform:uppercase;color:#667085">Username</th>
                            <th style="padding:10px 12px;text-align:left;font-size:11.5px;text-transform:uppercase;color:#667085">Nama Pengguna</th>
                            <th style="padding:10px 12px;text-align:left;font-size:11.5px;text-transform:uppercase;color:#667085">e-mail</th>
                            <th style="padding:10px 12px;text-align:center;font-size:11.5px;text-transform:uppercase;color:#667085">Level</th>
                            <th style="padding:10px 12px;text-align:center;font-size:11.5px;text-transform:uppercase;color:#667085">Status Aktif</th>
                            <th style="padding:10px 12px;text-align:center;font-size:11.5px;text-transform:uppercase;color:#667085">Login Terakhir</th>
                            <th style="padding:10px 12px;text-align:center;font-size:11.5px;text-transform:uppercase;color:#667085">Status Online</th>
                            <th style="padding:10px 12px;text-align:right;font-size:11.5px;text-transform:uppercase;color:#667085">Opsi</th>
                        </tr>
                    </thead>
                    <tbody id="userBody">
                        @php
                            $peranLabels = ['admin' => 'Administrator', 'guru' => 'Guru', 'siswa' => 'Siswa', 'ortu' => 'Orang Tua', 'kepsek' => 'Kepala Sekolah', 'tendik' => 'Tenaga Kependidikan'];
                            $peranColors = ['admin' => 'tag-bad', 'guru' => 'tag-gold', 'siswa' => 'tag-ok', 'ortu' => 'tag-mut', 'kepsek' => 'tag-gold', 'tendik' => 'tag-primary'];
                        @endphp
                        @foreach($users as $idx => $u)
                            @php
                                $rawEmail = $u->email ?: ($u->nama_pengguna . '@simantap.sch.id');
                                $emailTampil = str_replace('@erapor.id', '@simantap.sch.id', $rawEmail);
                                $isOnline = $u->terakhir_masuk && $u->terakhir_masuk->greaterThanOrEqualTo(now()->subMinutes(15));
                            @endphp
                            <tr data-role="{{ $u->peran }}" data-search="{{ strtolower($u->nama_lengkap . ' ' . $u->nama_pengguna . ' ' . $emailTampil) }}" style="{{ $idx >= 10 ? 'display:none' : '' }}">
                                <td style="padding:10px 12px;border-bottom:1px solid #E4E7EC;text-align:center;font-size:12.5px" class="col-no">{{ $idx + 1 }}</td>
                                <td style="padding:10px 12px;border-bottom:1px solid #E4E7EC">
                                    <code style="background:#EEF2F9;padding:2px 8px;border-radius:6px;font-size:12.5px;font-weight:700">{{ $u->nama_pengguna }}</code>
                                </td>
                                <td style="padding:10px 12px;border-bottom:1px solid #E4E7EC">
                                    <div style="display:flex;align-items:center;gap:9px">
                                        <span class="avatar" style="width:30px;height:30px;font-size:11px">{{ substr($u->nama_lengkap, 0, 2) }}</span>
                                        <div>
                                            <b>{{ $u->nama_lengkap }}</b>
                                            @if($u->dapodik_id)
                                                <span style="display:inline-block;background:#EEF2F9;color:var(--navy);font-size:9px;padding:1px 5px;border-radius:4px;font-weight:600;margin-left:4px" title="Data dari Dapodik">DAPODIK</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td style="padding:10px 12px;border-bottom:1px solid #E4E7EC;font-size:12.5px;color:var(--muted)">{{ $emailTampil }}</td>
                                <td style="padding:10px 12px;border-bottom:1px solid #E4E7EC;text-align:center">
                                    <span class="tag {{ $peranColors[$u->peran] ?? 'tag-mut' }}">{{ $peranLabels[$u->peran] ?? $u->peran }}</span>
                                </td>
                                <td style="padding:10px 12px;border-bottom:1px solid #E4E7EC;text-align:center">
                                    @if($u->aktif)
                                        <span class="tag tag-ok">Aktif</span>
                                    @else
                                        <span class="tag tag-bad">Tidak Aktif</span>
                                    @endif
                                </td>
                                <td style="padding:10px 12px;border-bottom:1px solid #E4E7EC;text-align:center;font-size:12px;color:var(--muted)">
                                    {{ $u->terakhir_masuk ? $u->terakhir_masuk->format('Y-m-d H:i:s') : '-' }}
                                </td>
                                <td style="padding:10px 12px;border-bottom:1px solid #E4E7EC;text-align:center">
                                    @if($isOnline)
                                        <span class="tag tag-ok">Online</span>
                                    @else
                                        <span class="tag tag-mut">Offline</span>
                                    @endif
                                </td>
                                <td style="padding:10px 12px;border-bottom:1px solid #E4E7EC;text-align:right;white-space:nowrap">
                                    <div style="position:relative;display:inline-block" class="opsi-wrap">
                                        <button type="button" onclick="toggleOpsiMenu(this, event)" class="btn btn-sm" style="background:#0284C7;color:#fff;border:none;padding:5px 11px;border-radius:6px;font-size:12px">
                                            Opsi <i class="fas fa-caret-down" style="margin-left:4px"></i>
                                        </button>
                                        <div class="opsi-dropdown" style="display:none;position:absolute;right:0;top:100%;margin-top:4px;background:#fff;border:1px solid #E4E7EC;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,0.14);min-width:175px;z-index:120;text-align:left;overflow:hidden">
                                            <button type="button" onclick="resetPassPengguna('{{ $u->id }}', @js($u->nama_lengkap))" style="display:flex;align-items:center;gap:8px;width:100%;padding:8px 12px;border:none;background:none;font-size:12.5px;cursor:pointer;color:var(--text)">
                                                <i class="fas fa-key" style="width:14px;color:#D97706"></i> Reset Password
                                            </button>
                                            @if($u->nama_pengguna !== 'admin' && $u->id !== auth()->id())
                                                <button type="button" onclick="toggleAktifPengguna('{{ $u->id }}', @js($u->nama_lengkap), {{ $u->aktif ? 'true' : 'false' }})" style="display:flex;align-items:center;gap:8px;width:100%;padding:8px 12px;border:none;background:none;font-size:12.5px;cursor:pointer;color:var(--text)">
                                                    @if($u->aktif)
                                                        <i class="fas fa-ban" style="width:14px;color:#DC2626"></i> Non Aktifkan
                                                    @else
                                                        <i class="fas fa-check" style="width:14px;color:#059669"></i> Aktifkan
                                                    @endif
                                                </button>
                                            @endif
                                            @if($u->peran === 'guru' && $u->aktif)
                                                <form method="POST" action="{{ route('admin.users.impersonate', $u) }}" style="margin:0">
                                                    @csrf
                                                    <button type="submit" style="display:flex;align-items:center;gap:8px;width:100%;padding:8px 12px;border:none;background:none;font-size:12.5px;cursor:pointer;color:#059669;font-weight:600">
                                                        <i class="fas fa-sign-in-alt" style="width:14px"></i> Login Akun Guru
                                                    </button>
                                                </form>
                                            @endif
                                            <a href="{{ route('admin.users.edit', $u) }}" style="display:flex;align-items:center;gap:8px;padding:8px 12px;font-size:12.5px;color:var(--text);text-decoration:none">
                                                <i class="fas fa-edit" style="width:14px;color:var(--navy)"></i> Ubah Detail
                                            </a>
                                            @if($u->nama_pengguna !== 'admin')
                                                <div style="border-top:1px solid #F2F4F7"></div>
                                                <button type="button" onclick="hapusUser('{{ $u->id }}', @js($u->nama_lengkap))" style="display:flex;align-items:center;gap:8px;width:100%;padding:8px 12px;border:none;background:none;font-size:12.5px;cursor:pointer;color:#B42318">
                                                    <i class="fas fa-trash" style="width:14px"></i> Hapus Pengguna
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div id="emptyState" style="display:none;padding:40px;text-align:center;color:#667085">
                <div style="font-size:40px;margin-bottom:8px">👤</div>
                <b>Tidak ada data ditemukan</b>
            </div>

            <div id="paginationFooter" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;padding:12px 16px;border-top:1px solid #E4E7EC;background:#FAFBFD">
                <div id="paginationInfo" style="font-size:12.5px;color:#667085">
                    Menampilkan <b>1</b> sampai <b>{{ min(10, $users->count()) }}</b> dari <b>{{ $users->count() }}</b> data
                </div>
                <div id="paginationControls" style="display:flex;align-items:center;gap:4px;flex-wrap:wrap"></div>
            </div>
        </div>
    </div>

    {{-- Kartu Siswa Belum Punya Akun --}}
    <div class="card" style="margin-top:16px" id="siswaTanpaAkunCard">
        <div class="card-header">
            <h3>Siswa Belum Punya Akun</h3>
            <a href="{{ route('admin.users.akun-massal') }}" class="btn btn-sm" style="background:#B8860B;color:#fff">Buat Akun Massal</a>
        </div>
        <div class="card-body tight">
            @if($siswaWithoutUser->count())
                <div class="table-wrapper">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Nama Siswa</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">NIS / NISN</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Kelas</th>
                                <th style="padding:11px 14px;text-align:right;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($siswaWithoutUser as $s)
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        <div style="display:flex;align-items:center;gap:11px">
                                            <span class="avatar">{{ substr($s->nama_peserta_didik, 0, 2) }}</span>
                                            <b>{{ $s->nama_peserta_didik }}</b>
                                        </div>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC"><code style="background:#EEF2F9;padding:2px 8px;border-radius:6px;font-size:12.5px">{{ $s->nisn ?? $s->nis }}</code></td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">{{ $s->kelas ?? '-' }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:right">
                                        <a href="{{ route('admin.users.create') }}?peran=siswa&siswa_id={{ $s->id }}" class="btn btn-ghost btn-sm">Buat Akun</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty" style="padding:30px"><div class="icon">✅</div><b>Semua siswa sudah punya akun</b></div>
            @endif
        </div>
    </div>

    {{-- Modal #modal_pengguna: Tambah Data Pengguna (ala e-Rapor SD) --}}
    <div id="modal_pengguna" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.55);z-index:1000;align-items:center;justify-content:center;padding:16px">
        <div style="background:#fff;border-radius:12px;max-width:520px;width:100%;box-shadow:0 20px 50px rgba(0,0,0,0.25);overflow:hidden">
            <div style="padding:14px 20px;background:var(--navy);color:#fff;display:flex;align-items:center;justify-content:space-between">
                <h3 style="margin:0;font-size:16px;color:#fff"><i class="fas fa-user-plus" style="margin-right:8px"></i>Tambah Data Pengguna</h3>
                <button type="button" onclick="tutupModalTambah()" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.users.store') }}" style="padding:20px">
                @csrf
                <div class="form-group" style="margin-bottom:12px">
                    <label class="form-label">Level Pengguna</label>
                    <select name="peran" id="modal_level" class="form-control" onchange="onChangeLevelModal(this.value)" required>
                        <option value="">-- Pilih Level Pengguna --</option>
                        <option value="admin">Administrator</option>
                        <option value="guru">Guru</option>
                        <option value="siswa">Siswa</option>
                        <option value="ortu">Orang Tua</option>
                        <option value="kepsek">Kepala Sekolah</option>
                        <option value="tendik">Tenaga Kependidikan</option>
                    </select>
                </div>

                {{-- Pilih Guru dari Data PTK --}}
                <div id="wrap_pilih_guru" style="display:none;margin-bottom:12px">
                    <label class="form-label">Pilih Guru (Data PTK)</label>
                    <select id="select_guru_modal" class="form-control" onchange="onSelectGuruModal(this)">
                        <option value="">-- Pilih Guru --</option>
                        @foreach($ptkData as $ptk)
                            @php
                                $unameGuru = $ptk->nip ?: ($ptk->nuptk ?: '');
                                $namaGuruFull = trim(($ptk->gelar_depan ? $ptk->gelar_depan . ' ' : '') . $ptk->nama . ($ptk->gelar_belakang ? ', ' . $ptk->gelar_belakang : ''));
                            @endphp
                            <option value="{{ $ptk->id }}" data-username="{{ $unameGuru }}" data-nama="{{ $namaGuruFull ?: $ptk->nama }}">
                                {{ $namaGuruFull ?: $ptk->nama }} ({{ $unameGuru ?: 'Tanpa NIP' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Pilih Siswa dari Data Siswa --}}
                <div id="wrap_pilih_siswa" style="display:none;margin-bottom:12px">
                    <label class="form-label">Pilih Peserta Didik</label>
                    <select id="select_siswa_modal" class="form-control" onchange="onSelectSiswaModal(this)">
                        <option value="">-- Pilih Siswa --</option>
                        @foreach($siswa as $s)
                            @php $unameSiswa = $s->nisn ?: $s->nis; @endphp
                            <option value="{{ $s->id }}" data-username="{{ $unameSiswa }}" data-nama="{{ $s->nama_peserta_didik }}" data-kelas="{{ $s->kelas }}">
                                {{ $s->nama_peserta_didik }} — {{ $s->kelas ?? '-' }} ({{ $unameSiswa }})
                            </option>
                        @endforeach
                    </select>
                    <input type="hidden" name="terhubung_dengan[]" id="modal_terhubung_siswa" disabled>
                </div>

                <div class="form-group" style="margin-bottom:12px">
                    <label class="form-label">Username</label>
                    <input type="text" name="nama_pengguna" id="modal_username" class="form-control" placeholder="NIP / NISN / Username" required>
                </div>

                <div class="form-group" style="margin-bottom:12px">
                    <label class="form-label">Password (Min. 8 karakter)</label>
                    <input type="password" name="kata_sandi" id="modal_password" class="form-control" minlength="8" placeholder="Minimal 8 karakter" required>
                </div>

                <div class="form-group" style="margin-bottom:12px">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" id="modal_nama" class="form-control" placeholder="Nama lengkap pengguna" required>
                </div>

                <div class="form-group" style="margin-bottom:16px">
                    <label class="form-label">e-mail (Opsional)</label>
                    <input type="email" name="email" id="modal_email" class="form-control" placeholder="contoh@simantap.sch.id">
                </div>

                <input type="hidden" name="kelas_mata_pelajaran" id="modal_kelas">
                <input type="hidden" name="aktif" value="1">

                <div style="display:flex;justify-content:flex-end;gap:8px;border-top:1px solid #E4E7EC;padding-top:14px">
                    <button type="button" onclick="tutupModalTambah()" class="btn btn-sm" style="background:#DC2626;color:#fff;border:none">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Hasil Reset Password Instan (ala SweetAlert2 e-Rapor SD) --}}
    <div id="modal_reset_pass" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.55);z-index:1000;align-items:center;justify-content:center;padding:16px">
        <div style="background:#fff;border-radius:12px;max-width:460px;width:100%;padding:24px;text-align:center;box-shadow:0 20px 50px rgba(0,0,0,0.25)">
            <div style="width:56px;height:56px;border-radius:50%;background:#ECFDF5;color:#059669;display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 12px">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3 style="margin:0 0 8px;font-size:18px">Ubah Password!</h3>
            <p id="reset_pass_text" style="font-size:13.5px;color:var(--text);line-height:1.6;margin-bottom:14px"></p>
            <div style="background:#F8FAFC;border:1.5px dashed var(--navy);border-radius:8px;padding:10px;margin-bottom:16px;display:flex;align-items:center;justify-content:center;gap:10px">
                <code id="reset_pass_code" style="font-size:18px;font-weight:800;color:var(--navy);letter-spacing:1px"></code>
                <button type="button" onclick="salinPasswordBaru()" class="btn btn-xs btn-ghost" title="Salin Password">
                    <i class="fas fa-copy"></i> Salin
                </button>
            </div>
            <button type="button" onclick="document.getElementById('modal_reset_pass').style.display='none'" class="btn btn-primary" style="min-width:120px;justify-content:center">
                OK, Sudah Dicatat
            </button>
        </div>
    </div>

    {{-- Modal Konfirmasi & Hasil Generate Semua Pengguna (ala SweetAlert2 e-Rapor SD) --}}
    <div id="modal_generate_semua" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.55);z-index:1000;align-items:center;justify-content:center;padding:16px">
        <div style="background:#fff;border-radius:12px;max-width:480px;width:100%;padding:24px;text-align:center;box-shadow:0 20px 50px rgba(0,0,0,0.25)">
            <div id="gen_icon_wrap" style="width:56px;height:56px;border-radius:50%;background:#FEF3C7;color:#D97706;display:flex;align-items:center;justify-content:center;font-size:26px;margin:0 auto 12px">
                <i id="gen_icon" class="fas fa-exclamation-triangle"></i>
            </div>
            <h3 id="gen_title" style="margin:0 0 8px;font-size:18px">Apakah Anda Yakin?</h3>
            <p id="gen_desc" style="font-size:13.5px;color:var(--text);line-height:1.6;margin-bottom:18px">
                Semua Data Pengguna untuk <b>Guru (PTK)</b> dan <b>Siswa</b> yang belum memiliki akun akan dibuat otomatis sesuai <b>NIP</b>, <b>NISN</b>, atau nama di database.
            </p>
            <div id="gen_actions_confirm" style="display:flex;justify-content:center;gap:10px">
                <button type="button" id="btn_ya_generate" onclick="eksekusiGenerateSemua()" class="btn btn-primary" style="min-width:120px;justify-content:center">
                    <i class="fas fa-check"></i> Ya, Buat!
                </button>
                <button type="button" id="btn_batal_generate" onclick="tutupModalGenerate(true)" class="btn" style="background:#DC2626;color:#fff;border:none;min-width:110px;justify-content:center">
                    <i class="fas fa-times"></i> Batal!
                </button>
            </div>
            <div id="gen_actions_done" style="display:none;justify-content:center">
                <button type="button" onclick="location.reload()" class="btn btn-primary" style="min-width:140px;justify-content:center">
                    OK, Muat Ulang
                </button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const tabTitles = {
        'all': 'Semua Akun',
        'admin': 'Data Pengguna — Administrator',
        'guru': 'Data Pengguna — Guru',
        'siswa': 'Data Pengguna — Siswa',
        'ortu': 'Data Pengguna — Orang Tua',
        'kepsek': 'Data Pengguna — Kepala Sekolah',
        'tendik': 'Data Pengguna — Tenaga Kependidikan',
        'tanpa-akun': 'Siswa Belum Punya Akun'
    };

    let currentPage = 1;

    function switchTab(tab) {
        document.querySelectorAll('.tab-btn').forEach(b => {
            b.classList.remove('active-tab');
            b.classList.add('btn-ghost');
        });
        const activeBtn = document.getElementById('tab-' + tab);
        if (activeBtn) {
            activeBtn.classList.add('active-tab');
            activeBtn.classList.remove('btn-ghost');
        }

        document.getElementById('tableTitle').textContent = tabTitles[tab] || 'Semua Akun';
        currentPage = 1;

        if (tab === 'tanpa-akun') {
            document.querySelectorAll('#userBody tr').forEach(r => r.style.display = 'none');
            document.getElementById('userTable').style.display = 'none';
            document.getElementById('paginationFooter').style.display = 'none';
            document.getElementById('siswaTanpaAkunCard').style.display = '';
            document.getElementById('searchUser').style.display = 'none';
        } else {
            document.getElementById('userTable').style.display = '';
            document.getElementById('paginationFooter').style.display = 'flex';
            document.getElementById('siswaTanpaAkunCard').style.display = 'none';
            document.getElementById('searchUser').style.display = '';
            applyFilterAndLimit();
        }
    }

    function filterUsers() {
        currentPage = 1;
        applyFilterAndLimit();
    }

    function onChangePageSize() {
        currentPage = 1;
        applyFilterAndLimit();
    }

    function goToPage(page) {
        currentPage = page;
        applyFilterAndLimit();
    }

    function applyFilterAndLimit() {
        const s = (document.getElementById('searchUser')?.value || '').toLowerCase();
        const activeTab = document.querySelector('.active-tab')?.id?.replace('tab-', '') || 'all';
        const limit = parseInt(document.getElementById('pageSizeSelect')?.value || '10', 10);

        const allRows = Array.from(document.querySelectorAll('#userBody tr'));
        const matchedRows = [];

        allRows.forEach(r => {
            const roleMatch = activeTab === 'all' || r.dataset.role === activeTab;
            const searchMatch = (r.dataset.search || '').includes(s);
            if (roleMatch && searchMatch) {
                matchedRows.push(r);
            } else {
                r.style.display = 'none';
            }
        });

        const totalMatched = matchedRows.length;
        const totalPages = Math.max(1, Math.ceil(totalMatched / limit));
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const startIdx = (currentPage - 1) * limit;
        const endIdx = Math.min(startIdx + limit, totalMatched);

        matchedRows.forEach((r, idx) => {
            if (idx >= startIdx && idx < endIdx) {
                r.style.display = '';
                const noCell = r.querySelector('.col-no');
                if (noCell) noCell.textContent = idx + 1;
            } else {
                r.style.display = 'none';
            }
        });

        document.getElementById('emptyState').style.display = totalMatched === 0 ? 'block' : 'none';
        renderPagination(totalMatched, startIdx, endIdx, totalPages, limit);
    }

    function renderPagination(totalMatched, startIdx, endIdx, totalPages, limit) {
        const infoEl = document.getElementById('paginationInfo');
        const ctrlEl = document.getElementById('paginationControls');
        if (!infoEl || !ctrlEl) return;

        if (totalMatched === 0) {
            infoEl.innerHTML = 'Menampilkan <b>0</b> data';
            ctrlEl.innerHTML = '';
            return;
        }

        infoEl.innerHTML = 'Menampilkan <b>' + (startIdx + 1) + '</b> sampai <b>' + endIdx + '</b> dari <b>' + totalMatched + '</b> data';

        if (limit >= 999999 || totalPages <= 1) {
            ctrlEl.innerHTML = '';
            return;
        }

        let html = '';
        const prevDisabled = currentPage <= 1;
        html += '<button type="button" onclick="if(' + (!prevDisabled) + ') goToPage(' + (currentPage - 1) + ')" class="btn btn-xs btn-ghost" ' + (prevDisabled ? 'disabled style="opacity:0.45;cursor:not-allowed"' : '') + '>Sebelumnya</button>';

        const pages = [];
        for (let p = 1; p <= totalPages; p++) {
            if (p === 1 || p === totalPages || (p >= currentPage - 2 && p <= currentPage + 2)) {
                pages.push(p);
            } else if (pages[pages.length - 1] !== '...') {
                pages.push('...');
            }
        }

        pages.forEach(p => {
            if (p === '...') {
                html += '<span style="padding:2px 6px;color:#667085;font-size:12px">...</span>';
            } else {
                const isActive = p === currentPage;
                html += '<button type="button" onclick="goToPage(' + p + ')" class="btn btn-xs ' + (isActive ? '' : 'btn-ghost') + '" style="' + (isActive ? 'background:#1F3864;color:#fff;border-color:#1F3864;font-weight:700;' : '') + 'min-width:28px;justify-content:center">' + p + '</button>';
            }
        });

        const nextDisabled = currentPage >= totalPages;
        html += '<button type="button" onclick="if(' + (!nextDisabled) + ') goToPage(' + (currentPage + 1) + ')" class="btn btn-xs btn-ghost" ' + (nextDisabled ? 'disabled style="opacity:0.45;cursor:not-allowed"' : '') + '>Berikutnya</button>';

        ctrlEl.innerHTML = html;
    }

    document.addEventListener('DOMContentLoaded', function() {
        applyFilterAndLimit();
    });

    // Dropdown Opsi per baris
    function toggleOpsiMenu(btn, e) {
        e.stopPropagation();
        const menu = btn.parentElement.querySelector('.opsi-dropdown');
        document.querySelectorAll('.opsi-dropdown').forEach(d => {
            if (d !== menu) d.style.display = 'none';
        });
        menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
    }
    document.addEventListener('click', function() {
        document.querySelectorAll('.opsi-dropdown').forEach(d => d.style.display = 'none');
    });

    // Modal Tambah Pengguna
    function bukaModalTambah() {
        document.getElementById('modal_pengguna').style.display = 'flex';
    }
    function tutupModalTambah() {
        document.getElementById('modal_pengguna').style.display = 'none';
    }
    function onChangeLevelModal(level) {
        document.getElementById('wrap_pilih_guru').style.display = level === 'guru' ? 'block' : 'none';
        document.getElementById('wrap_pilih_siswa').style.display = level === 'siswa' ? 'block' : 'none';
        const siswaInput = document.getElementById('modal_terhubung_siswa');
        if (level !== 'siswa') {
            siswaInput.disabled = true;
            siswaInput.value = '';
        }
    }
    function onSelectGuruModal(sel) {
        const opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.value) return;
        const uname = opt.getAttribute('data-username') || '';
        const nama = opt.getAttribute('data-nama') || '';
        document.getElementById('modal_username').value = uname;
        document.getElementById('modal_nama').value = nama;
        if (uname) document.getElementById('modal_email').value = uname + '@simantap.sch.id';
    }
    function onSelectSiswaModal(sel) {
        const opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.value) return;
        const uname = opt.getAttribute('data-username') || '';
        const nama = opt.getAttribute('data-nama') || '';
        const kelas = opt.getAttribute('data-kelas') || '';
        document.getElementById('modal_username').value = uname;
        document.getElementById('modal_nama').value = nama;
        document.getElementById('modal_kelas').value = kelas;
        if (uname) document.getElementById('modal_email').value = uname + '@simantap.sch.id';
        const siswaInput = document.getElementById('modal_terhubung_siswa');
        siswaInput.disabled = false;
        siswaInput.value = opt.value;
    }

    // Generate Semua Pengguna
    function generateSemuaPengguna() {
        const modal = document.getElementById('modal_generate_semua');
        document.getElementById('gen_icon_wrap').style.background = '#FEF3C7';
        document.getElementById('gen_icon_wrap').style.color = '#D97706';
        document.getElementById('gen_icon').className = 'fas fa-exclamation-triangle';
        document.getElementById('gen_title').textContent = 'Apakah Anda Yakin?';
        document.getElementById('gen_desc').innerHTML = 'Semua Data Pengguna untuk <b>Guru (PTK)</b> dan <b>Siswa</b> yang belum memiliki akun akan dibuat otomatis sesuai <b>NIP</b>, <b>NISN</b>, atau nama di database.';
        document.getElementById('gen_actions_confirm').style.display = 'flex';
        document.getElementById('gen_actions_done').style.display = 'none';
        const btnYa = document.getElementById('btn_ya_generate');
        btnYa.disabled = false;
        btnYa.innerHTML = '<i class="fas fa-check"></i> Ya, Buat!';
        modal.style.display = 'flex';
    }

    function tutupModalGenerate(cancelled) {
        document.getElementById('modal_generate_semua').style.display = 'none';
        if (cancelled && typeof showToast === 'function') {
            showToast('Dibatalkan - Batal membuat data pengguna', 'warning');
        }
    }

    function eksekusiGenerateSemua() {
        const btnYa = document.getElementById('btn_ya_generate');
        const btnBatal = document.getElementById('btn_batal_generate');
        btnYa.disabled = true;
        btnBatal.disabled = true;
        btnYa.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';

        fetch('{{ route("admin.users.generate-semua") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(async r => {
            const d = await r.json().catch(() => ({}));
            if (!r.ok || d.success === false) {
                throw new Error(d.message || ('Gagal memproses (HTTP ' + r.status + ')'));
            }
            return d;
        }).then(d => {
            document.getElementById('gen_icon_wrap').style.background = '#ECFDF5';
            document.getElementById('gen_icon_wrap').style.color = '#059669';
            document.getElementById('gen_icon').className = 'fas fa-check-circle';
            document.getElementById('gen_title').textContent = 'Generate Pengguna Selesai!';
            document.getElementById('gen_desc').textContent = d.message || 'Generate selesai.';
            document.getElementById('gen_actions_confirm').style.display = 'none';
            document.getElementById('gen_actions_done').style.display = 'flex';
            btnBatal.disabled = false;
            if (typeof showToast === 'function') showToast(d.message || 'Generate selesai', 'success');
        }).catch(err => {
            btnYa.disabled = false;
            btnBatal.disabled = false;
            btnYa.innerHTML = '<i class="fas fa-check"></i> Ya, Buat!';
            alert('Gagal generate pengguna: ' + err.message);
        });
    }

    // Reset Password Instan
    function resetPassPengguna(id, nama) {
        if (!confirm('Reset password untuk pengguna ' + nama + '?')) return;
        fetch('/admin/users/' + id + '/reset-password', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        }).then(r => r.json()).then(d => {
            if (d.success) {
                document.getElementById('reset_pass_text').textContent = d.message;
                document.getElementById('reset_pass_code').textContent = d.password_baru;
                document.getElementById('modal_reset_pass').style.display = 'flex';
                if (typeof showToast === 'function') showToast('Password Baru : ' + d.password_baru, 'success');
            } else {
                alert(d.message || 'Gagal mereset password.');
            }
        }).catch(err => alert('Terjadi kesalahan: ' + err.message));
    }

    function salinPasswordBaru() {
        const code = document.getElementById('reset_pass_code').textContent;
        if (navigator.clipboard && code) {
            navigator.clipboard.writeText(code);
            if (typeof showToast === 'function') showToast('Password disalin: ' + code, 'success');
        }
    }

    // Toggle Aktif / Non-Aktifkan (Blokir)
    function toggleAktifPengguna(id, nama, isAktif) {
        const aksi = isAktif ? 'Non-aktifkan (Blokir)' : 'Aktifkan kembali';
        if (!confirm(aksi + ' pengguna ' + nama + '?')) return;
        fetch('/admin/users/' + id + '/toggle-aktif', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        }).then(r => r.json()).then(d => {
            alert(d.message || 'Status diperbarui.');
            if (d.success) location.reload();
        }).catch(err => alert('Terjadi kesalahan: ' + err.message));
    }

    // Copy, Export Excel (CSV), dan Print
    function getVisibleTableMatrix() {
        const rows = [['No', 'Username', 'Nama Pengguna', 'Email', 'Level', 'Status Aktif', 'Login Terakhir', 'Status Online']];
        document.querySelectorAll('#userBody tr').forEach(tr => {
            if (tr.style.display === 'none') return;
            const tds = tr.querySelectorAll('td');
            if (tds.length >= 8) {
                rows.push([
                    tds[0].innerText.trim(),
                    tds[1].innerText.trim(),
                    tds[2].innerText.replace(/\s+/g, ' ').trim(),
                    tds[3].innerText.trim(),
                    tds[4].innerText.trim(),
                    tds[5].innerText.trim(),
                    tds[6].innerText.trim(),
                    tds[7].innerText.trim()
                ]);
            }
        });
        return rows;
    }

    function copyTableData() {
        const text = getVisibleTableMatrix().map(r => r.join('\t')).join('\n');
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text);
            if (typeof showToast === 'function') showToast('Data pengguna disalin ke clipboard', 'success');
        }
    }

    function exportTableCsv() {
        const csv = getVisibleTableMatrix().map(r => r.map(c => '"' + String(c).replace(/"/g, '""') + '"').join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'Data_Pengguna_SIMANTAP.csv';
        link.click();
    }

    function exportTablePdf() {
        const rows = getVisibleTableMatrix();
        const title = document.getElementById('tableTitle')?.textContent || 'Data Pengguna SIMANTAP';
        const win = window.open('', '_blank');
        if (!win) {
            window.print();
            return;
        }
        let html = '<!DOCTYPE html><html><head><title>' + title + '</title>';
        html += '<style>body{font-family:Arial,sans-serif;font-size:12px;padding:20px}h2{margin:0 0 12px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #cbd5e1;padding:6px 8px;text-align:left}th{background:#f1f5f9}</style>';
        html += '</head><body><h2>' + title + '</h2><div class="table-wrapper"><table><thead><tr>';
        rows[0].forEach(h => { html += '<th>' + h + '</th>'; });
        html += '</tr></thead><tbody>';
        rows.slice(1).forEach(r => {
            html += '<tr>';
            r.forEach(c => { html += '<td>' + c + '</td>'; });
            html += '</tr>';
        });
        html += '</tbody></table></div><script>window.onload=function(){window.print();}<\/script></body></html>';
        win.document.open();
        win.document.write(html);
        win.document.close();
    }

    function printTableData() {
        window.print();
    }

    function hapusUser(id, nama) {
        if (confirm('Apakah yakin ingin menghapus data pengguna ' + nama + '?')) {
            fetch('/admin/users/' + id, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            }).then(r => { if (r.ok) location.reload(); });
        }
    }

    function hapusSemua(peran, jumlah) {
        var labels = { guru: 'Guru', siswa: 'Siswa', ortu: 'Orang Tua' };
        var label = labels[peran] || peran;
        if (!confirm('Hapus SEMUA akun ' + label + ' (' + jumlah + ' akun)?\n\nTindakan ini tidak dapat dibatalkan.')) return;

        var frasa = 'HAPUS ' + String(peran).toUpperCase();
        var ketikan = prompt('Ketik persis: ' + frasa + '\nuntuk menghapus ' + jumlah + ' akun ' + label + '.');

        if (ketikan === null) return;

        if (String(ketikan).trim().toUpperCase() !== frasa) {
            alert('Teks konfirmasi tidak cocok. Tidak ada akun yang dihapus.');
            return;
        }

        fetch('{{ url("/admin/users/hapus-semua") }}/' + peran, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ konfirmasi: frasa })
        }).then(function(r) {
            return r.json().then(function(d) {
                if (!r.ok) throw new Error(d.message || ('HTTP ' + r.status));
                return d;
            });
        }).then(function(d) {
            if (d.success) {
                alert(d.message);
                location.reload();
            } else {
                alert(d.message || 'Gagal menghapus');
            }
        }).catch(function(err) {
            alert('Terjadi kesalahan: ' + err.message);
        });
    }
</script>

<style>
    .active-tab {
        background: #1F3864 !important;
        color: #fff !important;
        border-color: #1F3864 !important;
    }
    .opsi-dropdown button:hover, .opsi-dropdown a:hover {
        background: #F8FAFC !important;
    }
</style>
@endpush
