@extends('layouts.app')

@section('title', 'Identitas Sekolah - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Identitas Sekolah')
@section('page_subtitle', 'Profil dan identitas resmi sekolah')

@section('content')
    @if($sekolahSettings && $sekolahSettings->npsn)
        <div style="display:flex;align-items:center;gap:8px;padding:10px 16px;background:#EEF2F9;border-radius:8px;border-left:3px solid var(--navy);margin-bottom:16px;font-size:13px">
            <i class="fas fa-link" style="color:var(--navy)"></i>
            <span>Data ini bersumber dari <b>Dapodik Bridge</b> (NPSN: {{ $sekolahSettings->npsn }}). Anda dapat mengedit jika ada perubahan.</span>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3>Informasi Sekolah</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.sekolah.update') }}" method="POST" id="sekolahForm">
                @csrf
                <div style="margin-bottom:16px">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Nama Sekolah *</label>
                    <input type="text" name="nama_sekolah" id="nama_sekolah" value="{{ old('nama_sekolah', $sekolahSettings->nama_sekolah ?? '') }}" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                </div>

                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">NPSN</label>
                        <div style="display:flex;gap:8px">
                            <input type="text" name="npsn" id="npsn" value="{{ old('npsn', $sekolahSettings->npsn ?? '') }}" maxlength="8" placeholder="8 digit angka" style="flex:1;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                            <button type="button" id="btnLookup" onclick="lookupNpsn()" style="padding:10px 16px;background:#1F3864;color:#fff;border:none;border-radius:8px;cursor:pointer;white-space:nowrap;font-size:13px">
                                <i class="fas fa-search"></i> Cari
                            </button>
                        </div>
                        <div id="lookupStatus" style="margin-top:6px;font-size:12px;display:none"></div>
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Jenis Sekolah</label>
                        <select name="jenis_sekolah" id="jenis_sekolah" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                            <option value="">-- Pilih --</option>
                            <option value="Negeri" {{ ($sekolahSettings->jenis_sekolah ?? '') === 'Negeri' ? 'selected' : '' }}>Negeri</option>
                            <option value="Swasta" {{ ($sekolahSettings->jenis_sekolah ?? '') === 'Swasta' ? 'selected' : '' }}>Swasta</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Jenjang</label>
                        <select name="jenjang" id="jenjang" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                            <option value="">-- Pilih --</option>
                            @foreach(['SD', 'SMP', 'SMA', 'SMK'] as $j)
                                <option value="{{ $j }}" {{ ($sekolahSettings->jenjang ?? '') === $j ? 'selected' : '' }}>{{ $j }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Akreditasi</label>
                        <select name="akreditasi" id="akreditasi" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                            <option value="">-- Pilih --</option>
                            @foreach(['A', 'B', 'C', 'D'] as $ak)
                                <option value="{{ $ak }}" {{ ($sekolahSettings->akreditasi ?? '') === $ak ? 'selected' : '' }}>{{ $ak }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-2" style="margin-bottom:16px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Kepala Sekolah</label>
                        <input type="text" name="kepala_sekolah" id="kepala_sekolah" value="{{ old('kepala_sekolah', $sekolahSettings->kepala_sekolah ?? '') }}" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div></div>
                </div>

                <div style="margin-bottom:16px">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Alamat</label>
                    <textarea name="alamat" id="alamat" rows="2" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px;resize:vertical">{{ old('alamat', $sekolahSettings->alamat ?? '') }}</textarea>
                </div>

                <div class="grid grid-3" style="margin-bottom:16px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Kecamatan</label>
                        <input type="text" name="kecamatan" id="kecamatan" value="{{ old('kecamatan', $sekolahSettings->kecamatan ?? '') }}" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Kota/Kabupaten</label>
                        <input type="text" name="kota" id="kota" value="{{ old('kota', $sekolahSettings->kota ?? '') }}" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Provinsi</label>
                        <input type="text" name="provinsi" id="provinsi" value="{{ old('provinsi', $sekolahSettings->provinsi ?? '') }}" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                </div>

                <div class="grid grid-3" style="margin-bottom:16px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Kode Pos</label>
                        <input type="text" name="kode_pos" id="kode_pos" value="{{ old('kode_pos', $sekolahSettings->kode_pos ?? '') }}" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Telepon</label>
                        <input type="text" name="telepon" id="telepon" value="{{ old('telepon', $sekolahSettings->telepon ?? '') }}" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Email</label>
                        <input type="email" name="email" id="email" value="{{ old('email', $sekolahSettings->email ?? '') }}" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                </div>

                <div style="margin-bottom:16px">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Website</label>
                    <input type="url" name="website" id="website" value="{{ old('website', $sekolahSettings->website ?? '') }}" placeholder="https://" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                </div>

                <div style="display:flex;gap:10px">
                    <button type="submit" class="btn">💾 Simpan Identitas</button>
                </div>
            </form>
        </div>
    </div>

    <div style="margin-top:16px;padding:16px;background:#FBF4E4;border-left:3px solid #B8860B;border-radius:8px;font-size:13px">
        <b><i class="fas fa-info-circle"></i> Ambil Data Sekolah</b>
        <p style="margin:6px 0 0;color:#667085">Masukkan NPSN sekolah lalu klik <b>"Cari"</b> untuk mengambil data dari database lokal (hasil sinkronisasi Dapodik). Jika tidak ditemukan, akan dicoba dari API Dapodik.</p>
    </div>
@endsection

@push('scripts')
<script>
    async function lookupNpsn() {
        const npsn = document.getElementById('npsn').value.trim();
        const statusEl = document.getElementById('lookupStatus');
        const btn = document.getElementById('btnLookup');

        if (npsn.length < 3 || !/^\d+$/.test(npsn)) {
            statusEl.style.display = 'block';
            statusEl.style.color = '#dc3545';
            statusEl.textContent = 'NPSN harus minimal 3 digit angka.';
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mencari...';
        statusEl.style.display = 'block';
        statusEl.style.color = '#667085';
        statusEl.textContent = 'Mencari di database lokal...';

        try {
            const resp = await fetch('{{ route("admin.sekolah.search") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ npsn: npsn })
            });

            if (!resp.ok) {
                throw new Error('Server merespons HTTP ' + resp.status);
            }

            const contentType = resp.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                const text = await resp.text();
                throw new Error('Respons bukan JSON (kemungkinan CSRF 419 atau halaman error). Status: ' + resp.status);
            }

            const data = await resp.json();

            if (data.success && data.data) {
                fillForm(data.data);
                statusEl.style.color = '#0f766e';
                statusEl.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message + ' Silakan periksa dan edit jika perlu.';
            } else {
                statusEl.style.color = '#B8860B';
                statusEl.innerHTML = '<i class="fas fa-info-circle"></i> ' + (data.message || 'Tidak ditemukan di database lokal') + '. Mencoba dari API Dapodik...';
                await lookupFromApi(npsn, statusEl);
            }
        } catch (err) {
            statusEl.style.color = '#dc3545';
            statusEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <b>Error:</b> ' + err.message;
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-search"></i> Cari';
        }
    }

    async function lookupFromApi(npsn, statusEl) {
        try {
            const resp = await fetch('{{ url("/api/sekolah/lookup") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ npsn: npsn })
            });

            if (!resp.ok) {
                throw new Error('Server merespons HTTP ' + resp.status);
            }

            const data = await resp.json();

            if (data.success && data.data) {
                const d = data.data;
                fillForm({
                    nama_sekolah: d.nama_sekolah,
                    npsn: d.npsn || npsn,
                    jenis_sekolah: d.status,
                    alamat: d.alamat,
                    kecamatan: d.kecamatan,
                    kota: d.kabupaten,
                    provinsi: d.provinsi,
                    akreditasi: d.akreditasi,
                    email: d.email,
                    telepon: d.telepon,
                });
                statusEl.style.color = '#0f766e';
                statusEl.innerHTML = '<i class="fas fa-check-circle"></i> Data berhasil diambil dari API Dapodik. Silakan periksa dan simpan.';
            } else {
                statusEl.style.color = '#dc3545';
                statusEl.textContent = 'Data tidak ditemukan di database lokal maupun API Dapodik.';
            }
        } catch (err) {
            statusEl.style.color = '#dc3545';
            statusEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <b>Error:</b> ' + err.message;
        }
    }

    function fillForm(d) {
        document.getElementById('nama_sekolah').value = d.nama_sekolah || '';
        document.getElementById('npsn').value = d.npsn || '';
        document.getElementById('alamat').value = d.alamat || '';
        document.getElementById('kecamatan').value = d.kecamatan || '';
        document.getElementById('kota').value = d.kota || '';
        document.getElementById('provinsi').value = d.provinsi || '';
        document.getElementById('kode_pos').value = d.kode_pos || '';
        document.getElementById('telepon').value = d.telepon || '';
        document.getElementById('email').value = d.email || '';
        document.getElementById('website').value = d.website || '';
        document.getElementById('kepala_sekolah').value = d.kepala_sekolah || '';

        const jenisSel = document.getElementById('jenis_sekolah');
        if (jenisSel && d.jenis_sekolah) {
            const jenisVal = d.jenis_sekolah.toLowerCase();
            for (let opt of jenisSel.options) {
                opt.selected = (opt.value.toLowerCase() === jenisVal);
            }
        }

        const jenjangSel = document.getElementById('jenjang');
        if (jenjangSel && d.jenjang) {
            const jenjangVal = d.jenjang.toLowerCase();
            for (let opt of jenjangSel.options) {
                opt.selected = (opt.value.toLowerCase() === jenjangVal);
            }
        }

        const akredSel = document.getElementById('akreditasi');
        if (akredSel && d.akreditasi) {
            const akredVal = d.akreditasi;
            for (let opt of akredSel.options) {
                opt.selected = (opt.value === akredVal);
            }
        }
    }
</script>
@endpush
