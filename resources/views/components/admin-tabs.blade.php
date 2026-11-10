@php
    $tabs = [
        'erapor' => ['label' => 'E-Rapor', 'route' => 'admin.dashboard', 'asesmen' => false, 'icon' => 'fas fa-graduation-cap'],
        'cbt' => ['label' => 'CBT/Ujian', 'route' => 'admin.cbt.sessions.index', 'asesmen' => true, 'icon' => 'fas fa-laptop-code'],
        'tka' => ['label' => 'TKA', 'route' => 'admin.tka.dashboard', 'asesmen' => true, 'icon' => 'fas fa-chart-pie'],
        'setup' => ['label' => 'Setup', 'route' => 'admin.users.index', 'asesmen' => false, 'icon' => 'fas fa-sliders-h'],
    ];

    // Tab modul asesmen disembunyikan selama `simantap.cbt_enabled` mati:
    // seluruh route di baliknya dijawab 404 oleh middleware `module.asesmen`,
    // sehingga tab yang tetap tampil hanya akan menipu pengguna.
    if (!modulAsesmenAktif()) {
        $tabs = array_filter($tabs, fn (array $tab) => empty($tab['asesmen']));
    }

    $activeTab = activeAdminTab();
@endphp

<div class="admin-tabs" role="navigation" aria-label="Tab Modul Admin">
    @foreach($tabs as $tabKey => $tabData)
        <a href="{{ route($tabData['route']) }}" 
           class="admin-tab-button {{ $activeTab === $tabKey ? 'active' : '' }}">
            <i class="{{ $tabData['icon'] }} tab-icon"></i>
            <span>{{ $tabData['label'] }}</span>
        </a>
    @endforeach
</div>

<style>
.admin-tabs {
    display: flex;
    gap: 8px;
    padding: 6px 24px;
    background: #FFFFFF;
    overflow-x: auto;
    white-space: nowrap;
    border-bottom: 1px solid var(--line, #E2E8F0);
    min-height: 48px;
    align-items: center;
    box-sizing: border-box;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
}

.admin-tab-button {
    padding: 6px 14px;
    font-size: 13px;
    font-weight: 500;
    color: var(--muted, #64748B);
    text-decoration: none;
    border: 1px solid transparent;
    border-radius: 99px;
    background: transparent;
    cursor: pointer;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    white-space: nowrap;
    flex-shrink: 0;
}

.admin-tab-button .tab-icon {
    font-size: 13px;
    opacity: 0.75;
}

.admin-tab-button:hover {
    color: var(--navy, #1F3864);
    background: #F1F5F9;
}

.admin-tab-button.active {
    color: var(--navy, #1F3864);
    background: #EEF4FF;
    border-color: #C7D7FE;
    font-weight: 700;
}

.admin-tab-button.active .tab-icon {
    color: var(--gold, #B8860B);
    opacity: 1;
}

@media (max-width: 768px) {
    .admin-tabs {
        padding: 6px 12px;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .admin-tab-button {
        padding: 6px 12px;
        font-size: 12px;
        gap: 6px;
    }
}
</style>
