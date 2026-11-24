@extends('layouts.admin-with-tabs')

@section('title', 'Import Presensi Mesin - SIMANTAP')
@section('page_title', 'Import Presensi Mesin')
@section('page_subtitle', 'Tarik data dari mesin fingerprint melalui database Attendance Management atau file Excel/CSV')

@section('content')
    <div class="grid grid-4" style="margin-bottom:16px">
        <div class="stat"><div class="label">Pegawai Aktif</div><div class="value">{{ $jumlahPegawai }}</div></div>
        <div class="stat ok"><div class="label">Sudah Punya NIK</div><div class="value">{{ $sudahAdaNik }}</div></div>
        <div class="stat gold"><div class="label">Belum Punya NIK</div><div class="value">{{ max($jumlahPegawai - $sudahAdaNik, 0) }}</div></div>
        <div class="stat {{ count($belumTerpetakan ?? []) > 0 ? 'warn' : 'ok' }}"><div class="label">PIN Mesin Belum Terpetakan</div><div class="value">{{ count($belumTerpetakan ?? []) }}</div></div>
    </div>

    @include('admin.presensi._pemetaan-pin-card')

    <div class="card" style="margin-bottom:16px">
        <div class="card-header"><h3>Alur Import</h3></div>
        <div class="card-body">
            <ol style="margin:0;padding-left:20px;font-size:14px;color:#344054;line-height:1.8">
                <li><strong>Tarik langsung</strong> — klik <em>Tarik Data Sekarang</em> untuk membaca database Attendance Management (<code>att2000.mdb</code>) di komputer mesin. Tidak perlu mengunduh file. Ada juga tombol <em>Tarik Semua Data (1 Klik)</em> yang otomatis menelusuri seluruh rentang scan.</li>
                <li><strong>Import data karyawan</strong> — unggah file berisi <code>NIK</code> dan <code>Nama</code>, atau klik <em>Tarik NIK dari Database Mesin</em>. Sistem mencocokkan nama ke akun guru/kepsek/tendik lalu menyimpan NIK.</li>
                <li><strong>Import log mesin</strong> — unggah export mesin (mis. <code>InOutData.xls</code>) atau template CSV. Sistem mengambil scan masuk paling awal dan pulang paling akhir per pegawai per hari.</li>
                <li>Data hasil koreksi manual, serta status <code>izin</code>/<code>sakit</code>, tidak akan ditimpa.</li>
            </ol>
            <p style="margin:12px 0 0;font-size:13px;color:#667085">Batas jam masuk untuk status terlambat saat ini: <strong>{{ config('presensi.mesin_jam_masuk', '07:15') }}</strong>. Dapat diubah melalui <code>PRESENSI_MESIN_JAM_MASUK</code> di file <code>.env</code>.</p>
        </div>
    </div>

            <div class="note note-warn" style="margin-bottom:16px">
                Mode <strong>tarik langsung</strong> saat ini memakai pembaca <code>{{ $koneksi['engine'] ?? 'auto' }}</code>. Bila SIMANTAP berjalan di hosting, gunakan <strong>Presensi Bridge</strong> (<code>presensi-bridge/</code>) di PC sekolah agar mesin absensi tetap terbaca otomatis.
            </div>

    @if($directDisabled)
        <div class="note note-warn" style="margin-bottom:16px">Tarik langsung dari database mesin dinonaktifkan untuk sekolah bertenant. Gunakan impor file sekolah sendiri atau <a href="{{ route('admin.presensi.mesin.sinkron') }}">Presensi Bridge</a> di PC sekolah.</div>
    @else
    <div class="card" style="margin-bottom:16px;border-left:4px solid {{ ($koneksi['ok'] ?? false) ? '#12805C' : '#B54708' }}">
        <div class="card-header">
            <h3>C. Tarik Langsung dari Database Mesin</h3>
            @if($koneksi['ok'] ?? false)
                <span class="btn btn-sm" style="background:#E7F6EF;color:#12805C;border:1px solid #12805C;cursor:default">Database Terhubung</span>
            @else
                <span class="btn btn-sm" style="background:#FEF3E2;color:#B54708;border:1px solid #B54708;cursor:default">Belum Siap</span>
            @endif
        </div>
        <div class="card-body">
            <p style="font-size:13px;color:#344054;margin:0 0 10px">
                Tombol di bawah membaca langsung database Attendance Management (ZKTeco) yang dipakai mesin fingerprint.
                Sistem mengambil scan masuk paling awal dan pulang paling akhir per pegawai per hari, lalu menyimpannya sebagai presensi <code>mesin</code>.
                Koreksi manual admin serta status izin/sakit/cuti tetap dilindungi.
            </p>

            <div style="display:flex;gap:8px 18px;flex-wrap:wrap;font-size:13px;color:#475467;margin-bottom:12px">
                <span>Database: <code>{{ $koneksi['path'] ?? 'belum diatur' }}</code></span>
                <span>Pembaca: <strong>{{ $koneksi['engine'] ?? '-' }}</strong></span>
                @if(isset($koneksi['jumlah_pegawai']) && ($koneksi['engine'] ?? '') !== 'tcp')
                    <span>Pegawai di mesin: <strong>{{ $koneksi['jumlah_pegawai'] }}</strong></span>
                    <span>Total scan: <strong>{{ $koneksi['jumlah_scan'] }}</strong></span>
                @endif
                @if(!empty($koneksi['terakhir_scan']))
                    <span>Scan terakhir: <strong>{{ \Illuminate\Support\Carbon::parse($koneksi['terakhir_scan'])->format('d/m/Y H:i') }}</strong></span>
                @endif
                @if($terakhirTarik)
                    <span>Terakhir tarik: <strong>{{ \Illuminate\Support\Carbon::parse($terakhirTarik)->format('d/m/Y H:i') }}</strong></span>
                @endif
            </div>

            @if(!($koneksi['ok'] ?? false))
                <div style="background:#FEF3E2;border:1px solid #F5C27A;color:#B54708;border-radius:8px;padding:10px 12px;font-size:13px;margin-bottom:12px">
                    {{ $koneksi['pesan'] ?? 'Database mesin belum dapat dibaca.' }}
                    <br>
                    Atur lokasi database pada <code>PRESENSI_MESIN_DB_PATH</code> di file <code>.env</code>, misalnya
                    <code>PRESENSI_MESIN_DB_PATH="C:\\Program Files (x86)\\ZKTime\\bin\\att2000.mdb"</code>, lalu muat ulang halaman ini.
                </div>
            @endif

            @if(!empty($koneksi['terakhir_scan']) && \Illuminate\Support\Carbon::parse($koneksi['terakhir_scan'])->lt(now()->subDays(2)))
                <div style="background:#FEF3E2;border:1px solid #F5C27A;color:#B54708;border-radius:8px;padding:10px 12px;font-size:13px;margin-bottom:12px">
                    Scan terakhir di database mesin: <strong>{{ \Illuminate\Support\Carbon::parse($koneksi['terakhir_scan'])->format('d/m/Y H:i') }}</strong>.
                    Kemungkinan mesin fingerprint atau aplikasi ZKTime/Attendance Management belum menyinkronkan data terbaru.
                    Periksa koneksi mesin, lalu klik <em>Tarik Data Sekarang</em>.
                </div>
            @endif

            <form action="{{ route('admin.presensi.mesin.tarik') }}" method="POST" id="form-tarik" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
                @csrf
                <div>
                    <label for="fld-presensi-mesin-import-dari" class="form-label">Dari tanggal</label>
                    <input id="fld-presensi-mesin-import-dari" type="date" name="dari" value="{{ old('dari', now()->subDays(6)->toDateString()) }}" max="{{ now()->toDateString() }}" required class="form-control">
                </div>
                <div>
                    <label for="fld-presensi-mesin-import-sampai" class="form-label">Sampai tanggal</label>
                    <input id="fld-presensi-mesin-import-sampai" type="date" name="sampai" value="{{ old('sampai', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required class="form-control">
                </div>
                <div>
                    <button type="button" id="btn-tes" class="btn btn-sm">Tes Koneksi</button>
                </div>
                <div>
                    <button type="submit" id="btn-tarik" class="btn btn-sm" style="background:#1F3864;color:#fff">Tarik Data Sekarang</button>
                </div>
            </form>

            <form action="{{ route('admin.presensi.mesin.tarik.semua') }}" method="POST" id="form-tarik-semua" style="margin-top:12px">
                @csrf
                <button type="submit" id="btn-tarik-semua" class="btn btn-sm" style="background:#12805C;color:#fff" onclick="return confirm('Tarik SEMUA data presensi dari database mesin sekarang? Proses ini akan menelusuri seluruh rentang scan tersedia.')">Tarik Semua Data (1 Klik)</button>
            </form>

            <div style="border-top:1px dashed #D0D5DD;margin-top:12px;padding-top:12px">
                <p style="font-size:13px;color:#344054;margin:0 0 10px">Live Monitor — baca scan terbaru dari mesin ZKTeco via TCP.</p>
                <button type="button" class="btn btn-sm" id="btn-baca-live" style="background:#1F3864;color:#fff">Baca Scan Terkini</button>
                <div id="hasil-live" style="font-size:13px;margin-top:10px;color:#475467;white-space:pre-wrap"></div>
            </div>

            <form action="{{ route('admin.presensi.mesin.pengaturan') }}" method="POST" style="margin-top:12px;border-top:1px dashed #D0D5DD;padding-top:12px">
                @csrf
                <div style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
                    <div>
                        <label for="fld-presensi-mesin-import-mesin-engine" class="form-label">Pembaca mesin (engine)</label>
                        <select id="fld-presensi-mesin-import-mesin-engine" name="mesin_engine" class="form-control">
                            @foreach(['auto' => 'Auto (Default)', 'tcp' => 'TCP Langsung (ZKTeco)', 'powershell' => 'Database (PowerShell)', 'odbc' => 'Database (ODBC)'] as $nilai => $label)
                                <option value="{{ $nilai }}" {{ (old('mesin_engine', $mesinEngineTerpilih ?? 'auto')) === $nilai ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-sm" style="background:#1F3864;color:#fff">Simpan Pengaturan</button>
                    </div>
                </div>
                <p style="font-size:12px;color:#667085;margin:8px 0 0">Pengaturan disimpan di tabel settings dan langsung dipakai oleh tombol Tarik / Tes Koneksi.</p>
            </form>

            @error('mesin_engine')
                <div style="color:var(--bad);font-size:13px;margin-top:8px">{{ $message }}</div>
            @enderror

            <div id="hasil-tes" style="font-size:13px;margin-top:10px;color:#475467"></div>

            @error('mesin')
                <div style="color:var(--bad);font-size:13px;margin-top:8px">{{ $message }}</div>
            @enderror
            @error('dari')
                <div style="color:var(--bad);font-size:13px;margin-top:8px">{{ $message }}</div>
            @enderror
            @error('sampai')
                <div style="color:var(--bad);font-size:13px;margin-top:8px">{{ $message }}</div>
            @enderror
        </div>
    </div>

    @endif

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px">
        <div class="card">
            <div class="card-header">
                <h3>A. Import Data Karyawan (NIK)</h3>
                <a href="{{ route('admin.presensi.mesin.template.karyawan') }}" class="btn btn-sm">Unduh Template</a>
            </div>
            <div class="card-body">
                <p style="font-size:13px;color:#344054;margin:0 0 10px">Header wajib: <code>nik,nama</code>. Format yang didukung: XLS, XLSX, CSV. Nama yang tidak cocok atau ambigu akan dilewati dan dilaporkan.</p>
                <form action="{{ route('admin.presensi.mesin.karyawan.import') }}" method="POST" enctype="multipart/form-data" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
                    @csrf
                    <div style="flex:1;min-width:220px">
                        <label for="fld-presensi-mesin-import-file" class="form-label">File data karyawan (maks 10MB)</label>
                        <input id="fld-presensi-mesin-import-file" type="file" name="file" accept=".xls,.xlsx,.csv,.txt" required class="form-control">
                    </div>
                    <div style="width:100%;display:flex;gap:12px;align-items:center;flex-wrap:wrap;font-size:13px;color:#344054">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
                            <input type="checkbox" name="buat_akun_otomatis" value="1" {{ ($opsiAutoAkun['aktif'] ?? false) ? 'checked' : '' }}>
                            <span>Buat akun otomatis jika nama belum ada</span>
                        </label>
                        <select name="peran_otomatis" class="form-control" style="width:auto;padding:4px 8px;font-size:13px" aria-label="Peran akun otomatis saat import karyawan">
                            <option value="tendik" {{ ($opsiAutoAkun['peran'] ?? 'tendik') === 'tendik' ? 'selected' : '' }}>Peran: Tendik</option>
                            <option value="guru" {{ ($opsiAutoAkun['peran'] ?? 'tendik') === 'guru' ? 'selected' : '' }}>Peran: Guru</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-sm">Import NIK</button>
                    </div>
                </form>
                @unless($directDisabled)
                <div style="border-top:1px dashed #D0D5DD;margin:12px 0;padding-top:12px">
                    <p style="font-size:13px;color:#344054;margin:0 0 8px">Atau ambil daftar <code>NIK</code> dan <code>Nama</code> langsung dari tabel <code>USERINFO</code> pada database mesin yang sedang terhubung.</p>
                    <form action="{{ route('admin.presensi.mesin.karyawan.tarik') }}" method="POST" onsubmit="return confirm('Tarik NIK karyawan dari database mesin sekarang?')">
                        @csrf
                        <button type="submit" class="btn btn-sm" style="background:#1F3864;color:#fff">Tarik NIK dari Database Mesin</button>
                    </form>
                    @error('mesin')
                        <div style="color:var(--bad);font-size:13px;margin-top:8px">{{ $message }}</div>
                    @enderror
                </div>
                @endunless
                @error('file')
                    <div style="color:var(--bad);font-size:13px;margin-top:8px">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>B. Import Log Mesin</h3>
                <a href="{{ route('admin.presensi.mesin.template.log') }}" class="btn btn-sm">Unduh Template</a>
            </div>
            <div class="card-body">
                <p style="font-size:13px;color:#344054;margin:0 0 10px">Mendukung export mesin (<code>Nama</code>, <code>No.PIN</code>, <code>Tgl/Waktu</code>, <code>Status</code>) maupun template <code>nik,tanggal,jam_masuk,jam_pulang</code>.</p>
                <form action="{{ route('admin.presensi.mesin.log.import') }}" method="POST" enctype="multipart/form-data" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
                    @csrf
                    <div style="flex:1;min-width:220px">
                        <label for="fld-presensi-mesin-import-file-2" class="form-label">File log mesin (maks 10MB)</label>
                        <input id="fld-presensi-mesin-import-file-2" type="file" name="file" accept=".xls,.xlsx,.csv,.txt" required class="form-control">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-sm">Import Log</button>
                    </div>
                </form>
                @error('file')
                    <div style="color:var(--bad);font-size:13px;margin-top:8px">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        const tombolTes = document.getElementById('btn-tes');
        const kotakHasil = document.getElementById('hasil-tes');
        const form = document.getElementById('form-tarik');
        const tombolTarik = document.getElementById('btn-tarik');

        if (tombolTes) {
            tombolTes.addEventListener('click', async function () {
                tombolTes.disabled = true;
                kotakHasil.style.color = '#475467';
                kotakHasil.textContent = 'Menguji koneksi ke database mesin...';

                try {
                    const res = await fetch(@json(route('admin.presensi.mesin.tes')), {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': @json(csrf_token()),
                            'Accept': 'application/json',
                        },
                    });

                    const data = await res.json();
                    kotakHasil.style.color = data.ok ? '#12805C' : 'var(--bad)';

                    let teks = data.pesan || '';
                    if (data.ok) {
                        teks += ' Pembaca: ' + data.engine + '.';
                        if (data.jumlah_pegawai !== undefined) {
                            teks += ' Pegawai di mesin: ' + data.jumlah_pegawai + '.';
                        }
                        if (data.jumlah_scan !== undefined) {
                            teks += ' Total scan: ' + data.jumlah_scan + '.';
                        }
                    }
                    kotakHasil.textContent = teks;
                } catch (e) {
                    kotakHasil.style.color = 'var(--bad)';
                    kotakHasil.textContent = 'Gagal menghubungi server: ' + e.message;
                } finally {
                    tombolTes.disabled = false;
                }
            });
        }

        if (form && tombolTarik) {
            form.addEventListener('submit', function () {
                tombolTarik.disabled = true;
                tombolTarik.textContent = 'Menarik data...';
            });
        }

        const formSemua = document.getElementById('form-tarik-semua');
        const tombolTarikSemua = document.getElementById('btn-tarik-semua');

        if (formSemua && tombolTarikSemua) {
            formSemua.addEventListener('submit', function () {
                tombolTarikSemua.disabled = true;
                tombolTarikSemua.textContent = 'Menarik semua data...';
            });
        }

        const tombolLive = document.getElementById('btn-baca-live');
        const kotakLive = document.getElementById('hasil-live');

        if (tombolLive) {
            tombolLive.addEventListener('click', async function () {
                tombolLive.disabled = true;
                kotakLive.style.color = '#475467';
                kotakLive.textContent = 'Membaca scan terbaru dari mesin...';

                try {
                    const res = await fetch(@json(route('admin.presensi.mesin.live')), {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                        },
                    });

                    const data = await res.json();
                    if (data.ok) {
                        kotakLive.style.color = '#12805C';
                        const daftar = (data.scans ?? []).map((s) => (s.waktu ?? '-') + ' | NIK ' + (s.nik ?? '-') + ' | ' + (s.jenis ?? '')).join('\n');
                        kotakLive.textContent = 'Mesin OK. Scan terkini:\n' + (daftar || 'Belum ada scan.');
                    } else {
                        kotakLive.style.color = 'var(--bad)';
                        kotakLive.textContent = 'Gagal: ' + (data.pesan || 'Tidak ada data');
                    }
                } catch (e) {
                    kotakLive.style.color = 'var(--bad)';
                    kotakLive.textContent = 'Gagal menghubungi server: ' + e.message;
                } finally {
                    tombolLive.disabled = false;
                }
            });
        }
    })();
</script>
@endpush
