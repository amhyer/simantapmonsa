@extends('layouts.app')

@section('title', 'Akun Massal - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Akun Massal')
@section('page_subtitle', 'Buat akun siswa secara massal')

@section('content')

<div class="card" style="margin-bottom:16px">
    <div class="card-header"><h3>Buat Akun Massal</h3><a href="{{ route('admin.users.index') }}" class="btn btn-ghost btn-sm">Kembali</a></div>
    <div class="card-body">
        <div style="background:#EEF2FF;border-radius:8px;padding:12px;margin-bottom:14px;font-size:13px;color:#4338CA">
            <b>Cara kerja:</b> Sistem akan membuat akun login untuk setiap siswa yang belum punya akun. Password default: <code>NISN siswa</code>.
        </div>
        <form id="formMassal">
            @csrf
            <div style="margin-bottom:12px">
                <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px">Pilih Siswa (centang yang ingin dibuatkan akun)</label>
                <div style="display:flex;gap:8px;margin-bottom:8px">
                    <button type="button" class="btn btn-ghost btn-sm" onclick="centangSemua()">Centang Semua</button>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="batalCentang()">Batal Centang</button>
                </div>
                <div id="siswaList" style="max-height:400px;overflow-y:auto;border:1px solid #E4E7EC;border-radius:8px">
                    @forelse($siswa as $s)
                        @php
                            $hasUser = $users->contains(function ($u) use ($s) {
                                return $u->peran === 'siswa' && is_array($u->terhubung_dengan) && in_array($s->id, $u->terhubung_dengan);
                            });
                        @endphp
                        <div class="siswa-item" style="padding:8px 12px;border-bottom:1px solid #F2F4F7;display:flex;align-items:center;gap:10px">
                            <input type="checkbox" class="siswa-check" data-id="{{ $s->id }}" value="{{ $s->id }}" {{ $hasUser ? 'disabled checked' : '' }}>
                            <div style="flex:1">
                                <div style="font-weight:600;font-size:13px">{{ $s->nama_peserta_didik }}</div>
                                <div style="font-size:11px;color:#888">NIS: {{ $s->nis }} | NISN: {{ $s->nisn }} | Kelas: {{ $s->kelas }}</div>
                            </div>
                            @if($hasUser)
                                <span style="background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">Sudah punya akun</span>
                            @else
                                <span style="background:#FEF3C7;color:#92400E;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">Belum punya akun</span>
                            @endif
                        </div>
                    @empty
                        <div style="text-align:center;padding:20px;color:#888">Tidak ada data siswa</div>
                    @endforelse
                </div>
            </div>
            <div style="display:flex;gap:10px;align-items:center">
                <button type="submit" id="btnSubmit" class="btn btn-sm" style="background:#1F3864;color:#fff">Buat Akun Terpilih</button>
                <span id="countInfo" style="font-size:12px;color:#667085"></span>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
    function centangSemua() {
        document.querySelectorAll('.siswa-check:not(:disabled)').forEach(function(c) { c.checked = true; updateCount(); });
    }
    function batalCentang() {
        document.querySelectorAll('.siswa-check:not(:disabled)').forEach(function(c) { c.checked = false; updateCount(); });
    }
    function updateCount() {
        var n = document.querySelectorAll('.siswa-check:checked:not(:disabled)').length;
        document.getElementById('countInfo').textContent = n > 0 ? n + ' siswa dipilih' : '';
    }

    document.querySelectorAll('.siswa-check').forEach(function(c) {
        c.addEventListener('change', updateCount);
    });
    updateCount();

    document.getElementById('formMassal').addEventListener('submit', function(e) {
        e.preventDefault();
        var checked = document.querySelectorAll('.siswa-check:checked:not(:disabled)');
        if (!checked.length) {
            alert('Pilih minimal 1 siswa yang belum punya akun.');
            return;
        }
        var ids = [];
        checked.forEach(function(c) { ids.push(c.value); });

        var btn = document.getElementById('btnSubmit');
        btn.disabled = true;
        btn.textContent = 'Memproses...';

        fetch('{{ route("admin.users.proses-massal") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ siswa_ids: ids, buat_siswa: true })
        }).then(function(r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        }).then(function(d) {
            if (d.success) {
                alert(d.message || 'Berhasil');
                window.location.href = '{{ route("admin.users.index") }}';
            } else {
                alert(d.message || 'Gagal membuat akun');
                btn.disabled = false;
                btn.textContent = 'Buat Akun Terpilih';
            }
        }).catch(function(err) {
            alert('Terjadi kesalahan: ' + err.message);
            btn.disabled = false;
            btn.textContent = 'Buat Akun Terpilih';
        });
    });
</script>
@endpush
