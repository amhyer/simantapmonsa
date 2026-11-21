@php
    $pmCtxService = app(\App\Services\PembelajaranMendalam\PembelajaranContextService::class);
    $pmUser = auth()->user();
    $pmTenantId = $pmCtxService->tenantId($pmUser);
    $pmCtx = $pmCtxService->getContext(request(), $pmUser);
    $pmScope = ($pmUser && $pmUser->peran === 'guru')
        ? app(\App\Services\PembelajaranMendalam\GuruScopeService::class)->resolveForGuru($pmUser, $pmTenantId)
        : ['is_scoped' => false, 'tingkat_list' => [1, 2, 3, 4, 5, 6], 'kelas_list' => []];
    $pmAllowedTingkat = $pmScope['tingkat_list'] ?? [1, 2, 3, 4, 5, 6];
    $pmSupervisi = $pmUser?->peran === 'guru'
        ? $pmCtxService->supervisiAktifGuru($pmTenantId, $pmUser->id, $pmCtx['tahun_ajaran'], $pmCtx['semester'])
        : null;

    $pmTabs = [
        ['route' => 'guru.pm.alokasi.index', 'label' => 'Alokasi', 'icon' => 'fa-clock'],
        ['route' => 'guru.pm.cp.index', 'label' => 'CP', 'icon' => 'fa-award'],
        ['route' => 'guru.pm.tp.index', 'label' => 'TP', 'icon' => 'fa-bullseye'],
        ['route' => 'guru.pm.atp.index', 'label' => 'ATP', 'icon' => 'fa-project-diagram'],
        ['route' => 'guru.pm.buku-paket.index', 'label' => 'SIBI', 'icon' => 'fa-book'],
        ['route' => 'guru.pm.kktp.index', 'label' => 'KKTP', 'icon' => 'fa-sliders-h'],
        ['route' => 'guru.pm.kalender.index', 'label' => 'Kalender', 'icon' => 'fa-calendar-alt'],
        ['route' => 'guru.pm.prota.index', 'label' => 'Prota', 'icon' => 'fa-list-ol'],
        ['route' => 'guru.pm.prosem.index', 'label' => 'Prosem', 'icon' => 'fa-th'],
        ['route' => 'guru.perangkat.index', 'label' => 'RPM', 'icon' => 'fa-folder-open'],
        ['route' => 'guru.perangkat.jurnal.index', 'label' => 'Jurnal', 'icon' => 'fa-book-reader'],
        ['route' => 'guru.pm.generate.index', 'label' => 'Generate', 'icon' => 'fa-bolt'],
        ['route' => 'guru.pm.kokurikuler.index', 'label' => 'Kokurikuler', 'icon' => 'fa-compass'],
        ['route' => 'guru.pm.ttd.index', 'label' => 'TTD', 'icon' => 'fa-signature'],
    ];
@endphp

{{-- Context Bar Persisten (Tahun Ajaran · Semester · Kelas) + Kuota AI --}}
<div class="card" style="margin-bottom:12px">
    <div class="card-body" style="padding:10px 14px;display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between">
        <form method="POST" action="{{ route('guru.pm.konteks.simpan') }}" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">
            @csrf
            <span style="font-size:11px;font-weight:700;color:#667085;text-transform:uppercase;letter-spacing:.3px">Konteks Aktif:</span>
            <select name="tingkat" onchange="this.form.submit()" style="padding:4px 8px;border:1px solid #D0D5DD;border-radius:6px;font-size:12px;font-weight:600">
                @foreach($pmAllowedTingkat as $t)
                    <option value="{{ $t }}" {{ $pmCtx['tingkat'] === $t ? 'selected' : '' }}>
                        Kelas {{ $t }} (Fase {{ \App\Models\PembelajaranMendalam\TujuanPembelajaranPm::faseUntukKelas($t) }})
                    </option>
                @endforeach
            </select>
            @if(!empty($pmScope['is_scoped']) && !empty($pmScope['kelas_list']))
                <span class="badge badge-info" style="font-size:11px;white-space:nowrap" title="Kelas yang Anda ampu">
                    Rombel: {{ implode(', ', $pmScope['kelas_list']) }}
                </span>
            @endif
            <select name="semester" onchange="this.form.submit()" style="padding:4px 8px;border:1px solid #D0D5DD;border-radius:6px;font-size:12px">
                <option value="1" {{ $pmCtx['semester'] === 1 ? 'selected' : '' }}>Semester 1 (Ganjil)</option>
                <option value="2" {{ $pmCtx['semester'] === 2 ? 'selected' : '' }}>Semester 2 (Genap)</option>
            </select>
            <input type="text" name="tahun_ajaran" value="{{ $pmCtx['tahun_ajaran'] }}"
                   style="width:86px;padding:4px 8px;border:1px solid #D0D5DD;border-radius:6px;font-size:12px">
            <button type="submit" class="btn btn-sm btn-ghost" style="padding:4px 8px;font-size:11px">Terapkan</button>
        </form>

        @if(isset($aiQuota))
            <span class="badge {{ $aiQuota['configured'] ? 'badge-info' : 'badge-warning' }}" style="white-space:nowrap">
                <i class="fas fa-magic"></i>
                @if($aiQuota['configured'])
                    AI Gemini: {{ $aiQuota['remaining'] }}/{{ $aiQuota['limit'] }} hari ini
                @else
                    AI Gemini belum dikonfigurasi
                @endif
            </span>
        @endif
    </div>
    <div class="card-body" style="padding:8px 14px;border-top:1px solid #F2F4F7;display:flex;flex-wrap:wrap;gap:5px">
        @foreach($pmTabs as $tab)
            @if(\Illuminate\Support\Facades\Route::has($tab['route']))
                <a href="{{ route($tab['route']) }}"
                   class="btn btn-sm {{ request()->routeIs($tab['route']) ? '' : 'btn-ghost' }}"
                   style="padding:4px 9px;font-size:12px">
                    <i class="fas {{ $tab['icon'] }}"></i> {{ $tab['label'] }}
                </a>
            @endif
        @endforeach
    </div>
</div>

{{-- Banner Status & Catatan Supervisi Kepala Sekolah --}}
@if($pmSupervisi && $pmSupervisi->status !== \App\Models\PembelajaranMendalam\SupervisiPm::STATUS_MENUNGGU)
    @php
        $bgSup = match ($pmSupervisi->status) {
            'disetujui' => '#ECFDF3;border-color:#A6F4C5;color:#027A48',
            'revisi' => '#FEF3F2;border-color:#FECDCA;color:#B42318',
            default => '#EFF8FF;border-color:#B2DDFF;color:#175CD3',
        };
    @endphp
    <div style="margin-bottom:12px;padding:10px 14px;border:1px solid;border-radius:8px;font-size:13px;background:{{ $bgSup }}">
        <strong>
            <i class="fas {{ $pmSupervisi->status === 'disetujui' ? 'fa-lock' : 'fa-clipboard-check' }}"></i>
            Supervisi Kepala Sekolah ({{ $pmCtx['tahun_ajaran'] }} S{{ $pmCtx['semester'] }}): {{ $pmSupervisi->statusLabel() }}
        </strong>
        @if($pmSupervisi->status === 'disetujui')
            — <span>Dokumen terkunci untuk menjaga integritas supervisi.</span>
        @endif
        @if($pmSupervisi->catatan_kepsek)
            <div style="margin-top:4px;font-size:12px"><strong>Catatan Kepsek:</strong> {{ $pmSupervisi->catatan_kepsek }}</div>
        @endif
    </div>
@endif

@if($errors->any())
    <div class="alert alert-err" style="margin-bottom:14px">
        <ul style="margin:0;padding-left:18px">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif
