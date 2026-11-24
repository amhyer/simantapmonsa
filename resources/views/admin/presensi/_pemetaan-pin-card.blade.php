<div class="card" style="margin-bottom:16px;border-left:4px solid {{ count($belumTerpetakan ?? []) > 0 ? '#B54708' : '#12805C' }}">
    <div class="card-header" style="gap:12px;flex-wrap:wrap">
        <div style="margin-right:auto">
            <h3 style="margin:0">Pemetaan PIN Mesin &amp; Opsi Buat Akun Otomatis</h3>
            <p style="margin:4px 0 0;font-size:12px;color:#667085">
                Atur apakah nama baru dari mesin absensi langsung dibuatkan akun otomatis, atau dipasangkan manual lewat tabel di bawah.
            </p>
        </div>
        <span class="badge {{ count($belumTerpetakan ?? []) > 0 ? 'badge-warning' : 'badge-success' }}">
            {{ count($belumTerpetakan ?? []) }} PIN Belum Terhubung
        </span>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.presensi.mesin.pengaturan-akun') }}" method="POST" style="display:flex;gap:14px;align-items:end;flex-wrap:wrap;padding-bottom:14px;border-bottom:1px dashed #D0D5DD">
            @csrf
            <div style="flex:1;min-width:260px">
                <label class="form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:4px">
                    <input type="checkbox" name="auto_akun" value="1" {{ ($opsiAutoAkun['aktif'] ?? false) ? 'checked' : '' }}>
                    <span><strong>Buat Akun Otomatis</strong> saat tarik/impor nama pegawai yang belum ada di SIMANTAP</span>
                </label>
                <span style="font-size:12px;color:#667085">
                    Jika tidak dicentang, nama yang tidak cocok akan ditampung pada tabel Pemetaan PIN di bawah agar Anda bisa memilih akun lewat dropdown atau klik Buat Akun.
                </span>
            </div>
            <div>
                <label for="fld-presensi-default-peran" class="form-label">Peran Akun Baru Default</label>
                <select id="fld-presensi-default-peran" name="default_peran" class="form-control">
                    <option value="tendik" {{ ($opsiAutoAkun['peran'] ?? 'tendik') === 'tendik' ? 'selected' : '' }}>Tendik (Tenaga Kependidikan)</option>
                    <option value="guru" {{ ($opsiAutoAkun['peran'] ?? 'tendik') === 'guru' ? 'selected' : '' }}>Guru</option>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-sm" style="background:#1F3864;color:#fff">Simpan Perlakuan Akun</button>
            </div>
        </form>

        @error('pemetaan')
            <div style="background:#FEF3E2;border:1px solid #F5C27A;color:var(--bad);border-radius:8px;padding:10px 12px;font-size:13px;margin-top:12px">
                {{ $message }}
            </div>
        @enderror

        @if(count($belumTerpetakan ?? []) > 0)
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin:14px 0 10px">
                <div style="font-size:13px;color:#344054">
                    Daftar nama/PIN dari mesin absensi yang belum terhubung ke akun pegawai sekolah ini:
                </div>
                <form action="{{ route('admin.presensi.mesin.pemetaan.buat-semua') }}" method="POST" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap" onsubmit="return confirm('Buat akun baru otomatis untuk seluruh PIN mesin yang belum terhubung di bawah ini?')">
                    @csrf
                    <select name="peran" class="form-control" style="width:auto;padding:4px 8px;font-size:13px" aria-label="Peran untuk buat semua akun">
                        <option value="tendik" {{ ($opsiAutoAkun['peran'] ?? 'tendik') === 'tendik' ? 'selected' : '' }}>Sebagai Tendik</option>
                        <option value="guru" {{ ($opsiAutoAkun['peran'] ?? 'tendik') === 'guru' ? 'selected' : '' }}>Sebagai Guru</option>
                    </select>
                    <button type="submit" class="btn btn-sm" style="background:#12805C;color:#fff">
                        Buat Akun untuk Semua ({{ count($belumTerpetakan) }} PIN)
                    </button>
                </form>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>PIN / NIK Mesin</th>
                            <th>Nama di Mesin</th>
                            <th>Status</th>
                            <th>Pasangkan ke Akun Pegawai (Dropdown)</th>
                            <th>Atau Buat Akun Baru (1 Klik)</th>
                            <th style="text-align:center">Abaikan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($belumTerpetakan as $idx => $item)
                            <tr>
                                <td><code>{{ $item['nik'] }}</code></td>
                                <td>
                                    <strong>{{ $item['nama'] !== '' ? $item['nama'] : '(Tanpa Nama - dari Log Scan)' }}</strong>
                                </td>
                                <td>
                                    @if(($item['alasan'] ?? '') === 'ambigu')
                                        <span class="badge badge-warning">Nama Ambigu (&gt;1 Akun)</span>
                                    @elseif(($item['alasan'] ?? '') === 'bentrok')
                                        <span class="badge badge-danger">PIN Bentrok</span>
                                    @elseif(($item['alasan'] ?? '') === 'nik_tidak_dikenal')
                                        <span class="badge badge-warning">PIN Belum Dikenal</span>
                                    @else
                                        <span class="badge badge-warning">Nama Tidak Cocok</span>
                                    @endif
                                </td>
                                <td>
                                    <form action="{{ route('admin.presensi.mesin.pemetaan.pasangkan') }}" method="POST" style="display:flex;gap:6px;align-items:center">
                                        @csrf
                                        <input type="hidden" name="nik" value="{{ $item['nik'] }}">
                                        <select name="user_id" required class="form-control" style="min-width:190px;padding:4px 8px;font-size:13px" aria-label="Pilih akun pegawai untuk PIN {{ $item['nik'] }}">
                                            <option value="">-- Pilih Akun Pegawai --</option>
                                            @foreach($daftarPegawai ?? [] as $peg)
                                                <option value="{{ $peg->id }}">
                                                    {{ $peg->nama_lengkap }} ({{ strtoupper($peg->peran) }}){{ filled($peg->nik) ? ' [PIN: '.$peg->nik.']' : ' [Belum ada PIN]' }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-sm" style="background:#1F3864;color:#fff;white-space:nowrap">Pasangkan</button>
                                    </form>
                                </td>
                                <td>
                                    <form action="{{ route('admin.presensi.mesin.pemetaan.buat-akun') }}" method="POST" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                                        @csrf
                                        <input type="hidden" name="nik" value="{{ $item['nik'] }}">
                                        <input type="text" name="nama" value="{{ $item['nama'] !== '' ? $item['nama'] : 'Pegawai PIN '.$item['nik'] }}" required class="form-control" style="width:150px;padding:4px 8px;font-size:13px" placeholder="Nama lengkap" aria-label="Nama lengkap akun baru untuk PIN {{ $item['nik'] }}">
                                        <select name="peran" class="form-control" style="width:auto;padding:4px 8px;font-size:13px" aria-label="Peran akun baru untuk PIN {{ $item['nik'] }}">
                                            <option value="tendik" {{ ($opsiAutoAkun['peran'] ?? 'tendik') === 'tendik' ? 'selected' : '' }}>Tendik</option>
                                            <option value="guru" {{ ($opsiAutoAkun['peran'] ?? 'tendik') === 'guru' ? 'selected' : '' }}>Guru</option>
                                        </select>
                                        <button type="submit" class="btn btn-sm" style="background:#12805C;color:#fff;white-space:nowrap">Buat Akun</button>
                                    </form>
                                </td>
                                <td style="text-align:center">
                                    <form action="{{ route('admin.presensi.mesin.pemetaan.hapus') }}" method="POST" onsubmit="return confirm('Hapus PIN {{ $item['nik'] }} dari daftar antrean pemetaan?')">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="nik" value="{{ $item['nik'] }}">
                                        <button type="submit" class="btn btn-sm btn-ghost" title="Abaikan PIN ini">&times;</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p style="margin:12px 0 0;font-size:13px;color:#12805C">
                Tidak ada PIN/nama mesin absensi yang tertahan. Seluruh data yang terakhir diimpor sudah terhubung ke akun pegawai.
            </p>
        @endif
    </div>
</div>
