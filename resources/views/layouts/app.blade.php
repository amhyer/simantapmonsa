<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'SIMANTAP_MONSA'))</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#1F3864">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
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
    @elseif(auth()->check() && auth()->user()->peran === 'super_admin')
    <style>
        :root {
            --navy: #4338CA;
            --navy-light: #6366F1;
            --navy-dark: #312E81;
        }
    </style>
    @endif
    <style>
        .badge { white-space: nowrap; }
        .badge-success { background: #E6F5F0; color: var(--ok); }
        .badge-warning { background: #FEF3E2; color: var(--warn); }
        .badge-danger { background: #FEE9E7; color: var(--bad); }
        .badge-info { background: #EEF2F9; color: var(--navy); }
        .badge-secondary { background: #F2F4F7; color: var(--muted); }
        .table-wrapper table.table-compact th,
        .table-wrapper table.table-compact td { padding: 8px 8px; }
        @media (max-width: 768px) {
            .topbar { padding: 0 12px; gap: 8px; }
            .topbar-left { min-width: 0; flex: 1; overflow: hidden; }
            .topbar-title { min-width: 0; flex: 1; }
            .topbar-title h1 { font-size: 15px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .topbar-academic-pill { display: none !important; }
            .topbar-right { gap: 6px; flex-shrink: 0; }
            .header-actions .btn { padding: 5px 8px; font-size: 11.5px; }
            .page-content { padding-bottom: 84px !important; }
            #toTop { bottom: 76px !important; right: 16px !important; }
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <a href="{{ route('home') }}" style="display:flex;align-items:center;gap:10px;text-decoration:none">
                <img src="{{ asset('images/logo-128.png') }}" alt="Logo SIMANTAP_MONSA" style="width:38px;height:38px;border-radius:10px;object-fit:cover;border:1.5px solid #B8860B;flex-shrink:0;box-shadow:0 2px 8px rgba(0,0,0,0.15)">
                <div>
                    <div class="brand-text">SIMANTAP_MONSA</div>
                    <div class="brand-sub" title="{{ $layoutNamaSekolah ?? 'UPTD SDN Unggulan Mongisidi 1' }}">{{ $layoutNamaSekolah ?? 'UPTD SDN Unggulan Mongisidi 1' }}</div>
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
                <img src="{{ $userFoto }}" alt="Foto" style="width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0">
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
        @if(session()->has('impersonator_id'))
            <div style="background:linear-gradient(135deg, #1E1B4B 0%, #4338CA 100%);color:#fff;padding:12px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px;box-shadow:0 3px 12px rgba(67,56,202,0.3);position:sticky;top:0;z-index:999">
                <div style="display:flex;align-items:center;gap:12px;font-size:13px">
                    <span style="background:var(--bad);color:#fff;font-weight:800;padding:3px 10px;border-radius:999px;font-size:11px;letter-spacing:0.5px">SUPER ADMIN CONNECT</span>
                    <span>Anda terhubung masuk sebagai <b>{{ auth()->user()->nama_lengkap }}</b> ({{ auth()->user()->nama_pengguna }}). Sedang mengelola data sekolah ini.</span>
                </div>
                <form method="POST" action="{{ route('superadmin.impersonate.leave') }}" style="margin:0">
                    @csrf
                    <button type="submit" class="btn btn-sm" style="background:#fff;color:#4338CA;font-weight:700;border:none;box-shadow:0 1px 3px rgba(0,0,0,0.2);cursor:pointer;white-space:nowrap">
                        <i class="fas fa-arrow-left"></i> Kembali ke Super Admin
                    </button>
                </form>
            </div>
        @elseif(session()->has('admin_impersonator_id'))
            <div style="background:linear-gradient(135deg, #065F46 0%, #059669 100%);color:#fff;padding:12px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px;box-shadow:0 3px 12px rgba(5,150,105,0.3);position:sticky;top:0;z-index:999">
                <div style="display:flex;align-items:center;gap:12px;font-size:13px">
                    <span style="background:#F59E0B;color:#111827;font-weight:800;padding:3px 10px;border-radius:999px;font-size:11px;letter-spacing:0.5px">ADMIN &rarr; GURU</span>
                    <span>Anda sedang masuk sebagai Guru <b>{{ auth()->user()->nama_lengkap }}</b> ({{ auth()->user()->nama_pengguna }}).</span>
                </div>
                <form method="POST" action="{{ route('admin.impersonate.leave') }}" style="margin:0">
                    @csrf
                    <button type="submit" class="btn btn-sm" style="background:#fff;color:#065F46;font-weight:700;border:none;box-shadow:0 1px 3px rgba(0,0,0,0.2);cursor:pointer;white-space:nowrap">
                        <i class="fas fa-arrow-left"></i> Kembali ke Admin
                    </button>
                </form>
            </div>
        @endif
        <header class="topbar">
            <div class="topbar-left">
                <button class="topbar-toggle" onclick="toggleSidebar()" aria-label="Buka Menu Navigasi">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="topbar-title">
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                        <h1 style="margin:0">@yield('page_title', 'Dashboard')</h1>
                        @if($currentUser && $currentUser->peran === 'super_admin')
                            <span class="topbar-academic-pill" style="background:#EEF2FF;color:#4338CA;border-color:#C7D2FE;font-weight:700">
                                <i class="fas fa-shield-halved"></i> SUPER ADMIN
                            </span>
                        @elseif($acad = semesterAktifLabel())
                            <span class="topbar-academic-pill" title="Tahun Ajaran & Semester Aktif">
                                <i class="fas fa-calendar-alt"></i> {{ $acad }}
                            </span>
                        @endif
                    </div>
                    <div class="subtitle">@yield('page_subtitle', '')</div>
                </div>
            </div>
            
            <div class="topbar-right">
                {{-- Aksi Halaman (Diposisikan di kiri Profil Pengguna) --}}
                @if(View::hasSection('header_actions'))
                    <div class="header-actions" style="display:flex;align-items:center;gap:8px">
                        @yield('header_actions')
                    </div>
                @endif

                <button type="button" class="topbar-search-btn" onclick="openCommandPalette()" title="Cari Menu Cepat (Ctrl+K)">
                    <i class="fas fa-search"></i>
                    <span class="search-hint">Cari menu...</span>
                    <kbd>Ctrl K</kbd>
                </button>
                <div class="notif-wrap">
                    @php $notifs = $layoutNotifs ?? []; @endphp
                    <button class="topbar-btn" title="Notifikasi & Tugas" onclick="document.getElementById('notifPanel').classList.toggle('show')" style="position:relative">
                        <i class="fas fa-bell"></i>
                        @if(count($notifs) > 0)
                            <span style="position:absolute;top:2px;right:2px;min-width:16px;height:16px;border-radius:999px;background:var(--bad);color:#fff;font-size:10px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;padding:0 4px">{{ count($notifs) }}</span>
                        @endif
                    </button>
                    <div class="notif-panel" id="notifPanel">
                        <div class="notif-head">Notifikasi &amp; Tugas</div>
                        @forelse($notifs as $notif)
                            @if(!empty($notif['url']))
                                <a href="{{ $notif['url'] }}" class="notif-item" style="text-decoration:none;color:inherit">
                                    <i class="{{ $notif['icon'] }}"></i>
                                    <div>
                                        <div>{{ $notif['teks'] }}</div>
                                        <small>{{ $notif['waktu'] }}</small>
                                    </div>
                                </a>
                            @else
                                <div class="notif-item">
                                    <i class="{{ $notif['icon'] }}"></i>
                                    <div>
                                        <div>{{ $notif['teks'] }}</div>
                                        <small>{{ $notif['waktu'] }}</small>
                                    </div>
                                </div>
                            @endif
                        @empty
                            <div class="notif-empty">
                                <span class="icon"><i class="fas fa-bell-slash"></i></span>
                                Belum ada notifikasi baru.
                            </div>
                        @endforelse
                    </div>
                </div>
                
                {{-- Profil Pengguna Selalu di Posisi Paling Kanan --}}
                <div class="user-dropdown" style="position:relative" onclick="this.querySelector('.dropdown-menu').classList.toggle('show')">
                    @if($userFoto)
                        <img src="{{ $userFoto }}" alt="Foto" class="avatar-sm" style="object-fit:cover">
                    @else
                        <div class="avatar-sm">{{ $currentUser ? substr($currentUser->nama_lengkap, 0, 2) : '?' }}</div>
                    @endif
                    <span class="user-name">{{ $currentUser ? $currentUser->nama_lengkap : 'Guest' }}</span>
                    <i class="fas fa-chevron-down chevron"></i>
                    <div class="dropdown-menu" style="display:none;position:absolute;top:100%;right:0;margin-top:8px;background:#fff;border:1px solid var(--line);border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,0.12);min-width:210px;z-index:200;overflow:hidden">
                        @if($currentUser && $currentUser->peran === 'super_admin')
                            <div style="padding:10px 16px 6px;font-size:11px;font-weight:700;color:#4338CA;text-transform:uppercase;letter-spacing:0.5px">Super Administrator</div>
                            <a href="{{ route('superadmin.dashboard') }}" style="display:flex;align-items:center;gap:8px;padding:9px 16px;font-size:13px;color:var(--text);text-decoration:none"><i class="fas fa-tachometer-alt" style="width:16px;color:#4338CA"></i> Dasbor Multi-Sekolah</a>
                            <a href="{{ route('superadmin.tenants.index') }}" style="display:flex;align-items:center;gap:8px;padding:9px 16px;font-size:13px;color:var(--text);text-decoration:none"><i class="fas fa-school" style="width:16px;color:#4338CA"></i> Katalog Sekolah</a>
                            <a href="{{ route('superadmin.users.index') }}" style="display:flex;align-items:center;gap:8px;padding:9px 16px;font-size:13px;color:var(--text);text-decoration:none"><i class="fas fa-users" style="width:16px;color:#4338CA"></i> Semua Pengguna</a>
                        @elseif($currentUser && $currentUser->peran === 'admin')
                            <a href="{{ route('home') }}" style="display:flex;align-items:center;gap:8px;padding:10px 16px;font-size:13px;color:var(--text);text-decoration:none"><i class="fas fa-home" style="width:16px"></i> Beranda</a>
                            <a href="{{ route('admin.profile') }}" style="display:flex;align-items:center;gap:8px;padding:10px 16px;font-size:13px;color:var(--text);text-decoration:none"><i class="fas fa-user-shield" style="width:16px"></i> Profil Admin</a>
                        @elseif($currentUser && $currentUser->peran === 'siswa')
                            <a href="{{ route('home') }}" style="display:flex;align-items:center;gap:8px;padding:10px 16px;font-size:13px;color:var(--text);text-decoration:none"><i class="fas fa-home" style="width:16px"></i> Beranda</a>
                            <a href="{{ route('siswa.profil.show') }}" style="display:flex;align-items:center;gap:8px;padding:10px 16px;font-size:13px;color:var(--text);text-decoration:none"><i class="fas fa-user" style="width:16px"></i> Profil Saya</a>
                        @else
                            <a href="{{ route('home') }}" style="display:flex;align-items:center;gap:8px;padding:10px 16px;font-size:13px;color:var(--text);text-decoration:none"><i class="fas fa-home" style="width:16px"></i> Beranda</a>
                        @endif
                        <div style="border-top:1px solid var(--line)"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" style="display:flex;align-items:center;gap:8px;padding:10px 16px;font-size:13px;color:var(--bad);text-decoration:none;background:none;border:none;width:100%;cursor:pointer;text-align:left"><i class="fas fa-sign-out-alt" style="width:16px"></i> Keluar</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>
        
        <main class="page-content">
            @if(session('warning'))
                <div class="alert alert-warning" role="alert">{{ session('warning') }}</div>
            @endif
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
            @if(isset($errors) && $errors->any())
                <div class="note note-bad" style="margin-bottom:16px">
                    <i class="fas fa-exclamation-circle"></i> Periksa kembali isian berikut:
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
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
            const notifWrap = document.querySelector('.notif-wrap');
            const notifPanel = document.getElementById('notifPanel');
            if (notifWrap && notifPanel && !notifWrap.contains(e.target)) {
                notifPanel.classList.remove('show');
            }
        });
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            const icons = { success: '✅', error: '❌', warning: '⚠️' };
            toast.textContent = `${icons[type] || '📢'} ${message}`;
            container.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(100px)'; setTimeout(() => toast.remove(), 300); }, 3000);
        }
    </script>
    <x-command-palette />
    <x-mobile-bottom-nav />
    @stack('scripts')
</body>
</html>
