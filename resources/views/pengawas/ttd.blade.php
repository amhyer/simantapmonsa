@extends('layouts.app')

@section('title', 'Tanda Tangan Digital Pengawas Sekolah')
@section('page_title', 'Tanda Tangan Digital Pengawas Pembina')
@section('page_subtitle', 'Tanda tangan digital & identitas Pengawas Sekolah yang otomatis disematkan pada Berita Acara Visitasi & Laporan Kepengawasan PDF')

@section('sidebar')
    @include('pengawas.partials.sidebar')
@endsection

@section('content')
    @php
        $ttdService = app(\App\Services\PembelajaranMendalam\TandaTanganPdfService::class);
        $ttdData = $ttdService->resolveForPdf($sekolah);
    @endphp

    <div class="card" style="max-width:760px">
        <div class="card-header">
            <strong>Identitas &amp; Spesimen Tanda Tangan Pengawas Sekolah</strong>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('pengawas.ttd.store') }}" enctype="multipart/form-data" style="display:grid;gap:14px">
                @csrf
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px">
                    <div>
                        <label for="fld-pengawas-ttd-nama-pengawas" style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Nama Lengkap &amp; Gelar Pengawas *</label>
                        <input id="fld-pengawas-ttd-nama-pengawas" type="text" name="nama_pengawas" value="{{ old('nama_pengawas', $pengSekolah['nama_pengawas'] ?? $pengawas->nama_lengkap) }}" required
                               style="width:100%;padding:8px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    </div>
                    <div>
                        <label for="fld-pengawas-ttd-nip-pengawas" style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">NIP Pengawas Pembina</label>
                        <input id="fld-pengawas-ttd-nip-pengawas" type="text" name="nip_pengawas" value="{{ old('nip_pengawas', $pengSekolah['nip_pengawas'] ?? $pengawas->nip) }}"
                               placeholder="197501012000031001"
                               style="width:100%;padding:8px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    </div>
                </div>

                <div>
                    <label for="fld-pengawas-ttd-ttd-pengawas" style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Unggah Berkas Gambar Tanda Tangan (PNG transparan disarankan, maks 1 MB)</label>
                    <input id="fld-pengawas-ttd-ttd-pengawas" type="file" name="ttd_pengawas" accept="image/png,image/jpeg,image/webp"
                           style="width:100%;padding:8px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                </div>

                @if(!empty($ttdData['ttd_pengawas_uri']))
                    <div style="padding:12px;border:1px dashed #98A2B3;border-radius:8px;background:#F9FAFB;display:flex;align-items:center;justify-content:space-between;gap:12px">
                        <div>
                            <div style="font-size:12px;font-weight:600;color:#344054;margin-bottom:6px">Pratinjau Tanda Tangan Pengawas Aktif:</div>
                            <img src="{{ $ttdData['ttd_pengawas_uri'] }}" alt="TTD Pengawas" style="max-height:70px;object-fit:contain">
                        </div>
                    </div>
                @endif

                <div style="display:flex;gap:8px;justify-content:flex-end">
                    <button type="submit" class="btn btn-sm"><i class="fas fa-save"></i> Simpan Tanda Tangan Pengawas</button>
                </div>
            </form>

            @if(!empty($pengSekolah['ttd_pengawas']))
                <form method="POST" action="{{ route('pengawas.ttd.hapus') }}" style="margin-top:10px;display:flex;justify-content:flex-end"
                      onsubmit="return confirm('Hapus tanda tangan digital Pengawas?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-ghost" style="color:var(--bad)">
                        <i class="fas fa-trash"></i> Hapus Tanda Tangan
                    </button>
                </form>
            @endif
        </div>
    </div>
@endsection
