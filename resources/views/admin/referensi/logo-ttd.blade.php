@extends('layouts.admin-with-tabs')

@section('title', 'Logo dan TTD - SIMANTAP')

@section('page_title', 'Logo dan TTD')
@section('page_subtitle', 'Unggah logo pemda, logo sekolah, tanda tangan kepala sekolah, dan kop sekolah (maks 2MB per file)')

@section('content')
    @php
        $dokumen = [
            'logo_pemda' => 'Logo Pemda',
            'logo_sekolah' => 'Logo Sekolah',
            'ttd_kepsek' => 'Tanda Tangan Kepala Sekolah (PNG Transparan)',
            'stempel_sekolah' => 'Stempel Sekolah (PNG Transparan)',
            'kop_sekolah' => 'Kop Sekolah',
        ];
    @endphp

    <form action="{{ route('admin.referensi.logo-ttd.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="card" style="margin-bottom:16px">
            <div class="card-body" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
                <div style="flex:1;min-width:240px">
                    <label for="fld-referensi-logo-ttd-nip-kepsek" style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">NIP Kepala Sekolah (tampil di cetakan PDF)</label>
                    <input id="fld-referensi-logo-ttd-nip-kepsek" type="text" name="nip_kepsek" value="{{ old('nip_kepsek', $pengaturan['nip_kepsek'] ?? '') }}"
                           placeholder="Misal: 19750101 200003 1 001"
                           style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px;font-size:13px">
                </div>
                <div style="font-size:12px;color:#667085">
                    Kepala Sekolah: <strong>{{ $sekolah?->kepala_sekolah ?: 'Belum diisi di Profil Sekolah' }}</strong>
                </div>
            </div>
        </div>
        <div class="grid grid-2" style="margin-bottom:16px">
            @foreach($dokumen as $key => $label)
                <div class="card">
                    <div class="card-header">
                        <h3>{{ $label }}</h3>
                    </div>
                    <div class="card-body" style="text-align:center">
                        @php
                            $isPrivate = in_array($key, ['ttd_kepsek', 'stempel_sekolah'], true);
                            $previewSrc = null;
                            if (!empty($pengaturan[$key])) {
                                $previewSrc = $isPrivate
                                    ? app(\App\Services\PembelajaranMendalam\TandaTanganPdfService::class)->toDataUri($pengaturan[$key])
                                    : asset('storage/' . $pengaturan[$key]);
                            }
                        @endphp
                        @if($previewSrc)
                            <img src="{{ $previewSrc }}" alt="{{ $label }}" style="max-width:180px;max-height:140px;border:1px solid #E4E7EC;border-radius:8px;padding:8px;margin-bottom:10px;background:#fff">
                        @else
                            <div class="empty-state" style="padding:16px">
                                <div class="icon">🖼️</div>
                                <p style="font-size:12px">Belum ada file</p>
                            </div>
                        @endif
                        <input type="file" name="{{ $key }}" accept=".jpeg,.jpg,.png" style="width:100%;padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px;font-size:13px">
                    </div>
                </div>
            @endforeach
        </div>
        <button type="submit" class="btn btn-sm">Simpan Semua</button>
        <span style="font-size:12px;color:#667085;margin-left:8px">File lama otomatis terhapus saat diganti.</span>
    </form>
@endsection

