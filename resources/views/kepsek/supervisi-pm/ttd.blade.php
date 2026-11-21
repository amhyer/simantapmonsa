@extends('layouts.app')

@section('title', 'Tanda Tangan Digital Kepala Sekolah')
@section('page_title', 'Tanda Tangan Digital Kepala Sekolah')
@section('page_subtitle', 'Unggah PNG tanda tangan transparan Kepala Sekolah secara mandiri untuk seluruh cetakan PDF perangkat ajar & supervisi')

@section('sidebar')
    @include('kepsek.partials.sidebar')
@endsection

@section('content')
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(330px,1fr));gap:16px">
        <div class="card">
            <div class="card-header"><strong><i class="fas fa-signature"></i> Unggah Tanda Tangan Kepala Sekolah</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('kepsek.ttd.store') }}" enctype="multipart/form-data" style="display:grid;gap:12px">
                    @csrf
                    <div style="text-align:center;padding:16px;background:#F9FAFB;border:1px dashed #D0D5DD;border-radius:10px">
                        @if($preview['ttd_kepsek_uri'])
                            <img src="{{ $preview['ttd_kepsek_uri'] }}" alt="TTD Kepala Sekolah" style="max-height:90px;max-width:220px;margin-bottom:8px">
                            <div style="font-size:12px;color:#027A48;font-weight:600">Tanda tangan digital aktif (tersimpan di disk privat)</div>
                        @else
                            <div style="font-size:28px;color:#98A2B3;margin-bottom:4px"><i class="fas fa-pen-nib"></i></div>
                            <div style="font-size:12px;color:#667085">Belum ada gambar tanda tangan. Unggah file PNG transparan (maks 2MB).</div>
                        @endif
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">File Tanda Tangan (PNG Transparan disarankan)</label>
                        <input type="file" name="ttd_kepsek" accept=".png,.jpg,.jpeg"
                               style="width:100%;padding:8px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">NIP Kepala Sekolah</label>
                        <input type="text" name="nip_kepsek" value="{{ old('nip_kepsek', $preview['nip_kepsek']) }}"
                               placeholder="Misal: 19750101 200003 1 001"
                               style="width:100%;padding:8px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    </div>

                    <div>
                        <button type="submit" class="btn"><i class="fas fa-save"></i> Simpan Tanda Tangan</button>
                    </div>
                </form>

                @if($preview['ttd_kepsek_uri'])
                    <form method="POST" action="{{ route('kepsek.ttd.hapus') }}" style="margin-top:10px"
                          onsubmit="return confirm('Hapus gambar tanda tangan digital Kepala Sekolah?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-ghost" style="color:#D92D20">
                            <i class="fas fa-trash"></i> Hapus Gambar Tanda Tangan
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong><i class="fas fa-file-pdf"></i> Pratinjau pada Cetakan PDF (dengan Stempel Sekolah)</strong></div>
            <div class="card-body">
                <div style="background:#FFFFFF;border:1px solid #E4E7EC;border-radius:10px;padding:18px;font-size:12px;text-align:center">
                    <div>Mengetahui,</div>
                    <div>Kepala Sekolah</div>
                    <div style="position:relative;height:66px;margin:6px auto;width:210px">
                        @if($preview['stempel_uri'])
                            <img src="{{ $preview['stempel_uri'] }}" alt="Stempel" style="position:absolute;left:18px;top:0;height:62px;max-width:90px;opacity:0.85">
                        @endif
                        @if($preview['ttd_kepsek_uri'])
                            <img src="{{ $preview['ttd_kepsek_uri'] }}" alt="TTD" style="position:relative;height:56px;max-width:150px;margin-top:5px">
                        @endif
                    </div>
                    <div style="font-weight:bold;text-decoration:underline">{{ $preview['nama_kepsek'] ?: $kepsek->nama_lengkap }}</div>
                    @if($preview['nip_kepsek'])
                        <div style="font-size:11px;margin-top:2px">NIP. {{ $preview['nip_kepsek'] }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
