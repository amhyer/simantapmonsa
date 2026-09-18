@extends('layouts.app')

@section('title', 'Kode & Akses - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Kode & Akses')
@section('page_subtitle', 'Pengaturan kode akses per guru/kelas')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Pengaturan Kode Akses</h3>
        </div>
        <div class="card-body tight">
            @if($pengaturanGuru->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Guru</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Kelas</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Kode Akses Saat Ini</th>
                                <th style="padding:11px 14px;text-align:right;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pengaturanGuru as $pg)
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        <div style="display:flex;align-items:center;gap:8px">
                                            <span class="avatar" style="width:28px;height:28px;font-size:11px">{{ $pg->guru ? substr($pg->guru->nama_lengkap, 0, 2) : '?' }}</span>
                                            <span style="font-weight:600">{{ $pg->guru?->nama_lengkap ?? '-' }}</span>
                                        </div>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        <span class="tag tag-primary">{{ $pg->kelas }}</span>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        @php $kode = ($pg->pengaturan ?? [])['kode_akses'] ?? null; @endphp
                                        @if($kode)
                                            <code style="background:#EEF2F9;padding:3px 10px;border-radius:6px;font-size:13px">{{ $kode }}</code>
                                        @else
                                            <span class="tag tag-mut">Belum diatur</span>
                                        @endif
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:right">
                                        <button onclick="editKode({{ $pg->id }}, '{{ $kode ?? '' }}')" class="btn btn-ghost btn-sm">
                                            <i class="fas fa-key"></i> Ubah
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty"><div class="icon">🔑</div><b>Belum ada pengaturan</b><p>Buat pengaturan kelas terlebih dahulu di menu Peta Kelas.</p></div>
            @endif
        </div>
    </div>

    <div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:2000;display:none;align-items:center;justify-content:center">
        <div style="background:#fff;border-radius:12px;padding:24px;width:90%;max-width:420px;box-shadow:0 20px 60px rgba(0,0,0,0.2)">
            <h3 style="font-size:16px;font-weight:700;margin-bottom:16px">Ubah Kode Akses</h3>
            <form id="kodeForm" method="POST">
                @csrf
                <div style="margin-bottom:16px">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Kode Akses</label>
                    <input type="text" name="kode_akses" id="kodeInput" placeholder="Masukkan kode akses" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end">
                    <button type="button" onclick="closeModal()" class="btn btn-ghost">Batal</button>
                    <button type="submit" class="btn">💾 Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editKode(id, current) {
            document.getElementById('kodeForm').action = '/admin/kode-akses/' + id;
            document.getElementById('kodeInput').value = current;
            document.getElementById('editModal').style.display = 'flex';
        }
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
@endsection
