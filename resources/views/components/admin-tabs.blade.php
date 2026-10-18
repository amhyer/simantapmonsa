@php
    $tabs = [
        'erapor' => ['label' => 'E-Rapor', 'route' => 'admin.dashboard'],
        'cbt' => ['label' => 'CBT/Ujian', 'route' => 'admin.cbt.sessions.index'],
        'tka' => ['label' => 'TKA', 'route' => 'admin.tka.dashboard'],
        'setup' => ['label' => 'Setup', 'route' => 'admin.users.index'],
    ];
    $activeTab = activeAdminTab();
@endphp

<div class="admin-tabs">
    @foreach($tabs as $tabKey => $tabData)
        <a href="{{ route($tabData['route']) }}" 
           class="admin-tab-button {{ $activeTab === $tabKey ? 'active' : '' }}">
            {{ $tabData['label'] }}
        </a>
    @endforeach
</div>

<style>
.admin-tabs {
    display: flex;
    gap: 0;
    padding: 0;
    background: var(--navy, #003366);
    overflow-x: auto;
    white-space: nowrap;
    border-bottom: 1px solid var(--line, #e0e0e0);
    height: 48px;
    align-items: center;
}

.admin-tab-button {
    padding: 10px 20px;
    font-size: 13px;
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    border: none;
    background: none;
    cursor: pointer;
    transition: all 0.2s ease;
    border-bottom: 2px solid transparent;
    height: 48px;
    display: flex;
    align-items: center;
    white-space: nowrap;
    flex-shrink: 0;
}

.admin-tab-button:hover {
    color: rgba(255, 255, 255, 0.9);
}

.admin-tab-button.active {
    color: #ffffff;
    border-bottom-color: var(--gold, #ffc107);
}

@media (max-width: 768px) {
    .admin-tabs {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .admin-tab-button {
        padding: 8px 16px;
        font-size: 12px;
    }
}
</style>
