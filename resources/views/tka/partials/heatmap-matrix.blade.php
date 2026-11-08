@php
    $heatStyles = [
        'unggul' => ['bg' => '#DCFCE7', 'border' => '#86EFAC', 'text' => '#166534', 'label' => 'Unggul'],
        'memadai' => ['bg' => '#E0F2FE', 'border' => '#7DD3FC', 'text' => '#075985', 'label' => 'Memadai'],
        'waspada' => ['bg' => '#FEF3C7', 'border' => '#FCD34D', 'text' => '#92400E', 'label' => 'Waspada'],
        'kritis' => ['bg' => '#FEE2E2', 'border' => '#FCA5A5', 'text' => '#991B1B', 'label' => 'Kritis'],
        'kosong' => ['bg' => '#F8FAFC', 'border' => '#E2E8F0', 'text' => '#64748B', 'label' => 'Belum Diuji'],
    ];
@endphp

{{-- Ringkasan Statistik Kognitif --}}
<div class="grid grid-4" style="margin-bottom:20px">
    <div class="stat-card primary">
        <i class="fas fa-brain stat-icon"></i>
        <div class="stat-label">Rata-rata Kognitif</div>
        <div class="stat-value">{{ number_format((float) $heatmap['rata_rata_sekolah'], 1) }}</div>
        <div class="stat-change">Target kesiapan: {{ number_format((float) $heatmap['target_skor'], 0) }} ({{ $heatmap['total_siswa'] }} siswa)</div>
    </div>
    <div class="stat-card ok">
        <i class="fas fa-arrow-trend-up stat-icon"></i>
        <div class="stat-label">Di Atas Rata-rata</div>
        <div class="stat-value">{{ $heatmap['zona_sekolah']['di_atas_rata_rata'] ?? 0 }}</div>
        <div class="stat-change up">Siswa melampaui rata-rata kelompok</div>
    </div>
    <div class="stat-card gold">
        <i class="fas fa-equals stat-icon"></i>
        <div class="stat-label">Zona Rata-rata</div>
        <div class="stat-value">{{ $heatmap['zona_sekolah']['rata_rata'] ?? 0 }}</div>
        <div class="stat-change">Siswa berada pada rentang stabil</div>
    </div>
    <div class="stat-card bad">
        <i class="fas fa-triangle-exclamation stat-icon"></i>
        <div class="stat-label">Di Bawah Rata-rata</div>
        <div class="stat-value">{{ $heatmap['zona_sekolah']['di_bawah_rata_rata'] ?? 0 }}</div>
        <div class="stat-change down">Prioritas penguatan kognitif</div>
    </div>
</div>

{{-- Filter Jenjang & Target Skor --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-body" style="padding:16px 20px">
        <form method="GET" action="{{ route($filterRoute) }}" style="display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:14px">
            <div style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:12px">
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:4px">Filter Tingkat / Jenjang</label>
                    <select name="jenjang" class="form-control" style="min-width:170px">
                        <option value="">Semua Jenjang Kelas</option>
                        @foreach($heatmap['jenjang_options'] as $j)
                            <option value="{{ $j }}" @selected(($heatmap['jenjang_filter'] ?? '') === $j)>{{ $j }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:4px">Target Skor Kesiapan</label>
                    <input type="number" name="target" min="40" max="95" step="1" value="{{ (int) $heatmap['target_skor'] }}" class="form-control" style="width:120px">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-filter"></i> Terapkan
                </button>
                @if(!empty($heatmap['jenjang_filter']) || (int) $heatmap['target_skor'] !== 70)
                    <a href="{{ route($filterRoute) }}" class="btn btn-secondary btn-sm">Reset</a>
                @endif
            </div>

            @if(!empty($exportRoute))
                <a href="{{ route($exportRoute, ['jenjang' => $heatmap['jenjang_filter'], 'target' => $heatmap['target_skor']]) }}" class="btn btn-outline btn-sm">
                    <i class="fas fa-file-csv"></i> Unduh CSV Heatmap
                </a>
            @endif
        </form>
    </div>
</div>

{{-- Heatmap Kesenjangan Indikator per Kelas --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <div>
            <h3><i class="fas fa-th" style="color:var(--navy);margin-right:8px"></i>Heatmap Distribusi Indikator TKA & Kesenjangan per Kelas</h3>
            <div style="font-size:12px;color:var(--muted);margin-top:2px">
                Angka utama menunjukkan rata-rata indikator (0–100); angka dalam tanda kurung menunjukkan selisih terhadap target ({{ number_format((float) $heatmap['target_skor'], 0) }}).
            </div>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;font-size:11.5px;font-weight:700">
            @foreach(['unggul', 'memadai', 'waspada', 'kritis'] as $lvl)
                <span style="padding:4px 9px;border-radius:6px;background:{{ $heatStyles[$lvl]['bg'] }};color:{{ $heatStyles[$lvl]['text'] }};border:1px solid {{ $heatStyles[$lvl]['border'] }}">
                    {{ $heatStyles[$lvl]['label'] }}
                </span>
            @endforeach
        </div>
    </div>
    <div class="card-body" style="padding:0">
        @if(empty($heatmap['kelas_rows']))
            <div class="empty-state" style="padding:36px 20px;text-align:center;color:var(--muted)">
                Belum ada data siswa aktif pada filter kelas/jenjang yang dipilih.
            </div>
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Kelas</th>
                            <th>Tingkat</th>
                            <th style="text-align:center">Cakupan Uji</th>
                            <th style="text-align:center">Rata-rata Kelas</th>
                            @foreach($heatmap['indikator_columns'] as $ind)
                                <th style="text-align:center;min-width:135px">{{ $ind }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($heatmap['kelas_rows'] as $row)
                            <tr>
                                <td style="font-weight:800;color:var(--navy)">{{ $row['kelas'] }}</td>
                                <td><span class="tag tag-secondary">{{ $row['tingkat'] }}</span></td>
                                <td style="text-align:center;font-size:12.5px">
                                    <strong>{{ $row['siswa_terukur'] }}</strong> / {{ $row['jumlah_siswa'] }} siswa
                                </td>
                                <td style="text-align:center">
                                    @if($row['rata_skor'] !== null)
                                        <div style="font-weight:800;font-size:14px">{{ number_format($row['rata_skor'], 1) }}</div>
                                        <small style="color:{{ $row['kesenjangan_umum'] >= 0 ? '#166534' : '#991B1B' }};font-weight:700">
                                            {{ $row['kesenjangan_umum'] >= 0 ? '+' : '' }}{{ number_format($row['kesenjangan_umum'], 1) }}
                                        </small>
                                    @else
                                        <span style="color:var(--muted)">—</span>
                                    @endif
                                </td>
                                @foreach($heatmap['indikator_columns'] as $ind)
                                    @php
                                        $cell = $row['cells'][$ind] ?? ['terukur' => false, 'level_heatmap' => 'kosong'];
                                        $st = $heatStyles[$cell['level_heatmap']] ?? $heatStyles['kosong'];
                                    @endphp
                                    <td style="text-align:center;padding:8px">
                                        @if(!empty($cell['terukur']))
                                            <div style="background:{{ $st['bg'] }};border:1px solid {{ $st['border'] }};color:{{ $st['text'] }};border-radius:8px;padding:7px 8px">
                                                <div style="font-weight:800;font-size:13.5px">{{ number_format((float) $cell['rata_skor'], 1) }}</div>
                                                <div style="font-size:11px;font-weight:700">
                                                    ({{ $cell['kesenjangan'] >= 0 ? '+' : '' }}{{ number_format((float) $cell['kesenjangan'], 1) }})
                                                </div>
                                            </div>
                                        @else
                                            <div style="background:{{ $st['bg'] }};border:1px dashed {{ $st['border'] }};color:{{ $st['text'] }};border-radius:8px;padding:10px 6px;font-size:11.5px">
                                                Belum diuji
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- Rekap per Jenjang & Prioritas Kesenjangan --}}
<div class="grid grid-2">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-layer-group" style="color:var(--navy);margin-right:8px"></i>Rekap Capaian per Tingkat / Jenjang</h3>
        </div>
        <div class="card-body" style="padding:0">
            @if(empty($heatmap['jenjang_summary']))
                <div style="padding:20px;color:var(--muted);font-size:13px">Belum ada rekap tingkat.</div>
            @else
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tingkat</th>
                                <th style="text-align:center">Rombel</th>
                                <th style="text-align:center">Siswa Terukur</th>
                                <th style="text-align:center">Rata-rata</th>
                                <th style="text-align:center">Selisih Target</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($heatmap['jenjang_summary'] as $jRow)
                                <tr>
                                    <td style="font-weight:700">{{ $jRow['tingkat'] }}</td>
                                    <td style="text-align:center">{{ $jRow['jumlah_kelas'] }}</td>
                                    <td style="text-align:center">{{ $jRow['siswa_terukur'] }} / {{ $jRow['jumlah_siswa'] }}</td>
                                    <td style="text-align:center;font-weight:700">{{ $jRow['rata_skor'] !== null ? number_format($jRow['rata_skor'], 1) : '—' }}</td>
                                    <td style="text-align:center">
                                        @if($jRow['kesenjangan'] !== null)
                                            <span class="tag {{ $jRow['kesenjangan'] >= 0 ? 'tag-ok' : 'tag-bad' }}">
                                                {{ $jRow['kesenjangan'] >= 0 ? '+' : '' }}{{ number_format($jRow['kesenjangan'], 1) }}
                                            </span>
                                        @else
                                            —
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

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-bullseye" style="color:#B91C1C;margin-right:8px"></i>Prioritas Penutupan Kesenjangan Indikator</h3>
        </div>
        <div class="card-body" style="padding:0">
            @if(empty($heatmap['priority_gaps']))
                <div style="padding:22px;color:#166534;font-size:13px;font-weight:600">
                    <i class="fas fa-check-circle"></i> Seluruh indikator yang telah terukur sudah memenuhi atau melampaui target skor kesiapan ({{ number_format((float) $heatmap['target_skor'], 0) }}).
                </div>
            @else
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Kelas</th>
                                <th>Indikator Kognitif</th>
                                <th style="text-align:center">Skor</th>
                                <th style="text-align:center">Kesenjangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($heatmap['priority_gaps'] as $gap)
                                <tr>
                                    <td style="font-weight:700">{{ $gap['kelas'] }}</td>
                                    <td>{{ $gap['indikator'] }}</td>
                                    <td style="text-align:center;font-weight:700">{{ number_format($gap['rata_skor'], 1) }}</td>
                                    <td style="text-align:center">
                                        <span class="tag {{ $gap['level_heatmap'] === 'kritis' ? 'tag-bad' : 'tag-gold' }}">
                                            {{ number_format($gap['kesenjangan'], 1) }} poin
                                        </span>
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
