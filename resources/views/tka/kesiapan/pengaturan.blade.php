@extends('layouts.admin-with-tabs')

@section('title', 'Pengaturan & Pemetaan Kesiapan TKA')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Pengaturan & Pemetaan Kesiapan TKA')
@section('page_subtitle', 'Konfigurasi ambang internal sekolah, minimum bukti, pemetaan mata pelajaran, dan log audit')

@section('content')
@php use App\Support\KesiapanLabel; @endphp
@if(session('success'))<div class="note note-ok" style="margin-bottom:16px">{{ session('success') }}</div>@endif
@if($errors->any())<div class="note note-warn" style="margin-bottom:16px">{{ $errors->first() }}</div>@endif

@include('tka.kesiapan._disclaimer')

<div style="margin-bottom:12px">
    <a class="btn btn-ghost btn-sm" href="{{ route('admin.tka-kesiapan.index') }}">← Kembali ke dasbor kesiapan</a>
</div>

<div class="card" style="padding:14px;margin-bottom:20px">
    <h3>Status Indikator Pemantauan</h3>
    <div class="table-wrapper"><table class="table">
        <thead><tr><th>Indikator</th><th>Klasifikasi &amp; status verifikasi</th><th>Keterangan</th></tr></thead>
        <tbody>
        @foreach($indikator as $kode => $d)
            <tr>
                <td><strong>{{ $d['nama'] }}</strong> (<code>{{ $kode }}</code>)</td>
                <td>{{ KesiapanLabel::asal($d['asal']) }}</td>
                <td>{{ $d['catatan'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div>

<div class="grid grid-2" style="margin-bottom:20px">
    <div class="card" style="padding:14px">
        <h3>Aturan internal pemantauan sekolah</h3>
        <p style="font-size:.8rem;color:#64748b">Aturan ini adalah alat bantu internal SIMANTAP (bukan standar resmi Kementerian) dan harus disetujui/ditinjau oleh sekolah.</p>
        <form method="POST" action="{{ route('admin.tka-kesiapan.aturan.simpan') }}">
            @csrf
            <div class="grid grid-2" style="gap:10px">
                <label>Minimum bukti per indikator
                    <input type="number" name="min_bukti" value="{{ $aturan['min_bukti'] }}" min="1" max="20" class="form-control"></label>
                <label>Minimum tanggal berbeda
                    <input type="number" name="min_tanggal_berbeda" value="{{ $aturan['min_tanggal_berbeda'] }}" min="1" max="20" class="form-control"></label>
                <label>Ambang "perlu dukungan" (&lt; nilai ini)
                    <input type="number" step="0.5" name="ambang_perlu_dukungan" value="{{ $aturan['ambang_perlu_dukungan'] }}" min="0" max="100" class="form-control"></label>
                <label>Ambang "cukup mantap" (&ge; nilai ini)
                    <input type="number" step="0.5" name="ambang_cukup_mantap" value="{{ $aturan['ambang_cukup_mantap'] }}" min="0" max="100" class="form-control"></label>
                <label>Ambang perubahan tren (poin)
                    <input type="number" step="0.5" name="ambang_tren" value="{{ $aturan['ambang_tren'] }}" min="1" max="50" class="form-control"></label>
                <label>Minimum bukti per periode (tren)
                    <input type="number" name="min_bukti_per_periode" value="{{ $aturan['min_bukti_per_periode'] }}" min="1" max="20" class="form-control"></label>
                <label>Minimum siswa untuk agregat kelompok
                    <input type="number" name="min_kelompok_agregat" value="{{ $aturan['min_kelompok_agregat'] }}" min="1" max="30" class="form-control"></label>
                <label>Batas hari kebaruan bukti (keyakinan)
                    <input type="number" name="keyakinan_kebaruan_hari" value="{{ $aturan['keyakinan_kebaruan_hari'] }}" min="7" max="730" class="form-control"></label>
            </div>
            <label style="margin-top:8px;display:block">Alasan perubahan (dicatat di log audit)
                <input type="text" name="alasan" class="form-control" maxlength="500" placeholder="mis. Penyesuaian rapat dewan guru semester genap"></label>
            <button class="btn btn-primary btn-sm" style="margin-top:10px">Simpan aturan</button>
        </form>
    </div>

    <div class="card" style="padding:14px">
        <h3>Pemetaan label sumber ke indikator</h3>
        <form method="POST" action="{{ route('admin.tka-kesiapan.pemetaan.simpan') }}" style="margin-bottom:12px">
            @csrf
            <label>Nama mata pelajaran / label di sumber
                <input type="text" name="label_sumber" class="form-control" placeholder="mis. Matematika Terapan" required maxlength="120"></label>
            <label>Petakan ke indikator
                <select name="indikator_kode" class="form-control" required>
                    @foreach($indikator as $kode => $d)
                        <option value="{{ $kode }}">{{ $d['nama'] }} ({{ KesiapanLabel::asal($d['asal']) }})</option>
                    @endforeach
                </select></label>
            <label>Alasan pemetaan (opsional)
                <input type="text" name="alasan" class="form-control" maxlength="500" placeholder="mis. Mapel muatan lokal numerasi"></label>
            <button class="btn btn-primary btn-sm" style="margin-top:8px">Tambah / perbarui pemetaan</button>
        </form>

        <div class="table-wrapper"><table class="table">
            <thead><tr><th>Label sumber</th><th>Indikator</th><th></th></tr></thead>
            <tbody>
            @forelse($pemetaan as $m)
                <tr>
                    <td>{{ $m->label_sumber }}</td>
                    <td>{{ $indikator[$m->indikator_kode]['nama'] ?? $m->indikator_kode }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.tka-kesiapan.pemetaan.hapus', $m->id) }}">
                            @csrf @method('DELETE')
                            <button class="btn btn-ghost btn-sm">Hapus</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3">Belum ada pemetaan khusus sekolah (menggunakan pemetaan bawaan sementara).</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Riwayat audit perubahan konfigurasi</h3></div>
    <div class="table-wrapper"><table class="table">
        <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Alasan</th><th>Sebelum</th><th>Sesudah</th></tr></thead>
        <tbody>
        @forelse($audit as $a)
            <tr>
                <td>{{ $a->created_at?->format('d/m/Y H:i') }}</td>
                <td>{{ $a->user?->nama_lengkap ?? '—' }}</td>
                <td>{{ $a->aksi }}</td>
                <td>{{ $a->alasan ?? '—' }}</td>
                <td><code>{{ json_encode($a->sebelum) }}</code></td>
                <td><code>{{ json_encode($a->sesudah) }}</code></td>
            </tr>
        @empty
            <tr><td colspan="6">Belum ada perubahan konfigurasi.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
