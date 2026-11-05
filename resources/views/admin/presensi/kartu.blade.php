@extends('layouts.admin-with-tabs')

@section('title', 'Kartu Identitas & QR Presensi - SIMANTAP')
@section('page_title', 'Kartu Identitas & QR Presensi')
@section('page_subtitle', 'Terbitkan, cetak, dan hubungkan Kartu Identitas QR untuk Siswa, Guru, serta Pegawai')

@section('content')
    <div class="grid grid-4" style="margin-bottom:16px">
        <div class="stat"><div class="label">Siswa Aktif</div><div class="value">{{ $ringkasan['siswa_total'] ?? 0 }}</div></div>
        <div class="stat ok"><div class="label">Kartu Siswa Aktif</div><div class="value">{{ $ringkasan['siswa_berkartu'] ?? 0 }}</div></div>
        <div class="stat primary"><div class="label">Guru &amp; Pegawai</div><div class="value">{{ $ringkasan['pegawai_total'] ?? 0 }}</div></div>
        <div class="stat gold"><div class="label">Kartu Pegawai Aktif</div><div class="value">{{ $ringkasan['pegawai_berkartu'] ?? 0 }}</div></div>
    </div>

    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
        <a href="{{ route('admin.presensi.kartu.index', ['jenis' => 'siswa']) }}"
           class="btn btn-sm {{ ($jenis ?? 'siswa') === 'siswa' ? '' : 'btn-ghost' }}">
            🎓 Kartu Identitas Siswa
        </a>
        <a href="{{ route('admin.presensi.kartu.index', ['jenis' => 'pegawai']) }}"
           class="btn btn-sm {{ ($jenis ?? 'siswa') === 'pegawai' ? '' : 'btn-ghost' }}">
            🪪 Kartu Identitas Guru &amp; Pegawai
        </a>
    </div>

    @if(($jenis ?? 'siswa') === 'pegawai')
        <div class="note note-ok" style="margin-bottom:16px">
            Setiap Guru, Kepala Sekolah, dan Tenaga Kependidikan memiliki <strong>Kartu Identitas Ber-QR</strong> untuk <strong>scan mandiri</strong> jam masuk &amp; pulang pada menu <em>Presensi Saya</em>. Sistem hanya menyimpan fingerprint HMAC terenkripsi dari kode QR.
        </div>

        <div class="card" style="margin-bottom:16px">
            <div class="card-header" style="gap:10px;flex-wrap:wrap">
                <h3 style="margin-right:auto">⚡ Penerbitan &amp; Cetak Massal Kartu Pegawai</h3>
                <form method="POST" action="{{ route('admin.presensi.kartu.pegawai.issue-massal') }}" style="display:inline">
                    @csrf
                    <button type="submit" class="btn btn-sm">Terbitkan Kartu yang Belum Ada</button>
                </form>
                <a href="{{ route('admin.presensi.kartu.pegawai.cetak-massal', array_filter(['peran' => request('peran')])) }}" class="btn btn-ghost btn-sm">
                    🖨️ Cetak Semua Kartu Pegawai
                </a>
            </div>
            <div class="card-body">
                <details>
                    <summary style="cursor:pointer;font-weight:600;font-size:13px;color:#344054">📷 Opsional: Hubungkan Kartu Fisik Eksternal Guru/Pegawai</summary>
                    <div data-presensi-qr data-mode="bind-staff-card"
                         data-endpoint-template="{{ route('admin.presensi.kartu.pegawai.bind', ['pegawai' => '__PEGAWAI_UUID__']) }}"
                         style="margin-top:12px">
                        <p style="margin-top:0"><strong>Pegawai terpilih:</strong> <span data-qr-selected-staff-name>Belum dipilih</span></p>
                        <div data-qr-reader style="width:100%;max-width:480px;margin:10px auto"></div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                            <button type="button" class="btn btn-sm" data-qr-start>Aktifkan kamera</button>
                            <button type="button" class="btn btn-ghost btn-sm" data-qr-switch hidden>Ganti kamera</button>
                            <button type="button" class="btn btn-ghost btn-sm" data-qr-stop disabled>Hentikan kamera</button>
                            <input type="text" data-qr-payload autocomplete="off" placeholder="Atau scan/tempel isi QR kartu fisik" style="flex:1;min-width:220px">
                            <button type="button" class="btn btn-ghost btn-sm" data-qr-submit-manual>Hubungkan</button>
                        </div>
                        <div data-qr-feedback role="status" aria-live="polite" style="margin-top:10px;padding:10px;border-radius:8px;background:#F2F4F7" data-kind="info">Pilih pegawai dari tabel, lalu pindai kartu fisiknya.</div>
                    </div>
                </details>
            </div>
        </div>

        <div class="card">
            <div class="card-header" style="gap:10px;flex-wrap:wrap">
                <h3 style="margin-right:auto">Daftar Guru &amp; Pegawai</h3>
                <form method="GET" action="{{ route('admin.presensi.kartu.index') }}" style="display:flex;gap:6px;flex-wrap:wrap">
                    <input type="hidden" name="jenis" value="pegawai">
                    <select name="peran">
                        <option value="">Semua Peran</option>
                        <option value="guru" @selected(request('peran') === 'guru')>Guru</option>
                        <option value="kepsek" @selected(request('peran') === 'kepsek')>Kepala Sekolah</option>
                        <option value="tendik" @selected(request('peran') === 'tendik')>Tendik</option>
                    </select>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama / NIP / username…">
                    <button type="submit" class="btn btn-sm">Filter</button>
                </form>
            </div>
            <div class="card-body tight">
                <div class="table-wrapper"><table>
                    <thead>
                        <tr>
                            <th>Nama Lengkap</th>
                            <th>Peran</th>
                            <th>NIP / ID Pengguna</th>
                            <th>Status Kartu Identitas</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($pegawai as $person)
                        <tr>
                            <td><strong>{{ $person->nama_lengkap }}</strong></td>
                            <td><span class="tag tag-primary">{{ ucfirst($person->peran) }}</span></td>
                            <td>{{ $person->nip ?: $person->nama_pengguna }}</td>
                            <td>
                                @if($person->qr_token_hash && (int) $person->qr_card_version > 0)
                                    <span class="tag tag-ok">Kartu QR Sistem (v{{ $person->qr_card_version }})</span>
                                @elseif($person->qr_token_hash)
                                    <span class="tag tag-gold">Kartu Fisik Eksternal</span>
                                @else
                                    <span class="tag tag-warn">Belum diterbitkan</span>
                                @endif
                            </td>
                            <td style="white-space:nowrap;display:flex;gap:6px;flex-wrap:wrap">
                                <a href="{{ route('admin.presensi.kartu.pegawai.cetak', $person) }}" class="btn btn-sm">🖨️ Cetak Kartu</a>
                                <form method="POST" action="{{ route('admin.presensi.kartu.pegawai.issue', $person) }}" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost btn-sm">{{ $person->qr_token_hash ? '🔄 Ganti QR' : '⚡ Terbitkan QR' }}</button>
                                </form>
                                <button type="button" class="btn btn-ghost btn-sm" data-qr-select-staff="{{ $person->uuid }}" data-staff-name="{{ $person->nama_lengkap }}">Kartu Fisik</button>
                                @if($person->qr_token_hash)
                                    <form method="POST" action="{{ route('admin.presensi.kartu.pegawai.reset', $person) }}" style="display:inline" onsubmit="return confirm('Lepaskan QR kartu {{ addslashes($person->nama_lengkap) }}?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-ghost btn-sm" style="color:#B42318">Lepaskan</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="text-align:center;padding:24px">Belum ada guru atau pegawai aktif.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
    @else
        <div class="note note-warn" style="margin-bottom:16px">
            Kartu Identitas Siswa dipindai oleh <strong>Guru yang mengajar pada hari itu</strong> sesuai jadwal mata pelajaran maupun wali kelas. Anda dapat menerbitkan &amp; mencetak kartu resmi ber-QR langsung dari sistem atau memetakan kartu fisik lama tanpa menyimpan kode QR mentah di basis data.
        </div>

        <div class="card" style="margin-bottom:16px">
            <div class="card-header" style="gap:10px;flex-wrap:wrap">
                <h3 style="margin-right:auto">⚡ Penerbitan &amp; Cetak Kartu Siswa per Kelas</h3>
                <form method="POST" action="{{ route('admin.presensi.kartu.siswa.issue-massal') }}" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                    @csrf
                    <input type="hidden" name="rombel_id" value="{{ request('rombel_id') }}">
                    <button type="submit" class="btn btn-sm">Terbitkan Kartu yang Belum Ada</button>
                </form>
                <a href="{{ route('admin.presensi.kartu.siswa.cetak-massal', array_filter(['rombel_id' => request('rombel_id')])) }}" class="btn btn-ghost btn-sm">
                    🖨️ Cetak Kartu {{ request('rombel_id') ? 'Kelas Terpilih' : 'Semua Siswa' }}
                </a>
            </div>
            <div class="card-body">
                <details>
                    <summary style="cursor:pointer;font-weight:600;font-size:13px;color:#344054">📷 Opsional: Scan / Hubungkan Kartu Fisik Siswa yang Sudah Tercetak</summary>
                    <div data-presensi-qr data-mode="bind-card"
                         data-endpoint-template="{{ route('admin.presensi.kartu.bind', ['siswa' => '__SISWA_ID__']) }}"
                         style="margin-top:12px">
                        <p style="margin-top:0"><strong>Siswa terpilih:</strong> <span data-qr-selected-name>Belum dipilih</span></p>
                        <div data-qr-reader style="width:100%;max-width:520px;margin:12px auto"></div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                            <button type="button" class="btn btn-sm" data-qr-start>Aktifkan kamera</button>
                            <button type="button" class="btn btn-ghost btn-sm" data-qr-switch hidden>Ganti kamera</button>
                            <button type="button" class="btn btn-ghost btn-sm" data-qr-stop disabled>Hentikan kamera</button>
                            <input type="text" data-qr-payload autocomplete="off" placeholder="Atau scan/tempel isi QR di sini" style="flex:1;min-width:220px">
                            <button type="button" class="btn btn-ghost btn-sm" data-qr-submit-manual>Hubungkan</button>
                        </div>
                        <div data-qr-feedback role="status" aria-live="polite" style="margin-top:10px;padding:10px;border-radius:8px;background:#F2F4F7" data-kind="info">Pilih siswa dari tabel, lalu pindai kartunya.</div>
                    </div>
                </details>
            </div>
        </div>

        <div class="card">
            <div class="card-header" style="gap:10px;flex-wrap:wrap">
                <h3 style="margin-right:auto">Daftar Siswa</h3>
                <form method="GET" action="{{ route('admin.presensi.kartu.index') }}" style="display:flex;gap:6px;flex-wrap:wrap">
                    <input type="hidden" name="jenis" value="siswa">
                    <select name="rombel_id">
                        <option value="">Semua Kelas / Rombel</option>
                        @foreach($rombels as $rombel)
                            <option value="{{ $rombel->id }}" @selected((int) request('rombel_id') === (int) $rombel->id)>{{ $rombel->nama_rombel }}</option>
                        @endforeach
                    </select>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama, NIS, atau NISN…">
                    <button type="submit" class="btn btn-sm">Cari</button>
                </form>
            </div>
            <div class="card-body tight">
                <div class="table-wrapper"><table>
                    <thead><tr><th>Nama Siswa</th><th>NIS / NISN</th><th>Kelas</th><th>Status Kartu</th><th>Aksi</th></tr></thead>
                    <tbody>
                    @forelse($students as $student)
                        <tr>
                            <td><strong>{{ $student->nama_peserta_didik }}</strong></td>
                            <td>{{ $student->nis }}{{ $student->nisn ? ' / ' . $student->nisn : '' }}</td>
                            <td>{{ $student->rombel?->nama_rombel ?? $student->kelas ?? '—' }}</td>
                            <td>
                                @if($student->qr_token_hash && (int) $student->qr_card_version > 0)
                                    <span class="tag tag-ok">Kartu QR Sistem (v{{ $student->qr_card_version }})</span>
                                @elseif($student->qr_token_hash)
                                    <span class="tag tag-gold">Kartu Fisik Terhubung</span>
                                @else
                                    <span class="tag tag-warn">Belum diterbitkan</span>
                                @endif
                            </td>
                            <td style="white-space:nowrap;display:flex;gap:6px;flex-wrap:wrap">
                                <a href="{{ route('admin.presensi.kartu.siswa.cetak', $student) }}" class="btn btn-sm">🖨️ Cetak Kartu</a>
                                <form method="POST" action="{{ route('admin.presensi.kartu.siswa.issue', $student) }}" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost btn-sm">{{ $student->qr_token_hash ? '🔄 Ganti QR' : '⚡ Terbitkan QR' }}</button>
                                </form>
                                <button type="button" class="btn btn-ghost btn-sm" data-qr-select-student="{{ $student->id }}" data-student-name="{{ $student->nama_peserta_didik }}">Kartu Fisik</button>
                                @if($student->qr_token_hash)
                                    <form method="POST" action="{{ route('admin.presensi.kartu.reset', $student) }}" style="display:inline" onsubmit="return confirm('Lepaskan QR kartu {{ addslashes($student->nama_peserta_didik) }}? Kartu tidak dapat dipindai sampai diterbitkan atau dihubungkan lagi.')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-ghost btn-sm" style="color:#B42318">Lepaskan</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="text-align:center;padding:24px">Siswa tidak ditemukan.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
                <div style="padding:14px">{{ $students->links() }}</div>
            </div>
        </div>
    @endif
@endsection
