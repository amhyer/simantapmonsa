@php
    $pmTabs = [
        ['route' => 'guru.pm.alokasi.index', 'label' => 'Alokasi Waktu', 'icon' => 'fa-clock'],
        ['route' => 'guru.pm.cp.index', 'label' => 'CP', 'icon' => 'fa-award'],
        ['route' => 'guru.pm.tp.index', 'label' => 'TP', 'icon' => 'fa-bullseye'],
        ['route' => 'guru.pm.atp.index', 'label' => 'ATP', 'icon' => 'fa-project-diagram'],
        ['route' => 'guru.pm.buku-paket.index', 'label' => 'Buku Paket (SIBI)', 'icon' => 'fa-book'],
        ['route' => 'guru.pm.kktp.index', 'label' => 'KKTP', 'icon' => 'fa-sliders-h'],
        ['route' => 'guru.pm.kalender.index', 'label' => 'Kalender', 'icon' => 'fa-calendar-alt'],
        ['route' => 'guru.pm.prota.index', 'label' => 'Prota', 'icon' => 'fa-list-ol'],
        ['route' => 'guru.pm.prosem.index', 'label' => 'Prosem', 'icon' => 'fa-th'],
        ['route' => 'guru.perangkat.index', 'label' => 'Modul Ajar / RPM', 'icon' => 'fa-folder-open'],
        ['route' => 'guru.perangkat.jurnal.index', 'label' => 'Jurnal Mengajar', 'icon' => 'fa-book-reader'],
        ['route' => 'guru.pm.generate.index', 'label' => 'Generate Sekaligus', 'icon' => 'fa-bolt'],
        ['route' => 'guru.pm.kokurikuler.index', 'label' => 'Kokurikuler', 'icon' => 'fa-compass'],
        ['route' => 'guru.pm.ttd.index', 'label' => 'Tanda Tangan', 'icon' => 'fa-signature'],
    ];
@endphp

<div class="card" style="margin-bottom:14px">
    <div class="card-body" style="padding:10px 14px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;justify-content:space-between">
        <div style="display:flex;flex-wrap:wrap;gap:6px">
            @foreach($pmTabs as $tab)
                @if(\Illuminate\Support\Facades\Route::has($tab['route']))
                    <a href="{{ route($tab['route']) }}"
                       class="btn btn-sm {{ request()->routeIs($tab['route']) ? '' : 'btn-ghost' }}">
                        <i class="fas {{ $tab['icon'] }}"></i> {{ $tab['label'] }}
                    </a>
                @endif
            @endforeach
        </div>
        @if(isset($aiQuota))
            <div style="font-size:12px;color:#475467;display:flex;align-items:center;gap:6px">
                <span class="badge {{ $aiQuota['configured'] ? 'badge-info' : 'badge-warning' }}">
                    <i class="fas fa-magic"></i>
                    @if($aiQuota['configured'])
                        AI Gemini: {{ $aiQuota['remaining'] }}/{{ $aiQuota['limit'] }} hari ini
                    @else
                        AI Gemini belum dikonfigurasi
                    @endif
                </span>
            </div>
        @endif
    </div>
</div>

@if($errors->any())
    <div class="alert alert-err" style="margin-bottom:14px">
        <ul style="margin:0;padding-left:18px">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif
