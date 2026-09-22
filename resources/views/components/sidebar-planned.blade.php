@props(['icon', 'label', 'sub' => false])
<span class="nav-item{{ $sub ? ' nav-subitem' : '' }} nav-planned" title="Segera hadir — backend Fase 2">
    <span class="icon"><x-dynamic-component :component="$icon" class="w-5 h-5" /></span>
    {{ $label }}
    <span class="badge badge-segera">Segera</span>
</span>
