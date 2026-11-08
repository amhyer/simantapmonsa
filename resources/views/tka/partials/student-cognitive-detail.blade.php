@php
    $trenLabel = match ($profile['tren_status'] ?? 'belum_ada_tren') {
        'meningkat' => 'Meningkat (+' . number_format((float) $profile['tren_delta'], 1) . ')',
        'menurun' => 'Menurun (' . number_format((float) $profile['tren_delta'], 1) . ')',
        'stabil' => 'Stabil (' . ($profile['tren_delta'] >= 0 ? '+' : '') . number_format((float) $profile['tren_delta'], 1) . ')',
        'awal' => 'Pengujian Pertama',
        default => 'Belum Ada Data Tren',
    };
    $trenClass = match ($profile['tren_status'] ?? '') {
        'meningkat' => 'tag-ok',
        'menurun' => 'tag-bad',
        'stabil', 'awal' => 'tag-primary',
        default => 'tag-secondary',
    };
@endphp

{{-- Kartu Ringkasan Kognitif Siswa --}}
<div class="grid grid-4" style="margin-bottom:20px">
    <div class="stat-card primary">
        <i class="fas fa-brain stat-icon"></i>
        <div class="stat-label">Skor Kognitif TKA</div>
        <div class="stat-value">{{ number_format((float) $profile['skor_kognitif'], 1) }}</div>
        <div class="stat-change">{{ $profile['jumlah_pengujian'] }} kali rekam uji</div>
    </div>
    <div class="stat-card {{ $profile['zona_kemampuan'] === 'di_atas_rata_rata' ? 'ok' : ($profile['zona_kemampuan'] === 'di_bawah_rata_rata' ? 'bad' : 'gold') }}">
        <i class="fas fa-compass stat-icon"></i>
        <div class="stat-label">Zona Kemampuan</div>
        <div class="stat-value" style="font-size:18px">{{ $profile['zona_label'] }}</div>
        <div class="stat-change">Rata-rata kelompok: {{ number_format((float) $profile['rata_rata_pembanding'], 1) }} ({{ $profile['selisih_rata_rata'] >= 0 ? '+' : '' }}{{ number_format((float) $profile['selisih_rata_rata'], 1) }})</div>
    </div>
    <div class="stat-card {{ ($profile['tren_status'] ?? '') === 'meningkat' ? 'ok' : (($profile['tren_status'] ?? '') === 'menurun' ? 'bad' : 'primary') }}">
        <i class="fas fa-chart-line stat-icon"></i>
        <div class="stat-label">Tren Antar Waktu</div>
        <div class="stat-value" style="font-size:17px">{{ $trenLabel }}</div>
        <div class="stat-change">Uji diagnostik & simulasi adaptif</div>
    </div>
    <div class="stat-card gold">
        <i class="fas fa-laptop-code stat-icon"></i>
        <div class="stat-label">Simulasi & Diagnostik</div>
        <div class="stat-value">{{ $profile['jumlah_simulasi'] }} / {{ $profile['jumlah_asesmen_guru'] }}</div>
        <div class="stat-change">Simulasi selesai / Asesmen guru</div>
    </div>
</div>

{{-- Rekap Per Indikator Kognitif & Tren Perkembangan --}}
<div class="grid grid-2" style="margin-bottom:20px">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-sliders-h" style="color:var(--navy);margin-right:8px"></i>Capaian per Indikator Kognitif</h3>
            <span class="tag {{ $profile['zona_badge'] }}">{{ $profile['zona_label'] }}</span>
        </div>
        <div class="card-body">
            <div style="display:flex;flex-direction:column;gap:14px">
                @foreach($profile['indikator_scores'] as $namaInd => $indData)
                    @php
                        $skorInd = (float) ($indData['skor'] ?? 0);
                        $fillCls = !$indData['terukur'] ? '' : ($skorInd >= 80 ? 'fill-ok' : ($skorInd >= 70 ? '' : 'fill-bad'));
                        $badgeCls = !$indData['terukur'] ? 'tag-secondary' : ($skorInd >= 80 ? 'tag-ok' : ($skorInd >= 70 ? 'tag-primary' : 'tag-bad'));
                    @endphp
                    <div>
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;gap:8px">
                            <span style="font-weight:700;font-size:13.5px;color:var(--text)">{{ $namaInd }}</span>
                            <div style="display:flex;align-items:center;gap:8px">
                                <span class="tag {{ $badgeCls }}">{{ $indData['kategori_capaian'] }}</span>
                                <span style="font-weight:800;font-size:13.5px;min-width:44px;text-align:right">
                                    {{ $indData['terukur'] ? number_format($skorInd, 1) : '—' }}
                                </span>
                            </div>
                        </div>
                        <div class="bar">
                            <span class="fill {{ $fillCls }}" style="width:{{ $indData['terukur'] ? min(100, max(4, round($skorInd))) : 0 }}%"></span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history" style="color:var(--navy);margin-right:8px"></i>Tren Perkembangan Antar Waktu (Uji → Uji Berikutnya)</h3>
            <span class="tag {{ $trenClass }}">{{ $trenLabel }}</span>
        </div>
        <div class="card-body" style="padding:0">
            @if(empty($profile['timeline']))
                <div style="padding:28px 20px;text-align:center;color:var(--muted);font-size:13px">
                    Belum ada riwayat penilaian diagnostik maupun simulasi TKA yang diselesaikan.
                </div>
            @else
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tahap</th>
                                <th>Instrumen Uji</th>
                                <th style="text-align:center">Skor</th>
                                <th style="text-align:center">Perubahan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($profile['timeline'] as $tItem)
                                <tr>
                                    <td style="font-weight:800;color:var(--navy)">
                                        {{ $tItem['urutan_uji'] }}
                                        <div style="font-size:11px;color:var(--muted);font-weight:500">{{ $tItem['tanggal'] }}</div>
                                    </td>
                                    <td>
                                        <div style="font-weight:700;font-size:13px">{{ $tItem['judul'] }}</div>
                                        <small style="color:var(--muted)">{{ $tItem['tipe'] }} • {{ $tItem['badge'] }}</small>
                                    </td>
                                    <td style="text-align:center;font-weight:800">{{ number_format((float) $tItem['skor'], 1) }}</td>
                                    <td style="text-align:center">
                                        @if($tItem['perubahan'] === null)
                                            <span class="tag tag-secondary">Awal</span>
                                        @elseif($tItem['perubahan'] > 0)
                                            <span class="tag tag-ok">+{{ number_format((float) $tItem['perubahan'], 1) }}</span>
                                        @elseif($tItem['perubahan'] < 0)
                                            <span class="tag tag-bad">{{ number_format((float) $tItem['perubahan'], 1) }}</span>
                                        @else
                                            <span class="tag tag-primary">0.0</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Paket Rekomendasi Personalisasi Pembelajaran --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <div>
            <h3><i class="fas fa-lightbulb" style="color:#D97706;margin-right:8px"></i>Rekomendasi Personalisasi Pembelajaran & Tindak Lanjut</h3>
            <div style="font-size:12px;color:var(--muted);margin-top:2px">
                Disusun otomatis berdasarkan indikator lemah (&lt; 70) untuk penguatan/remedial dan indikator kuat (&ge; 80) untuk pengayaan.
            </div>
        </div>
        @if($rekomendasi)
            <span class="tag {{ $rekomendasi->status_tindak_lanjut === 'tuntas' ? 'tag-ok' : ($rekomendasi->status_tindak_lanjut === 'berjalan' ? 'tag-primary' : 'tag-gold') }}">
                Status: {{ ucfirst($rekomendasi->status_tindak_lanjut) }}
            </span>
        @endif
    </div>
    <div class="card-body">
        @if(!$rekomendasi || empty($rekomendasi->paket_tindak_lanjut))
            <div style="color:var(--muted);font-size:13.5px">
                Paket rekomendasi personalisasi belum digenerate. Paket akan otomatis tersusun begitu siswa menyelesaikan simulasi adaptif atau ketika guru menekan tombol penyusunan otomatis.
            </div>
        @else
            <div class="grid grid-2" style="gap:16px">
                @foreach($rekomendasi->paket_tindak_lanjut as $pkt)
                    @php
                        $isRemedial = ($pkt['tipe'] ?? '') === 'remedial';
                    @endphp
                    <div style="border:1.5px solid {{ $isRemedial ? '#FDE68A' : '#BBF7D0' }};background:{{ $isRemedial ? '#FFFBEB' : '#F0FDF4' }};border-radius:12px;padding:16px">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px">
                            <span class="tag {{ $isRemedial ? 'tag-gold' : 'tag-ok' }}">
                                {{ $isRemedial ? 'Paket Penguatan / Remedial' : (($pkt['tipe'] ?? '') === 'pengayaan' ? 'Paket Pengayaan HOTS' : 'Pemetaan Awal') }}
                            </span>
                            <span style="font-size:12px;font-weight:700;color:var(--muted)">
                                Indikator: {{ $pkt['indikator'] ?? '-' }} ({{ number_format((float) ($pkt['skor_saat_ini'] ?? 0), 1) }})
                            </span>
                        </div>
                        <h4 style="margin:0 0 10px;font-size:14.5px;font-weight:800;color:var(--navy)">
                            {{ $pkt['judul_paket'] ?? 'Paket Latihan Terarah' }}
                        </h4>
                        <ul style="margin:0 0 12px;padding-left:18px;font-size:13px;line-height:1.55;color:var(--text)">
                            @foreach(($pkt['langkah_kegiatan'] ?? []) as $langkah)
                                <li>{{ $langkah }}</li>
                            @endforeach
                        </ul>
                        @if(!empty($pkt['tips_orang_tua']))
                            <div style="background:#fff;border-radius:8px;padding:10px 12px;font-size:12px;color:var(--muted);border:1px solid rgba(0,0,0,0.06)">
                                <strong style="color:var(--navy)"><i class="fas fa-home"></i> Dukungan di Rumah:</strong>
                                {{ $pkt['tips_orang_tua'] }}
                            </div>
                        @endif

                        @if(!empty($showStudentLaunchButton) && !empty($pkt['paket_simulasi']))
                            <form method="POST" action="{{ route('siswa.tka-simulasi.adaptif.start', $pkt['paket_simulasi']) }}" style="margin-top:12px">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-play"></i> Mulai Latihan Adaptif Subtes Ini
                                </button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>

            @if(!empty($rekomendasi->catatan_guru))
                <div style="margin-top:16px;padding:12px 14px;background:#F8FAFC;border-left:4px solid var(--navy);border-radius:6px;font-size:13px">
                    <strong>Catatan Guru Pembimbing:</strong> {{ $rekomendasi->catatan_guru }}
                </div>
            @endif
        @endif
    </div>
</div>
