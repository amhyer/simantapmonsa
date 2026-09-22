@props(['icon', 'label', 'open' => false, 'active' => false])
<details class="nav-submenu" {{ $open ? 'open' : '' }}>
    <summary class="nav-item{{ $active ? ' active' : '' }}">
        <span class="icon"><x-dynamic-component :component="$icon" class="w-5 h-5" /></span>
        {{ $label }}
    </summary>
    <div>
        {{ $slot }}
    </div>
</details>
