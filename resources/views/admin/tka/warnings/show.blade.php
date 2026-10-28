@extends('layouts.admin-with-tabs')

@section('title', 'Detail Early Warning TKA - Admin')

@section('page_title', 'Detail Early Warning')
@section('page_subtitle', $warning->siswa->nama_peserta_didik ?? 'Peringatan dini TKA')

@section('content')
    @php
        $tingkatTags = [
            'ringan' => 'tag-info',
            'sedang' => 'tag-warn',
            'tinggi' => 'tag-warn',
            'berat' => 'tag-bad',
            'kritis' => 'tag-bad',
        ];
        $statusLabels = [
            'pending' => 'Pending',
            'sedang_diproses' => 'Sedang Diproses',
            'selesai' => 'Selesai',
        ];
        $statusTags = [
            'pending' => 'tag-warn',
            'sedang_diproses' => 'tag-info',
            'selesai' => 'tag-ok',
        ];
    @endphp

    <div class="card" style="margin-bottom:16px">
        <div class="card-header">
            <h3>Informasi Peringatan</h3>
            <a href="{{ route('admin.tka.warnings.index') }}" class="btn btn-sm">Kembali</a>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:170px 1fr;gap:8px 16px;font-size:13px">
                <div style="color:var(--muted)">Siswa</div>
                <div><strong>{{ $warning->siswa->nama_peserta_didik ?? '-' }}</strong></div>

                <div style="color:var(--muted)">NIS / Kelas</div>
                <div>{{ $warning->siswa->nis ?? '-' }} / {{ $warning->siswa->kelas ?? '-' }}</div>

                <div style="color:var(--muted)">Dimensi</div>
                <div>{{ $warning->dimension->nama_dimensi ?? '-' }}</div>

                <div style="color:var(--muted)">Semester</div>
                <div>{{ $warning->semester->label ?? '-' }}</div>

                <div style="color:var(--muted)">Tingkat Peringatan</div>
                <div>
                    <span class="tag {{ $tingkatTags[$warning->tingkat_peringatan] ?? 'tag-mut' }}">
                        {{ ucfirst($warning->tingkat_peringatan ?? '-') }}
                    </span>
                </div>

                <div style="color:var(--muted)">Status</div>
                <div>
                    <span class="tag {{ $statusTags[$warning->status] ?? 'tag-mut' }}">
                        {{ $statusLabels[$warning->status] ?? ($warning->status ?: '-') }}
                    </span>
                </div>

                <div style="color:var(--muted)">Dibuat</div>
                <div>{{ $warning->created_at?->format('d/m/Y H:i') ?? '-' }}</div>

                <div style="color:var(--muted)">Ditangani Oleh</div>
                <div>{{ $warning->handler->nama_lengkap ?? '-' }}</div>

                <div style="color:var(--muted)">Tanggal Selesai</div>
                <div>{{ $warning->tanggal_selesai?->format('d/m/Y H:i') ?? '-' }}</div>

                <div style="color:var(--muted)">Alasan Peringatan</div>
                <div>{{ $warning->alasan_peringatan ?: '-' }}</div>

                <div style="color:var(--muted)">Rekomendasi Tindakan</div>
                <div>{{ $warning->rekomendasi_tindakan ?: '-' }}</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Perbarui Status</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.tka.warnings.update-status', $warning) }}" method="POST" style="display:grid;gap:12px;max-width:420px">
                @csrf
                <div>
                    <label class="form-label">Status</label>
                    <select name="status" required class="form-control">
                        @foreach($statusLabels as $val => $label)
                            <option value="{{ $val }}" @selected(old('status', $warning->status) === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div>
                    <button type="submit" class="btn btn-sm">Simpan Status</button>
                </div>
            </form>
        </div>
    </div>
@endsection
