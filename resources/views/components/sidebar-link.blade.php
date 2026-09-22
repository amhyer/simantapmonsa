@props(['href', 'icon', 'label', 'active' => false, 'sub' => false, 'badge' => null, 'badgeType' => null])
<a href="{{ $href }}"
   class="nav-item{{ $sub ? ' nav-subitem' : '' }}{{ $active ? ' active' : '' }}">
    <span class="icon"><x-dynamic-component :component="$icon" class="w-5 h-5" /></span>
    {{ $label }}
    @if($badge)
        <span class="badge" style="background:{{ $badgeType === 'ok' ? 'var(--ok)' : '#F59E0B' }};color:#fff;font-size:9px;padding:1px 6px;border-radius:8px;margin-left:auto;white-space:nowrap">{{ $badge }}</span>
    @endif
</a>
