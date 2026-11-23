@extends('layouts.app')

@section('title', 'Tanda Tangan Digital - Pembelajaran Mendalam')
@section('page_title', 'Tanda Tangan Digital & Pratinjau Stempel PDF')
@section('page_subtitle', 'Unggah PNG tanda tangan transparan Anda agar otomatis tampil di seluruh cetakan PDF Perangkat Ajar & Jurnal')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('content')
    @include('guru.pm._nav')

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(330px,1fr));gap:16px">
        {{-- Form Upload TTD Guru --}}
        <div class="card">
            <div class="card-header"><strong><i class="fas fa-signature"></i> Tanda Tangan Digital Saya</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('guru.pm.ttd.store') }}" enctype="multipart/form-data" style="display:grid;gap:12px">
                    @csrf

                    <div style="text-align:center;padding:16px;background:#F9FAFB;border:1px dashed #D0D5DD;border-radius:10px">
                        @if($preview['ttd_guru_uri'])
                            <img src="{{ $preview['ttd_guru_uri'] }}" alt="Tanda Tangan Saya"
                                 style="max-height:90px;max-width:220px;margin-bottom:8px">
                            <div style="font-size:12px;color:#027A48;font-weight:600">Tanda tangan digital aktif</div>
                        @else
                            <div style="font-size:28px;color:#98A2B3;margin-bottom:4px"><i class="fas fa-pen-nib"></i></div>
                            <div style="font-size:12px;color:#667085">Belum ada gambar tanda tangan. Unggah file PNG transparan (maks 2MB).</div>
                        @endif
                    </div>

                    <div>
                        <label for="fld-pm-ttd-ttd-guru" style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Unggah Gambar Tanda Tangan (PNG Transparan disarankan)</label>
                        <input id="fld-pm-ttd-ttd-guru" type="file" name="ttd_guru" accept=".png,.jpg,.jpeg"
                               style="width:100%;padding:8px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    </div>

                    <div>
                        <label for="fld-pm-ttd-jabatan-guru" style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Jabatan pada Cetakan PDF</label>
                        <input id="fld-pm-ttd-jabatan-guru" type="text" name="jabatan_guru"
                               value="{{ old('jabatan_guru', $pengGuru['jabatan_guru'] ?? 'Guru Kelas / Mata Pelajaran') }}"
                               placeholder="Misal: Guru Kelas 1A / Guru Matematika"
                               style="width:100%;padding:8px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    </div>

                    <div>
                        <label for="fld-pm-ttd-nip-guru" style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">NIP / NUPTK (opsional)</label>
                        <input id="fld-pm-ttd-nip-guru" type="text" name="nip_guru"
                               value="{{ old('nip_guru', $pengGuru['nip_guru'] ?? '') }}"
                               placeholder="Misal: 19880512 201503 2 004"
                               style="width:100%;padding:8px 10px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px">
                    </div>

                    <div style="display:flex;gap:8px;align-items:center">
                        <button type="submit" class="btn"><i class="fas fa-save"></i> Simpan Tanda Tangan</button>
                    </div>
                </form>

                @if($preview['ttd_guru_uri'])
                    <form method="POST" action="{{ route('guru.pm.ttd.hapus') }}" style="margin-top:10px"
                          onsubmit="return confirm('Hapus gambar tanda tangan digital Anda?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-ghost" style="color:var(--bad)">
                            <i class="fas fa-trash"></i> Hapus Gambar Tanda Tangan
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Pratinjau Blok TTD PDF (Kepsek + Stempel + Guru) --}}
        <div class="card">
            <div class="card-header"><strong><i class="fas fa-file-pdf"></i> Pratinjau Blok Tanda Tangan di Cetakan PDF</strong></div>
            <div class="card-body">
                <div style="background:#FFFFFF;border:1px solid #E4E7EC;border-radius:10px;padding:18px;font-size:12px">
                    @include('guru.pm.pdf._ttd', ['sekolah' => $sekolah, 'guru' => $guru])
                </div>

                <div style="margin-top:14px;padding:12px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;font-size:12px;color:#475467;line-height:1.5">
                    <strong>Status Aset Sekolah:</strong>
                    <ul style="margin:6px 0 0;padding-left:18px">
                        <li>TTD Kepala Sekolah: <strong>{{ $preview['ttd_kepsek_uri'] ? 'Sudah terpasang' : 'Belum diunggah oleh Admin' }}</strong></li>
                        <li>Stempel Sekolah: <strong>{{ $preview['stempel_uri'] ? 'Sudah terpasang' : 'Belum diunggah oleh Admin' }}</strong></li>
                        <li>TTD Guru: <strong>{{ $preview['ttd_guru_uri'] ? 'Sudah terpasang' : 'Belum diunggah' }}</strong></li>
                    </ul>
                    <div style="margin-top:6px">
                        <em>Admin dapat mengunggah TTD Kepala Sekolah &amp; Stempel Sekolah di menu <strong>Admin → Logo dan TTD</strong>.</em>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
