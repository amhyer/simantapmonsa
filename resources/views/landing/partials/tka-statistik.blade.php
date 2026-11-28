<section id="tka-statistik" data-aos="fade-up">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <div class="section-label"><i class="fas fa-chart-bar"></i> Statistik Nasional</div>
            <h2 class="section-title">Statistik <span class="highlight">TKA</span> Nasional</h2>
            <p class="section-subtitle">Data Tes Kemampuan Akademik — Tahun 2026 dari tka.kemendikdasmen.go.id</p>
            @if($lastUpdate)
                <div class="tka-source-badge"><i class="fas fa-clock"></i> Pembaruan Terakhir: {{ $lastUpdate }}</div>
            @endif
            @if(!isset($tkaData['error']))
                <div style="margin-top:12px;padding:10px 20px;background:rgba(184,134,11,0.08);border-radius:10px;display:inline-block;font-size:13px;color:var(--text-muted)">
                    <i class="fas fa-info-circle" style="color:var(--gold)"></i> Data TKA mencakup: SMA/MA, SMK, SLB, PKBM/SKB, dan Ponpes.
                </div>
            @endif
        </div>

        @if(isset($tkaData['error']))
            <div class="text-center py-4">
                <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                <p class="text-muted">Statistik TKA nasional dapat dipantau melalui halaman statistik lengkap. <a href="{{ route('tka.statistik') }}" class="font-weight-bold">Lihat halaman lengkap →</a></p>
            </div>
        @else
            {{-- Filter Bar --}}
            <div class="tka-filter-bar" data-aos="fade-up">
                <div>
                    <label for="tkaFilterProp">Provinsi</label><br>
                    <select id="tkaFilterProp" onchange="tkaFilterChanged()">
                        <option value="all">Semua Provinsi (Nasional)</option>
                        @foreach($provinsiList as $p)
                            <option value="{{ $p['key'] }}">{{ $p['value'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="tkaFilterJenjang">Jenjang</label><br>
                    <select id="tkaFilterJenjang" onchange="tkaFilterChanged()">
                        <option value="all">Semua Jenjang</option>
                        <option value="SD/MI">SD/MI</option>
                        <option value="SMP/MTs">SMP/MTs</option>
                        <option value="SMA/MA">SMA/MA</option>
                        <option value="SMK">SMK</option>
                        <option value="Kesetaraan">Kesetaraan</option>
                        <option value="Sekolah Khusus">Sekolah Khusus</option>
                    </select>
                </div>
                <button class="tka-filter-reset" onclick="tkaResetFilter()"><i class="fas fa-undo"></i> Reset</button>
            </div>

            {{-- Charts --}}
            <div class="row g-3 mb-4" data-aos="fade-up" data-aos-delay="100">
                <div class="col-md-6">
                    <div class="tka-card">
                        <div class="tka-card-header"><i class="fas fa-chart-line"></i> <h6>Grafik Calon Peserta Harian</h6></div>
                        <div style="padding:16px"><canvas id="tka-chart-capes-harian" height="200"></canvas></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="tka-card">
                        <div class="tka-card-header"><i class="fas fa-chart-area"></i> <h6>Grafik Kumulatif Pendaftar</h6></div>
                        <div style="padding:16px"><canvas id="tka-chart-kumulatif" height="200"></canvas></div>
                    </div>
                </div>
            </div>

            {{-- Stat Cards --}}
            <div class="row g-3 mb-4" data-aos="fade-up" data-aos-delay="150">
                <div class="col-6 col-lg-3">
                    <div class="tka-stat-card">
                        <div class="tka-stat-icon" style="background:var(--navy)"><i class="fas fa-building"></i></div>
                        <div class="tka-stat-value" data-count="{{ $fmtNum($total['jm_sek_daftar'] ?? 0) }}">0</div>
                        <div class="tka-stat-label">Satuan Pendidikan</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="tka-stat-card">
                        <div class="tka-stat-icon" style="background:#3B82F6"><i class="fas fa-users"></i></div>
                        <div class="tka-stat-value" data-count="{{ $fmtNum($total['jm_pes_t'] ?? 0) }}">0</div>
                        <div class="tka-stat-label">Calon Peserta</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="tka-stat-card">
                        <div class="tka-stat-icon" style="background:#10B981"><i class="fas fa-user-check"></i></div>
                        <div class="tka-stat-value" data-count="{{ $fmtNum($total['jm_daftar_t'] ?? 0) }}">0</div>
                        <div class="tka-stat-label">Pendaftar Lengkap</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="tka-stat-card">
                        <div class="tka-stat-icon" style="background:var(--gold)"><i class="fas fa-user-clock"></i></div>
                        <div class="tka-stat-value" data-count="{{ $fmtNum($total['jm_tidak'] ?? 0) }}">0</div>
                        <div class="tka-stat-label">Belum Lengkap</div>
                    </div>
                </div>
            </div>

            {{-- Rekap Pendaftaran --}}
            @if(count($rekap) > 0)
            <div class="tka-card mb-4" data-aos="fade-up" data-aos-delay="200">
                <div class="tka-tabs">
                    <button class="tka-tab active" onclick="tkaTabClick(this,'tka-tab-peserta')">Calon Peserta</button>
                    <button class="tka-tab" onclick="tkaTabClick(this,'tka-tab-daftar')">Pendaftar</button>
                </div>
                {{-- Tab: Calon Peserta --}}
                <div id="tka-tab-peserta" class="tka-tab-content">
                    <div class="table-responsive">
                        <table class="table tka-tbl mb-0">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="text-center" style="vertical-align:middle">Jenjang</th>
                                    <th colspan="3" class="text-center" style="border-left:2px solid rgba(255,255,255,0.2)">Satuan Pendidikan</th>
                                    <th colspan="4" class="text-center" style="border-left:2px solid rgba(255,255,255,0.2)">Calon Peserta</th>
                                </tr>
                                <tr>
                                    <th style="border-left:2px solid rgba(255,255,255,0.2)">Jumlah</th>
                                    <th>Daftar</th>
                                    <th>%</th>
                                    <th style="border-left:2px solid rgba(255,255,255,0.2)">L</th>
                                    <th>P</th>
                                    <th>Jumlah</th>
                                    <th>%</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $totSek = 0; $totDaftar = 0; $totPL = 0; $totPP = 0; $totPT = 0; @endphp
                                @foreach($rekap as $r)
                                    @php
                                        $sek = $fmtNum($r['jm_sek'] ?? 0);
                                        $daf = $fmtNum($r['jm_sek_daftar'] ?? 0);
                                        $pl = $fmtNum($r['jm_pes_l'] ?? 0);
                                        $pp = $fmtNum($r['jm_pes_p'] ?? 0);
                                        $pt = $fmtNum($r['jm_pes_t'] ?? 0);
                                        $totSek += $sek; $totDaftar += $daf; $totPL += $pl; $totPP += $pp; $totPT += $pt;
                                        $pctSek = $sek > 0 ? round($daf / $sek * 100, 1) : 0;
                                    @endphp
                                    <tr>
                                        <td>{{ $r['nm_jenjang'] ?? '-' }}</td>
                                        <td>{{ number_format($sek) }}</td>
                                        <td>{{ number_format($daf) }}</td>
                                        <td>{{ $pctSek }}%</td>
                                        <td>{{ number_format($pl) }}</td>
                                        <td>{{ number_format($pp) }}</td>
                                        <td>{{ number_format($pt) }}</td>
                                        <td>{{ $pt > 0 ? round($daf / $pt * 100, 1) : 0 }}%</td>
                                    </tr>
                                @endforeach
                                <tr class="tka-total-row">
                                    <td>TOTAL</td>
                                    <td>{{ number_format($totSek) }}</td>
                                    <td>{{ number_format($totDaftar) }}</td>
                                    <td>{{ $totSek > 0 ? round($totDaftar / $totSek * 100, 1) : 0 }}%</td>
                                    <td>{{ number_format($totPL) }}</td>
                                    <td>{{ number_format($totPP) }}</td>
                                    <td>{{ number_format($totPT) }}</td>
                                    <td>{{ $totPT > 0 ? round($totDaftar / $totPT * 100, 1) : 0 }}%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                {{-- Tab: Pendaftar --}}
                <div id="tka-tab-daftar" class="tka-tab-content" style="display:none">
                    <div class="table-responsive">
                        <table class="table tka-tbl mb-0">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="text-center" style="vertical-align:middle">Jenjang</th>
                                    <th colspan="3" class="text-center" style="border-left:2px solid rgba(255,255,255,0.2)">Pendaftar Lengkap</th>
                                    <th colspan="2" class="text-center" style="border-left:2px solid rgba(255,255,255,0.2)">Moda Pelaksanaan</th>
                                </tr>
                                <tr>
                                    <th style="border-left:2px solid rgba(255,255,255,0.2)">L</th>
                                    <th>P</th>
                                    <th>Jumlah</th>
                                    <th style="border-left:2px solid rgba(255,255,255,0.2)">Mandiri</th>
                                    <th>Menumpang</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $totDL = 0; $totDP = 0; $totDT = 0; $totMandiri = 0; $totMenumpang = 0;
                                    $jenjangMap = collect($jenjangRekap)->keyBy('nm_jenjang');
                                @endphp
                                @foreach($rekap as $r)
                                    @php
                                        $dl = $fmtNum($r['jm_daftar_l'] ?? 0);
                                        $dp = $fmtNum($r['jm_daftar_p'] ?? 0);
                                        $dt = $fmtNum($r['jm_daftar_t'] ?? 0);
                                        $j = $jenjangMap->get($r['nm_jenjang'], []);
                                        $mandiri = $fmtNum($j['jm_mandiri'] ?? 0);
                                        $menumpang = $fmtNum($j['jm_menumpang'] ?? 0);
                                        $totDL += $dl; $totDP += $dp; $totDT += $dt; $totMandiri += $mandiri; $totMenumpang += $menumpang;
                                    @endphp
                                    <tr>
                                        <td>{{ $r['nm_jenjang'] ?? '-' }}</td>
                                        <td>{{ number_format($dl) }}</td>
                                        <td>{{ number_format($dp) }}</td>
                                        <td>{{ number_format($dt) }}</td>
                                        <td>{{ number_format($mandiri) }}</td>
                                        <td>{{ number_format($menumpang) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="tka-total-row">
                                    <td>TOTAL</td>
                                    <td>{{ number_format($totDL) }}</td>
                                    <td>{{ number_format($totDP) }}</td>
                                    <td>{{ number_format($totDT) }}</td>
                                    <td>{{ number_format($totMandiri) }}</td>
                                    <td>{{ number_format($totMenumpang) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- Status & Moda Pelaksanaan --}}
            <div class="row g-3 mb-4" data-aos="fade-up" data-aos-delay="250">
                {{-- Pie Charts --}}
                <div class="col-lg-6">
                    <div class="tka-card" style="height:100%">
                        <div class="tka-card-header"><i class="fas fa-chart-pie"></i> <h6>Status Pelaksanaan</h6></div>
                        <div style="padding:20px">
                            <div class="tka-pie-grid">
                                @foreach($pieStatus as $ps)
                                    @if(($ps['data'][0] ?? 0) > 0)
                                    <div class="tka-pie-card">
                                        <div class="tka-pie-title">{{ $ps['title'] }}</div>
                                        <canvas class="tka-pie-canvas" data-values="{{ json_encode($ps['data']) }}" height="130"></canvas>
                                        <div style="display:flex;justify-content:center;gap:12px;margin-top:8px;font-size:11px;color:var(--text-muted)">
                                            <span><span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:#10B981;margin-right:3px"></span>Mandiri</span>
                                            <span><span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:#F59E0B;margin-right:3px"></span>Menumpang</span>
                                            <span><span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:var(--bad);margin-right:3px"></span>Blm Ditetapkan</span>
                                        </div>
                                    </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                {{-- Moda Pelaksanaan --}}
                <div class="col-lg-6">
                    <div class="tka-card" style="height:100%">
                        <div class="tka-card-header"><i class="fas fa-wifi"></i> <h6>Moda Pelaksanaan</h6></div>
                        <div style="padding:20px">
                            <div class="tka-pie-grid">
                                @foreach($pieModa as $pm)
                                    @if(($pm['data'][0] ?? 0) > 0)
                                    <div class="tka-pie-card">
                                        <div class="tka-pie-title">{{ $pm['title'] }}</div>
                                        <canvas class="tka-moda-canvas" data-values="{{ json_encode($pm['data']) }}" height="130"></canvas>
                                        <div style="display:flex;justify-content:center;gap:12px;margin-top:8px;font-size:11px;color:var(--text-muted)">
                                            <span><span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:#3B82F6;margin-right:3px"></span>Online</span>
                                            <span><span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:#8B5CF6;margin-right:3px"></span>Semi Online</span>
                                            <span><span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:var(--bad);margin-right:3px"></span>Blm Ditentukan</span>
                                        </div>
                                    </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Rekap Provinsi --}}
            @if(count($wilayahRekap) > 0)
            <div class="tka-card mb-4" data-aos="fade-up" data-aos-delay="300">
                <div class="tka-card-header"><i class="fas fa-map-marked-alt"></i> <h6>Rekap per Provinsi</h6></div>
                <div class="table-responsive">
                    <table class="table tka-tbl mb-0">
                        <thead>
                            <tr>
                                <th class="text-center">No</th>
                                <th class="text-center" style="text-align:left !important">Provinsi</th>
                                <th class="text-center">Sekolah</th>
                                <th class="text-center">Daftar</th>
                                <th class="text-center">Calon Peserta</th>
                                <th class="text-center">Pendaftar</th>
                                <th class="text-center">% Daftar</th>
                                <th class="text-center">Mandiri</th>
                                <th class="text-center">Online</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($wilayahRekap as $i => $w)
                                @php
                                    $sek = $fmtNum($w['jm_sekolah'] ?? 0);
                                    $daf = $fmtNum($w['jm_sek_daftar'] ?? 0);
                                    $pes = $fmtNum($w['jm_pes_t'] ?? 0);
                                    $daftar = $fmtNum($w['jm_daftar_t'] ?? 0);
                                    $mandiri = $fmtNum($w['jm_mandiri'] ?? 0);
                                    $online = $fmtNum($w['jm_online'] ?? 0);
                                    $pct = $sek > 0 ? round($daf / $sek * 100, 1) : 0;
                                @endphp
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td style="text-align:left !important;font-weight:600;color:var(--navy)">{{ $w['nm_prop'] ?? '-' }}</td>
                                    <td>{{ number_format($sek) }}</td>
                                    <td>{{ number_format($daf) }}</td>
                                    <td>{{ number_format($pes) }}</td>
                                    <td>{{ number_format($daftar) }}</td>
                                    <td>{{ $pct }}%</td>
                                    <td>{{ number_format($mandiri) }}</td>
                                    <td>{{ number_format($online) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- Actions --}}
            <div class="text-center mt-4" data-aos="fade-up" data-aos-delay="350">
                <a href="{{ route('tka.statistik') }}" class="btn-hero btn-hero-secondary">
                    <i class="fas fa-chart-line"></i> Lihat Statistik Lengkap
                </a>
                <a href="https://tka.kemendikdasmen.go.id/hasiltka/" target="_blank" class="btn-hero btn-hero-primary" style="margin-left:8px">
                    <i class="fas fa-search"></i> Cek Hasil TKA Sekolah
                </a>
                <a href="https://tka.kemendikdasmen.go.id" target="_blank" class="btn-hero btn-hero-secondary" style="margin-left:8px">
                    <i class="fas fa-external-link-alt"></i> Situs Resmi TKA
                </a>
            </div>
        @endif
    </div>
</section>
