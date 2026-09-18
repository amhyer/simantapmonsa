<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SIMANTAP')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --navy: #1F3864;
            --navy-light: #2B4A80;
            --navy-dark: #152A4A;
            --gold: #B8860B;
            --gold-light: #D4A017;
            --sidebar-width: 260px;
            --header-height: 64px;
            --bg: #F0F2F5;
            --card: #FFFFFF;
            --line: #E4E7EC;
            --muted: #667085;
            --text: #101828;
            --ok: #12805C;
            --bad: #B42318;
            --warn: #B54708;
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --radius: 12px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            font-size: 14px;
            line-height: 1.6;
            overflow-x: hidden;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--navy);
            color: #fff;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease;
            overflow-y: auto;
            overflow-x: hidden;
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }
        .sidebar-brand {
            padding: 18px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            flex-shrink: 0;
        }
        .sidebar-brand a {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: #fff;
        }
        .sidebar-brand .logo-icon {
            width: 36px;
            height: 36px;
            background: var(--gold);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 16px;
            color: #fff;
            flex-shrink: 0;
        }
        .sidebar-brand .brand-text {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .sidebar-brand .brand-sub {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: var(--gold);
            font-weight: 700;
            margin-top: 2px;
        }
        .sidebar-user {
            padding: 16px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .sidebar-user .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            color: #fff;
            flex-shrink: 0;
        }
        .sidebar-user .user-info { flex: 1; min-width: 0; }
        .sidebar-user .user-name {
            font-weight: 600;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sidebar-user .user-role {
            font-size: 11px;
            color: rgba(255,255,255,0.6);
            text-transform: capitalize;
        }
        .sidebar-nav {
            flex: 1;
            padding: 12px 10px;
        }
        .sidebar-nav .nav-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: rgba(255,255,255,0.35);
            padding: 10px 12px 6px;
            font-weight: 700;
        }
        .sidebar-nav .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 9px 14px;
            border-radius: 8px;
            color: rgba(255,255,255,0.75);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.15s ease;
            margin-bottom: 2px;
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }
        .sidebar-nav .nav-item:hover {
            background: rgba(255,255,255,0.08);
            color: #fff;
        }
        .sidebar-nav .nav-item.active {
            background: rgba(255,255,255,0.12);
            color: #fff;
            font-weight: 600;
            box-shadow: inset 3px 0 0 var(--gold);
        }
        .sidebar-nav .nav-item .icon {
            width: 20px;
            text-align: center;
            font-size: 15px;
            flex-shrink: 0;
        }
        .sidebar-nav .nav-item .badge {
            margin-left: auto;
            background: var(--gold);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 99px;
        }
        .sidebar-nav details.nav-submenu > summary { list-style: none; }
        .sidebar-nav details.nav-submenu > summary::-webkit-details-marker { display: none; }
        .sidebar-nav details.nav-submenu > summary::after {
            content: '\f078';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: 10px;
            margin-left: auto;
            opacity: .6;
        }
        .sidebar-nav details.nav-submenu[open] > summary::after { content: '\f077'; }
        .sidebar-nav .nav-subitem { padding-left: 46px; font-size: 12.5px; }
        .sidebar-nav .nav-planned { opacity: .45; cursor: not-allowed; }
        .sidebar-nav .badge-segera {
            margin-left: auto;
            background: rgba(255,255,255,.15);
            color: rgba(255,255,255,.75);
            font-size: 9px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 99px;
            white-space: nowrap;
        }
        .sidebar-footer {
            padding: 12px 16px;
            border-top: 1px solid rgba(255,255,255,0.08);
            flex-shrink: 0;
        }
        .sidebar-footer .logout-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            border-radius: 8px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: 0.15s;
            background: none;
            border: none;
            width: 100%;
            cursor: pointer;
        }
        .sidebar-footer .logout-btn:hover {
            background: rgba(255,255,255,0.08);
            color: #fff;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .topbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: #fff;
            border-bottom: 1px solid var(--line);
            padding: 0 24px;
            height: var(--header-height);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .topbar-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .topbar-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 20px;
            color: var(--text);
            cursor: pointer;
            padding: 4px;
        }
        .topbar-title h1 {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.3px;
        }
        .topbar-title .subtitle {
            font-size: 12px;
            color: var(--muted);
            font-weight: 400;
        }
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .topbar-right .topbar-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 1px solid var(--line);
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: 0.15s;
            color: var(--muted);
            font-size: 16px;
            position: relative;
        }
        .topbar-right .topbar-btn:hover {
            background: var(--bg);
            border-color: var(--navy);
            color: var(--navy);
        }
        .topbar-right .topbar-btn .notification-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            background: var(--bad);
            border-radius: 50%;
            border: 2px solid #fff;
        }
        .topbar-right .user-dropdown {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 4px 12px 4px 4px;
            border-radius: 99px;
            border: 1px solid var(--line);
            cursor: pointer;
            transition: 0.15s;
            background: #fff;
        }
        .topbar-right .user-dropdown:hover {
            border-color: var(--navy);
        }
        .topbar-right .user-dropdown .avatar-sm {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--navy);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 12px;
        }
        .topbar-right .user-dropdown .user-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
        }
        .topbar-right .user-dropdown .chevron {
            color: var(--muted);
            font-size: 12px;
        }
        .dropdown-menu.show { display: block !important; }
        .dropdown-menu a:hover, .dropdown-menu button:hover { background: var(--bg); }
        .page-content {
            padding: 24px 28px 60px;
            flex: 1;
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: 280px; }
            .sidebar.open { transform: translateX(0); }
            .sidebar-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 999; display: none; }
            .sidebar-overlay.active { display: block; }
            .main-content { margin-left: 0; }
            .topbar-toggle { display: block; }
            .topbar-title h1 { font-size: 16px; }
            .topbar-right .user-name { display: none; }
            .page-content { padding: 16px; }
        }
        @media (max-width: 480px) {
            .topbar { padding: 0 12px; }
            .topbar-title .subtitle { display: none; }
        }
        .page-title { margin-bottom: 24px; }
        .page-title h2 { font-size: 22px; font-weight: 700; letter-spacing: -0.5px; }
        .page-title p { color: var(--muted); font-size: 14px; margin-top: 4px; }
        .card {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--line);
            overflow: hidden;
        }
        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .card-header h3 { font-size: 15px; font-weight: 700; margin: 0; }
        .card-body { padding: 20px; }
        .card-body.tight { padding: 0; }
        .grid { display: grid; gap: 16px; }
        .grid-2 { grid-template-columns: 1fr 1fr; }
        .grid-3 { grid-template-columns: 1fr 1fr 1fr; }
        .grid-4 { grid-template-columns: 1fr 1fr 1fr 1fr; }
        @media (max-width: 1024px) { .grid-4 { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 768px) { .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; } }
        .stat-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 18px 20px;
            position: relative;
            overflow: hidden;
            transition: 0.15s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .stat-card .stat-icon { position: absolute; right: 16px; top: 16px; font-size: 28px; opacity: 0.15; }
        .stat-card .stat-label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted); font-weight: 600; }
        .stat-card .stat-value { font-size: 28px; font-weight: 800; letter-spacing: -0.5px; margin-top: 4px; }
        .stat-card .stat-change { font-size: 12px; margin-top: 4px; }
        .stat-card .stat-change.up { color: var(--ok); }
        .stat-card .stat-change.down { color: var(--bad); }
        .stat-card.primary .stat-value { color: var(--navy); }
        .stat-card.primary::after { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--navy); }
        .stat-card.gold .stat-value { color: var(--gold); }
        .stat-card.gold::after { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--gold); }
        .stat-card.ok .stat-value { color: var(--ok); }
        .stat-card.ok::after { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--ok); }
        .stat-card.bad .stat-value { color: var(--bad); }
        .stat-card.bad::after { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--bad); }
        .stat { background: #fff; border: 1px solid var(--line); border-radius: var(--radius); padding: 18px 20px; position: relative; overflow: hidden; }
        .stat::after { content: ''; position: absolute; inset: 0 auto 0 0; width: 4px; background: var(--navy); }
        .stat.gold::after { background: var(--gold); }
        .stat.ok::after { background: var(--ok); }
        .stat.bad::after { background: var(--bad); }
        .stat .label { font-size: 12px; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); font-weight: 600; }
        .stat .value { font-size: 28px; font-weight: 800; letter-spacing: -0.5px; margin-top: 4px; }
        .stat .desc { font-size: 12px; color: var(--muted); margin-top: 2px; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.15s ease;
            text-decoration: none;
            white-space: nowrap;
        }
        .btn-primary, .btn { background: var(--navy); color: #fff; border-color: var(--navy); }
        .btn:hover { background: var(--navy-light); border-color: var(--navy-light); }
        .btn-gold { background: var(--gold); color: #fff; border-color: var(--gold); }
        .btn-gold:hover { background: var(--gold-light); border-color: var(--gold-light); }
        .btn-ghost { background: #fff; color: var(--navy); border-color: var(--line); }
        .btn-ghost:hover { background: var(--bg); border-color: var(--navy); }
        .btn-danger { background: var(--bad); color: #fff; border-color: var(--bad); }
        .btn-danger:hover { background: #8f1a12; border-color: #8f1a12; }
        .btn-sm { padding: 5px 12px; font-size: 12px; border-radius: 6px; }
        .btn-block { width: 100%; justify-content: center; }
        .tag { display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 99px; font-size: 11.5px; font-weight: 600; background: var(--bg); color: var(--muted); }
        .tag-ok { background: #E6F5F0; color: var(--ok); }
        .tag-warn { background: #FEF3E2; color: var(--warn); }
        .tag-bad { background: #FEE9E7; color: var(--bad); }
        .tag-gold { background: #FBF4E4; color: var(--gold); }
        .tag-primary { background: #EEF2F9; color: var(--navy); }
        .tag-secondary { background: #F2F4F7; color: #667085; }
        .tag-info { background: #EEF2F9; color: #1F3864; }
        .tag-mut { background: #F2F4F7; color: var(--muted); }
        .table-wrapper { overflow-x: auto; }
        .table-wrapper table { width: 100%; border-collapse: collapse; }
        .table-wrapper th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted); font-weight: 600; padding: 10px 14px; background: #FAFBFD; border-bottom: 1px solid var(--line); }
        .table-wrapper td { padding: 10px 14px; border-bottom: 1px solid var(--line); vertical-align: middle; }
        .table-wrapper tbody tr:hover td { background: #FAFBFD; }
        .empty-state { text-align: center; padding: 40px 20px; color: var(--muted); }
        .empty-state .icon { font-size: 48px; opacity: 0.3; margin-bottom: 12px; }
        .empty-state h4 { font-size: 16px; font-weight: 600; color: var(--text); }
        .empty-state p { font-size: 14px; margin-top: 4px; }
        .empty { text-align: center; padding: 40px 20px; color: var(--muted); }
        .empty .icon { font-size: 48px; opacity: 0.3; margin-bottom: 12px; }
        .note { padding: 12px 16px; border-radius: 8px; background: #EEF2F9; border-left: 3px solid var(--navy); font-size: 13px; }
        .note-ok { background: #E6F5F0; border-color: var(--ok); }
        .note-warn { background: #FEF3E2; border-color: var(--warn); }
        .note-gold { background: #FBF4E4; border-color: var(--gold); }
        .avatar { width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,0.15); display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; flex-shrink: 0; }
        .avatar-lg { width: 60px; height: 60px; font-size: 21px; }
        .bar { height: 7px; border-radius: 99px; background: #EDF0F5; overflow: hidden; min-width: 70px; }
        .bar .fill { display: block; height: 100%; border-radius: 99px; background: var(--navy); transition: width .5s; }
        .bar .fill-ok { background: var(--ok); }
        .bar .fill-warn { background: var(--gold); }
        .bar .fill-bad { background: var(--bad); }
        .chart-container { height: 280px; position: relative; }
        .chart-container.small { height: 200px; }
        .chart-container.large { height: 340px; }
        .toast-container { position: fixed; bottom: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; }
        .toast { padding: 12px 20px; border-radius: 10px; background: var(--navy); color: #fff; font-weight: 500; font-size: 13px; box-shadow: 0 8px 24px rgba(0,0,0,0.2); animation: slideUp 0.3s ease; display: flex; align-items: center; gap: 10px; }
        .toast-success { background: var(--ok); }
        .toast-error { background: var(--bad); }
        .toast-warning { background: var(--warn); }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    </style>
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
        
        @php
            $currentUser = auth()->user();
            $userFoto = null;
            if ($currentUser) {
                $siswaModel = \App\Models\Siswa::where('nis', $currentUser->nama_pengguna)->first();
                if (!$siswaModel && !empty($currentUser->terhubung_dengan)) {
                    $siswaModel = \App\Models\Siswa::whereIn('id', $currentUser->terhubung_dengan)->first();
                }
                $userFoto = $siswaModel->foto ?? null;
            }
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
