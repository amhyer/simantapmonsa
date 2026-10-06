@props(['icon', 'label', 'open' => false, 'active' => false])
<details class="nav-submenu" @if($open) open @endif>
    <summary class="nav-item{{ $active ? ' active' : '' }}">
        <span class="icon"><x-dynamic-component :component="$icon" class="w-5 h-5" /></span>
        {{ $label }}
    </summary>
    <div>
        {{ $slot }}
    </div>
</details>
