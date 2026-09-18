@extends('layouts.app')

@section('title', 'Kelola Pengguna - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Kelola Pengguna')
@section('page_subtitle', 'Kelola semua akun pengguna sistem')

@section('header_actions')
    <a href="{{ route('admin.users.create') }}" class="btn btn-sm">+ Tambah akun</a>
    <a href="{{ route('admin.users.akun-massal') }}" class="btn btn-sm btn-ghost">Akun Massal</a>
@endsection

@section('content')
    @php
        $adminUsers = $users->where('peran', 'admin');
        $guruUsers = $users->where('peran', 'guru');
        $siswaUsers = $users->where('peran', 'siswa');
        $ortuUsers = $users->where('peran', 'ortu');
        $kepsekUsers = $users->where('peran', 'kepsek');
        $allRole = $adminUsers->merge($guruUsers)->merge($siswaUsers)->merge($ortuUsers)->merge($kepsekUsers);
        $siswaWithoutUser = $siswa->filter(function ($s) use ($users) {
            return !$users->contains(function ($u) use ($s) {
                return $u->peran === 'siswa' && is_array($u->terhubung_dengan) && in_array($s->id, $u->terhubung_dengan);
            });
        });
    @endphp

    <div class="grid grid-4" style="margin-bottom:16px">
        <div class="stat"><div class="label">Total akun</div><div class="value">{{ $users->count() }}</div></div>
        <div class="stat gold"><div class="label">Guru</div><div class="value">{{ $guruUsers->count() }}</div></div>
        <div class="stat ok"><div class="label">Siswa</div><div class="value">{{ $siswaUsers->count() }}</div></div>
        <div class="stat"><div class="label">Tanpa akun</div><div class="value">{{ $siswaWithoutUser->count() }}</div></div>
    </div>

    <div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
        <button onclick="switchTab('all')" id="tab-all" class="btn btn-sm tab-btn active-tab">Semua ({{ $users->count() }})</button>
        <button onclick="switchTab('admin')" id="tab-admin" class="btn btn-sm btn-ghost tab-btn">Admin ({{ $adminUsers->count() }})</button>
        <button onclick="switchTab('guru')" id="tab-guru" class="btn btn-sm btn-ghost tab-btn">Guru ({{ $guruUsers->count() }})</button>
        <button onclick="switchTab('siswa')" id="tab-siswa" class="btn btn-sm btn-ghost tab-btn">Siswa ({{ $siswaUsers->count() }})</button>
        <button onclick="switchTab('ortu')" id="tab-ortu" class="btn btn-sm btn-ghost tab-btn">Orang Tua ({{ $ortuUsers->count() }})</button>
        <button onclick="switchTab('tanpa-akun')" id="tab-tanpa-akun" class="btn btn-sm btn-ghost tab-btn" style="border:1px dashed #B8860B;color:#B8860B">Belum Punya Akun ({{ $siswaWithoutUser->count() }})</button>
        @if($guruUsers->count())
            <button onclick="hapusSemua('guru', {{ $guruUsers->count() }})" class="btn btn-sm btn-ghost" style="color:#B42318;border:1px solid #B42318;margin-left:auto">Hapus Semua Guru</button>
        @endif
        @if($siswaUsers->count())
            <button onclick="hapusSemua('siswa', {{ $siswaUsers->count() }})" class="btn btn-sm btn-ghost" style="color:#B42318;border:1px solid #B42318">Hapus Semua Siswa</button>
        @endif
        @if($ortuUsers->count())
            <button onclick="hapusSemua('ortu', {{ $ortuUsers->count() }})" class="btn btn-sm btn-ghost" style="color:#B42318;border:1px solid #B42318">Hapus Semua Orang Tua</button>
        @endif
    </div>

    <div class="card">
        <div class="card-header">
            <h3 id="tableTitle">Semua Akun</h3>
            <input type="text" id="searchUser" placeholder="Cari nama atau username..." style="padding:6px 12px;border:1px solid #E4E7EC;border-radius:8px;width:200px" oninput="filterUsers(this.value)">
        </div>
        <div class="card-body tight">
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse" id="userTable">
                    <thead>
                        <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                            <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Nama</th>
                            <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Username</th>
                            <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Peran</th>
                            <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Aktif</th>
                            <th style="padding:11px 14px;text-align:right;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="userBody">
                        @php
                            $peranLabels = ['admin' => 'Admin', 'guru' => 'Guru', 'siswa' => 'Siswa', 'ortu' => 'Orang Tua', 'kepsek' => 'Kepsek'];
                            $peranColors = ['admin' => 'tag-bad', 'guru' => 'tag-gold', 'siswa' => 'tag-ok', 'ortu' => 'tag-mut', 'kepsek' => 'tag-gold'];
                        @endphp
                        @foreach($users as $u)
                            <tr data-role="{{ $u->peran }}" data-search="{{ strtolower($u->nama_lengkap . ' ' . $u->nama_pengguna) }}">
                                <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                    <div style="display:flex;align-items:center;gap:11px">
                                        <span class="avatar">{{ substr($u->nama_lengkap, 0, 2) }}</span>
                                        <b>{{ $u->nama_lengkap }}</b>
                                        @if($u->dapodik_id)
                                            <span style="display:inline-block;background:#EEF2F9;color:var(--navy);font-size:9px;padding:1px 5px;border-radius:4px;font-weight:600" title="Data dari Dapodik">DAPODIK</span>
                                        @endif
                                    </div>
                                </td>
                                <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC"><code style="background:#EEF2F9;padding:2px 8px;border-radius:6px;font-size:12.5px">{{ $u->nama_pengguna }}</code></td>
                                <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC"><span class="tag {{ $peranColors[$u->peran] ?? 'tag-mut' }}">{{ $peranLabels[$u->peran] ?? $u->peran }}</span></td>
                                <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">
                                    @if($u->aktif)<span class="tag tag-ok">Ya</span>@else<span class="tag tag-mut">Tidak</span>@endif
                                </td>
                                <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:right;white-space:nowrap">
                                    <a href="{{ route('admin.users.edit', $u) }}" class="btn btn-ghost btn-sm">Ubah</a>
                                    @if($u->nama_pengguna !== 'admin')
                                        <button onclick="hapusUser('{{ $u->id }}', '{{ $u->nama_lengkap }}')" class="btn btn-ghost btn-sm" style="color:#B42318">Hapus</button>
                                    @endif
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
        </div>
    </div>

    <div class="card" style="margin-top:16px" id="siswaTanpaAkunCard">
        <div class="card-header">
            <h3>Siswa Belum Punya Akun</h3>
            <a href="{{ route('admin.users.akun-massal') }}" class="btn btn-sm" style="background:#B8860B;color:#fff">Buat Akun Massal</a>
        </div>
        <div class="card-body tight">
            @if($siswaWithoutUser->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Nama Siswa</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">NIS</th>
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
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC"><code style="background:#EEF2F9;padding:2px 8px;border-radius:6px;font-size:12.5px">{{ $s->nis }}</code></td>
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
@endsection

@push('scripts')
<script>
    const tabTitles = {
        'all': 'Semua Akun',
        'admin': 'Akun Admin',
        'guru': 'Akun Guru',
        'siswa': 'Akun Siswa',
        'ortu': 'Akun Orang Tua',
        'tanpa-akun': 'Siswa Belum Punya Akun'
    };

    function switchTab(tab) {
        document.querySelectorAll('.tab-btn').forEach(b => {
            b.classList.remove('active-tab');
            b.classList.add('btn-ghost');
        });
        const activeBtn = document.getElementById('tab-' + tab);
        activeBtn.classList.add('active-tab');
        activeBtn.classList.remove('btn-ghost');

        document.getElementById('tableTitle').textContent = tabTitles[tab] || 'Semua Akun';

        const rows = document.querySelectorAll('#userBody tr');
        let visible = 0;

        if (tab === 'tanpa-akun') {
            rows.forEach(r => r.style.display = 'none');
            document.getElementById('userTable').style.display = 'none';
            document.getElementById('siswaTanpaAkunCard').style.display = '';
            document.getElementById('searchUser').style.display = 'none';
        } else {
            document.getElementById('userTable').style.display = '';
            document.getElementById('siswaTanpaAkunCard').style.display = 'none';
            document.getElementById('searchUser').style.display = '';

            rows.forEach(r => {
                if (tab === 'all' || r.dataset.role === tab) {
                    r.style.display = '';
                    visible++;
                } else {
                    r.style.display = 'none';
                }
            });

            document.getElementById('emptyState').style.display = visible === 0 ? 'block' : 'none';
        }
    }

    function filterUsers(v) {
        const s = v.toLowerCase();
        const activeTab = document.querySelector('.active-tab')?.id?.replace('tab-', '') || 'all';
        document.querySelectorAll('#userBody tr').forEach(r => {
            const roleMatch = activeTab === 'all' || r.dataset.role === activeTab;
            const searchMatch = (r.dataset.search || '').includes(s);
            r.style.display = roleMatch && searchMatch ? '' : 'none';
        });
    }

    function hapusUser(id, nama) {
        if (confirm('Hapus akun ' + nama + '?')) {
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

        fetch('{{ url("/admin/users/hapus-semua") }}/' + peran, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        }).then(function(r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
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
</style>
@endpush
