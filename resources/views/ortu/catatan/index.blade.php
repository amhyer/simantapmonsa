@extends('layouts.app')

@section('title', 'Catatan Guru - SIMANTAP')

@section('sidebar')
    @include('ortu.partials.sidebar')
@endsection

@section('page_title', 'Catatan Guru')
@section('page_subtitle', 'Catatan perkembangan dari guru')

@section('content')
    @if(!$anak)
        <div class="card">
            <div class="card-body">
                <div class="empty">
                    <div class="icon">👪</div>
                    <b>Belum ada anak terhubung</b>
                    <div>Hubungi guru untuk mendapatkan akses ke data anak Anda.</div>
                </div>
            </div>
        </div>
    @else
        <div class="card" style="margin-bottom:16px">
            <div class="card-body" style="display:flex;gap:18px;align-items:center;flex-wrap:wrap">
                <span class="avatar avatar-lg">{{ substr($anak->nama_peserta_didik, 0, 2) }}</span>
                <div style="flex:1;min-width:190px">
                    <div style="font-size:11.5px;font-weight:800;letter-spacing:.7px;text-transform:uppercase;color:#667085">Catatan guru</div>
                    <div style="font-size:19px;font-weight:800;letter-spacing:-.4px">{{ $anak->nama_peserta_didik }}</div>
                    <div style="font-size:13px;color:#667085">{{ $anak->kelas }} · {{ $catatan->count() }} catatan</div>
                </div>
            </div>
        </div>

        @if(isset($anakList) && $anakList->count() > 1)
            <div style="margin-bottom:16px">
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Pilih Anak</label>
                <select onchange="window.location.href='{{ route('ortu.catatan.index') }}?siswa_id='+this.value" style="padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px;min-width:180px">
                    @foreach($anakList as $a)
                        <option value="{{ $a->id }}" {{ $a->id == $anak->id ? 'selected' : '' }}>{{ $a->nama_peserta_didik }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
            <button class="btn btn-sm filter-btn active" data-filter="semua" onclick="filterCatatan('semua', this)">
                Semua <span class="tag tag-mut" style="margin-left:4px">{{ $catatan->count() }}</span>
            </button>
            @php
                $apresiasi = $catatan->where('jenis', 'apresiasi')->count();
                $perhatian = $catatan->where('jenis', 'perhatian')->count();
                $umum = $catatan->where('jenis', 'umum')->count();
            @endphp
            <button class="btn btn-sm btn-ghost filter-btn" data-filter="apresiasi" onclick="filterCatatan('apresiasi', this)">
                <i class="fas fa-star" style="color:var(--ok)"></i> Apresiasi <span class="tag tag-ok" style="margin-left:4px">{{ $apresiasi }}</span>
            </button>
            <button class="btn btn-sm btn-ghost filter-btn" data-filter="perhatian" onclick="filterCatatan('perhatian', this)">
                <i class="fas fa-exclamation-triangle" style="color:var(--warn)"></i> Perhatian <span class="tag tag-warn" style="margin-left:4px">{{ $perhatian }}</span>
            </button>
            <button class="btn btn-sm btn-ghost filter-btn" data-filter="umum" onclick="filterCatatan('umum', this)">
                <i class="fas fa-info-circle" style="color:var(--navy)"></i> Umum <span class="tag tag-primary" style="margin-left:4px">{{ $umum }}</span>
            </button>
        </div>

        @if($catatan->count())
            <div id="catatanList" style="display:grid;gap:12px">
                @foreach($catatan as $item)
                    @php
                        $jenisInfo = match($item->jenis) {
                            'apresiasi' => ['icon' => '⭐', 'color' => 'var(--ok)', 'bg' => '#E6F5F0', 'border' => '#12805C', 'label' => 'Apresiasi'],
                            'perhatian' => ['icon' => '⚠️', 'color' => 'var(--warn)', 'bg' => '#FEF3E2', 'border' => '#B54708', 'label' => 'Perhatian'],
                            default => ['icon' => '📝', 'color' => 'var(--navy)', 'bg' => '#EEF2F9', 'border' => '#1F3864', 'label' => 'Umum'],
                        };
                    @endphp
                    <div class="card catatan-item" data-jenis="{{ $item->jenis }}">
                        <div class="card-body" style="display:flex;gap:14px;align-items:flex-start">
                            <div style="width:40px;height:40px;border-radius:11px;background:{{ $item->jenis == 'apresiasi' ? '#E6F5F0' : ($item->jenis == 'perhatian' ? '#FEF3E2' : '#EEF2F9') }};display:flex;align-items:center;justify-content:center;font-size:18px;flex:0 0 auto">
                                {{ $jenisInfo['icon'] }}
                            </div>
                            <div style="flex:1;min-width:0">
                                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px">
                                    <div style="display:flex;align-items:center;gap:8px">
                                        <span class="tag" style="background:{{ $jenisInfo['bg'] }};color:{{ $jenisInfo['color'] }}">{{ $jenisInfo['label'] }}</span>
                                        <span style="font-size:12px;color:var(--muted)">{{ \Carbon\Carbon::parse($item->tanggal)->translatedFormat('d M Y') }}</span>
                                    </div>
                                    <span style="font-size:12px;color:var(--muted)">oleh {{ $item->nama_guru }}</span>
                                </div>
                                <p style="font-size:14px;line-height:1.6;margin:0">{{ $item->catatan }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="card">
                <div class="card-body">
                    <div class="empty-state">
                        <div class="icon">🗒️</div>
                        <h4>Belum ada catatan guru</h4>
                        <p>Catatan perkembangan dari guru akan muncul di sini.</p>
                    </div>
                </div>
            </div>
        @endif

        @push('scripts')
        <script>
            function filterCatatan(jenis, btn) {
                document.querySelectorAll('.filter-btn').forEach(b => {
                    b.classList.remove('active');
                    b.classList.add('btn-ghost');
                });
                btn.classList.add('active');
                btn.classList.remove('btn-ghost');

                document.querySelectorAll('.catatan-item').forEach(item => {
                    if (jenis === 'semua' || item.dataset.jenis === jenis) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
            }
        </script>
        <style>
            .filter-btn.active { background: var(--navy) !important; color: #fff !important; border-color: var(--navy) !important; }
            .filter-btn.active .tag { background: rgba(255,255,255,0.2) !important; color: #fff !important; }
        </style>
        @endpush
    @endif
@endsection
