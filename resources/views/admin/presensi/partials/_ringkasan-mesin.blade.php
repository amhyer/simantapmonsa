@php
    $konten = $rekap;
    if (is_object($konten)) {
        $konten = $konten->toArray();
    }
    $rekap = (array) $konten;
    $catatan = is_object($catatan) ? $catatan->keyBy('user_id') : collect();
    $tanggal = $tanggal ?? now()->toDateString();
    $stat = [
        'total_pegawai' => $rekap['total_pegawai'] ?? 0,
        'hadir' => $rekap['hadir'] ?? 0,
        'terlambat' => $rekap['terlambat'] ?? 0,
        'belum_tercatat' => $rekap['belum_tercatat'] ?? 0,
        'izin_sakit_cuti' => $rekap['izin_sakit_cuti'] ?? 0,
        'belum_pulang' => $rekap['belum_pulang'] ?? 0,
    ];
@endphp

<div class="card" style="margin-bottom:16px">
    <div class="card-header">
        <h3 style="margin-right:auto">Ringkasan Presensi Mesin — {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('l, d F Y') }}</h3>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            @if(request()->routeIs('admin.presensi.mesin.ringkasan'))
                <form method="POST" action="{{ route('admin.presensi.mesin.ringkasan.flush') }}" style="margin:0">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-ghost">Reset ringkasan</button>
                </form>
            @endif
            <form method="POST" action="{{ route('admin.presensi.mesin.ringkasan.pull') }}" style="margin:0" id="pull-ringkasan">
                @csrf
                <input type="hidden" name="tanggal" value="{{ $tanggal }}">
                <button type="submit" class="btn btn-sm">Tarik presensi mesin</button>
            </form>
        </div>
    </div>
    <div class="card-body tight">
        <p style="margin:0 0 12px;color:#667085;font-size:12px">
            Ringkasan berikut dibaca dari data presensi mesin (fingerprint) yang sudah
            terimpor. Pengaturan cron <code>presensi:mesin-sync</code> akan mengisi/refresh
            ringkasan ini secara berkala tanpa menghapus koreksi manual admin.
        </p>
        <div class="grid grid-4" style="gap:12px">
            <div class="stat ok"><div class="label">Hadir / Terlambat</div><div class="value">{{ $stat['hadir'] }}</div></div>
            <div class="stat warn"><div class="label">Belum Tercatat</div><div class="value">{{ $stat['belum_tercatat'] }}</div></div>
            <div class="stat"><div class="label">Izin / Sakit / Cuti</div><div class="value">{{ $stat['izin_sakit_cuti'] }}</div></div>
            <div class="stat"><div class="label">Belum Pulang</div><div class="value">{{ $stat['belum_pulang'] }}</div></div>
        </div>

        @if($pulled ?? false)
            <div class="note note-ok" style="margin-top:12px"><i class="fas fa-check-circle"></i> Ringkasan berhasil diperbarui dari mesin.</div>
        @endif

        <div class="table-wrapper" style="margin-top:12px"><table>
            <thead>
                <tr>
                    <th>Nama Pegawai</th>
                    <th>Status</th>
                    <th>Masuk</th>
                    <th>Pulang</th>
                    <th>Dari</th>
                </tr>
            </thead>
            <tbody>
                @forelse($catatan as $person)
                    @php $record = $catatan->get($person->id); @endphp
                    <tr>
                        <td style="white-space:nowrap"><strong>{{ $person->nama_lengkap }}</strong><br><small>{{ ucfirst($person->peran) }}</small></td>
                        <td>
                            @if(!$record)
                                <span class="tag tag-mut">Belum tercatat</span>
                            @else
                                <span class="tag {{ in_array($record->status, ['hadir', 'terlambat'], true) ? 'tag-ok' : 'tag-warn' }}">{{ ucfirst($record->status) }}</span>
                            @endif
                        </td>
                        <td style="white-space:nowrap">{{ $record?->waktu_masuk?->format('H:i:s') ?? '—' }}</td>
                        <td style="white-space:nowrap">{{ $record?->waktu_pulang?->format('H:i:s') ?? '—' }}</td>
                        <td style="font-size:11px;color:#667085">
                            @if($record?->metode === 'mesin')
                                <i class="fas fa-sync-alt"></i> mesin
                            @elseif($record?->metode === 'machine')
                                <i class="fas fa-sync-alt"></i> mesin(2)
                            @else
                                {{ $record?->metode ?? '-' }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;padding:16px">Belum ada data presensi mesin untuk hari ini.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </div>
</div>
