<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SIMANTAP')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>document.documentElement.classList.add('js')</script>
    @if(auth()->check() && auth()->user()->peran === 'siswa')
    <style>
        :root {
            --navy: var(--violet);
            --navy-light: var(--violet-light);
            --navy-dark: var(--violet-dark);
        }
    </style>
    @endif
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <a href="{{ route('home') }}">
                <div class="logo-icon">SM</div>
                <div>
                    <div class="brand-text">SIMANTAP</div>
                    <div class="brand-sub">Situs Mandiri Terintegrasi</div>
                </div>
            </a>
        </div>
        
        {{-- $layoutUser/$layoutUserFoto disediakan ViewComposer (AppServiceProvider) + cache;
             fallback auth()->user() agar view tetap aman saat composer tidak jalan (mis. test). --}}
        @php
            $currentUser = $layoutUser ?? auth()->user();
            $userFoto = $layoutUserFoto ?? null;
        @endphp
        <div class="sidebar-user">
            @if($userFoto)
                <img src="{{ asset('storage/' . $userFoto) }}" alt="Foto" style="width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0">
            @else
                <div class="avatar">{{ $currentUser ? substr($currentUser->nama_lengkap, 0, 2) : '?' }}</div>
            @endif
            <div class="user-info">
                <div class="user-name">{{ $currentUser ? $currentUser->nama_lengkap : 'Guest' }}</div>
                <div class="user-role">{{ $currentUser ? $currentUser->peran : '' }}</div>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            @yield('sidebar')
        </nav>
        
        <div class="sidebar-footer">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Keluar
                </button>
            </form>
        </div>
    </aside>
    
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="topbar-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="topbar-title">
                    <h1>@yield('page_title', 'Dashboard')</h1>
                    <div class="subtitle">@yield('page_subtitle', '')</div>
                </div>
            </div>
            
            <div class="topbar-right">
                <button class="topbar-btn" title="Notifikasi">
                    <i class="fas fa-bell"></i>
                    <span class="notification-dot"></span>
                </button>
                
                <div class="user-dropdown" style="position:relative" onclick="this.querySelector('.dropdown-menu').classList.toggle('show')">
                    @if($userFoto)
                        <img src="{{ asset('storage/' . $userFoto) }}" alt="Foto" class="avatar-sm" style="object-fit:cover">
                    @else
                        <div class="avatar-sm">{{ $currentUser ? substr($currentUser->nama_lengkap, 0, 2) : '?' }}</div>
                    @endif
                    <span class="user-name">{{ $currentUser ? $currentUser->nama_lengkap : 'Guest' }}</span>
                    <i class="fas fa-chevron-down chevron"></i>
                    <div class="dropdown-menu" style="display:none;position:absolute;top:100%;right:0;margin-top:8px;background:#fff;border:1px solid var(--line);border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,0.12);min-width:180px;z-index:200;overflow:hidden">
                        <a href="{{ route('home') }}" style="display:flex;align-items:center;gap:8px;padding:10px 16px;font-size:13px;color:var(--text);text-decoration:none"><i class="fas fa-home" style="width:16px"></i> Beranda</a>
                        @if($currentUser && $currentUser->peran === 'siswa')
                            <a href="{{ route('siswa.profil.edit') }}" style="display:flex;align-items:center;gap:8px;padding:10px 16px;font-size:13px;color:var(--text);text-decoration:none"><i class="fas fa-user" style="width:16px"></i> Profil Saya</a>
                        @endif
                        <div style="border-top:1px solid var(--line)"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" style="display:flex;align-items:center;gap:8px;padding:10px 16px;font-size:13px;color:var(--bad);text-decoration:none;background:none;border:none;width:100%;cursor:pointer;text-align:left"><i class="fas fa-sign-out-alt" style="width:16px"></i> Keluar</button>
                        </form>
                    </div>
                </div>
                
                @yield('header_actions')
            </div>
        </header>
        
        <main class="page-content">
            @if(session('success'))
                <div class="note note-ok" style="margin-bottom:16px">
                    <i class="fas fa-check-circle"></i> {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="note note-warn" style="margin-bottom:16px">
                    <i class="fas fa-exclamation-triangle"></i> {{ session('error') }}
                </div>
            @endif
            @yield('content')
        </main>
    </div>
    
    <div class="toast-container" id="toastContainer"></div>
    
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                document.getElementById('sidebar').classList.remove('open');
                document.getElementById('sidebarOverlay').classList.remove('active');
            }
        });
        document.addEventListener('click', function(e) {
            const dropdown = document.querySelector('.user-dropdown');
            if (dropdown && !dropdown.contains(e.target)) {
                const menu = dropdown.querySelector('.dropdown-menu');
                if (menu) menu.classList.remove('show');
            }
        });
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            const icons = { success: '✅', error: '❌', warning: '⚠️' };
            toast.innerHTML = `${icons[type] || '📢'} ${message}`;
            container.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(100px)'; setTimeout(() => toast.remove(), 300); }, 3000);
        }
    </script>
    @stack('scripts')
</body>
</html>
