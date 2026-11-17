@php
    $user = auth()->user();
    $paletteService = app(\App\Services\Navigation\CommandPaletteService::class);
    $commandMenu = $paletteService->itemsFor($user);
    $contextHint = $paletteService->contextualHintFor($user);
@endphp

<div id="commandPaletteModal" class="cp-overlay" style="display:none" onclick="if(event.target === this) closeCommandPalette()">
    <div class="cp-modal" role="dialog" aria-modal="true" aria-label="Pencarian Menu Cepat">
        <div class="cp-header">
            <i class="fas fa-search cp-search-icon"></i>
            <input type="text" id="cpSearchInput" class="cp-input" placeholder="Cari menu, perangkat ajar (CP/TP/ATP/RPP), atau aksi cepat..." autocomplete="off">
            <button type="button" class="cp-close-btn" onclick="closeCommandPalette()" title="Tutup (Esc)">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div style="padding:8px 16px;background:#F8FAFC;border-bottom:1px solid #EAECF0;font-size:12px;color:#475467;display:flex;align-items:center;gap:8px">
            <i class="fas fa-lightbulb" style="color:#D97706"></i>
            <span>{{ $contextHint }}</span>
        </div>
        <div class="cp-body" id="cpResultsList">
            @forelse($commandMenu as $idx => $cmd)
                <a href="{{ $cmd['url'] }}"
                   class="cp-item"
                   data-index="{{ $idx }}"
                   data-title="{{ strtolower($cmd['title']) }}"
                   data-cat="{{ strtolower($cmd['category']) }}"
                   data-keywords="{{ strtolower($cmd['keywords'] ?? '') }}">
                    <div class="cp-item-icon">
                        <i class="{{ $cmd['icon'] }}"></i>
                    </div>
                    <div class="cp-item-info">
                        <div class="cp-item-title">{{ $cmd['title'] }}</div>
                        <div class="cp-item-cat">{{ $cmd['category'] }}</div>
                    </div>
                    <span class="cp-item-enter"><i class="fas fa-arrow-right"></i></span>
                </a>
            @empty
                <div class="cp-empty">Tidak ada daftar perintah cepat untuk akun ini.</div>
            @endforelse
            <div id="cpNoMatch" class="cp-empty" style="display:none">Menu tidak ditemukan. Coba kata kunci lain.</div>
        </div>
        <div class="cp-footer">
            <span>Gunakan <kbd>↑</kbd> <kbd>↓</kbd> untuk memilih</span>
            <span><kbd>Enter</kbd> untuk membuka</span>
            <span><kbd>Esc</kbd> untuk keluar</span>
        </div>
    </div>
</div>

<script>
    let cpSelectedIndex = -1;

    function openCommandPalette() {
        const modal = document.getElementById('commandPaletteModal');
        const input = document.getElementById('cpSearchInput');
        if (!modal || !input) return;
        modal.style.display = 'flex';
        input.value = '';
        filterCommandPalette('');
        setTimeout(() => input.focus(), 50);
    }

    function closeCommandPalette() {
        const modal = document.getElementById('commandPaletteModal');
        if (modal) modal.style.display = 'none';
    }

    function toggleCommandPalette() {
        const modal = document.getElementById('commandPaletteModal');
        if (!modal) return;
        if (modal.style.display === 'flex') {
            closeCommandPalette();
        } else {
            openCommandPalette();
        }
    }

    function filterCommandPalette(q) {
        const term = q.trim().toLowerCase();
        const items = document.querySelectorAll('.cp-item');
        let matched = 0;
        cpSelectedIndex = -1;

        items.forEach(item => {
            const title = item.getAttribute('data-title') || '';
            const cat = item.getAttribute('data-cat') || '';
            const keywords = item.getAttribute('data-keywords') || '';
            item.classList.remove('active');
            if (term === '' || title.includes(term) || cat.includes(term) || keywords.includes(term)) {
                item.style.display = 'flex';
                matched++;
            } else {
                item.style.display = 'none';
            }
        });

        const noMatch = document.getElementById('cpNoMatch');
        if (noMatch) {
            noMatch.style.display = matched === 0 ? 'block' : 'none';
        }

        const firstVisible = Array.from(items).find(i => i.style.display !== 'none');
        if (firstVisible) {
            firstVisible.classList.add('active');
            cpSelectedIndex = parseInt(firstVisible.getAttribute('data-index') || '0', 10);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('cpSearchInput');
        if (!input) return;

        input.addEventListener('input', function (e) {
            filterCommandPalette(e.target.value);
        });

        input.addEventListener('keydown', function (e) {
            const visibleItems = Array.from(document.querySelectorAll('.cp-item')).filter(i => i.style.display !== 'none');
            if (visibleItems.length === 0) return;

            let currentActiveIndex = visibleItems.findIndex(i => i.classList.contains('active'));

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                let nextIndex = (currentActiveIndex + 1) % visibleItems.length;
                visibleItems.forEach(i => i.classList.remove('active'));
                visibleItems[nextIndex].classList.add('active');
                visibleItems[nextIndex].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                let prevIndex = currentActiveIndex <= 0 ? visibleItems.length - 1 : currentActiveIndex - 1;
                visibleItems.forEach(i => i.classList.remove('active'));
                visibleItems[prevIndex].classList.add('active');
                visibleItems[prevIndex].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const active = visibleItems.find(i => i.classList.contains('active')) || visibleItems[0];
                if (active) {
                    window.location.href = active.getAttribute('href');
                }
            }
        });

        window.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                toggleCommandPalette();
            } else if (e.key === 'Escape') {
                closeCommandPalette();
            }
        });
    });
</script>
