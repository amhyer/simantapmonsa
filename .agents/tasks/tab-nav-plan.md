# Tab-Based Navigation Implementation Plan

## Overview
Implement a professional tab-based navigation system for the SIMANTAP admin dashboard that separates menu items by domain (E-Rapor, CBT/Ujian, TKA, Setup). The sidebar will dynamically change based on the active tab. All existing menu items preserved, no routes/controllers modified.

## Architecture Decisions

### Tab Organization
Tabs divide the 13-item menu into 4 domain-specific sidebars:

1. **E-Rapor Tab** (default): E-learning, grading, progression, transcripts, printing, core referential data
2. **CBT/Ujian Tab**: CBT sessions, question banks, tryout packages/sessions/results
3. **TKA Tab**: Profile dimensions, assessments, early warning
4. **Setup Tab**: User management, dapodik sync, data configuration, system settings, backups

Rationale: Reduces cognitive load by hiding ~75% of menu items per domain, preserves all functionality, aligns with user workflows (teachers use E-Rapor mostly; admins use Setup periodically).

### Tab Detection & Persistence
Use route-based tab detection: inspect the current route name and assign it to a tab. Store active tab in URL via query parameter (`?tab=erapor`), not localStorage, so tabs persist across navigation within the domain but reset on external links.

Rationale: URL-based approach keeps state explicit and shareable; query param is lightweight and does not require cookie disclaimers.

### Layout Architecture
- **New layout**: `layouts/admin-with-tabs.blade.php` extends `layouts/app.blade.php` structure, inserts tab bar between topbar and content
- **Tab bar component**: `components/admin-tabs.blade.php` renders 4 tab buttons, highlights active tab
- **Five split sidebars**: Each contains a subset of $menuAdmin groups; fallback sidebar shows all (for non-admin or unknown routes)
- **Helper function**: `activeAdminTab()` macro in a service provider determines tab from current route
- **Blade macro**: `@activeAdminTab` to render tab button with active state

### Database & State
No database changes. Tab state is stateless and derived from route name on every request.

---

## Implementation Plan

- [ ] 1. Create the tab detection helper function.
      Add `app/Helpers/AdminTabHelper.php` with `activeAdminTab()` function that maps route names to tab keys: `erapor`, `cbt`, `tka`, `setup`.
      Route patterns:
      - erapor: `admin.dashboard`, `admin.penilaian.*`, `admin.perkembangan.*`, `admin.transkrip.*`, `admin.cetak.*`, `admin.referensi.*`, `admin.profile`, `admin.sekolah.*`
      - cbt: `admin.cbt.*`, `admin.tryout.*`
      - tka: `admin.tka.*`
      - setup: `admin.users.*`, `admin.mapel.*`, `admin.semester.*`, `admin.kode-akses.*`, `admin.modul.*`, `admin.bobot.*`, `admin.backup.*`, `admin.log.*`, `admin.sheet.*`, `admin.dapodik.*`, `admin.peta-kelas.*`, `admin.kokurikuler.*`
      Return null if route not matched (fallback).
      Files: app/Helpers/AdminTabHelper.php
      Verify: Run `php artisan tinker` and test `\App\Helpers\AdminTabHelper::activeAdminTab('admin.cbt.banks.index')` returns `'cbt'`.

- [ ] 2. Register the helper in AppServiceProvider and create a Blade macro.
      In `app/Providers/AppServiceProvider.php` boot method, add a Blade macro `@activeAdminTab` that calls the helper and returns the active tab name. Include the helper with `composer require` or manual require in boot.
      Files: app/Providers/AppServiceProvider.php
      Verify: In a Blade view, test `{{ @activeAdminTab }}` outputs the correct tab name.

- [ ] 3. Create the tab bar component: `resources/views/components/admin-tabs.blade.php`.
      Component accepts `$tabs` array with keys (erapor, cbt, tka, setup) and labels. Renders 4 horizontal tab buttons using the `@activeAdminTab` macro. Active tab highlighted with gold underline or white text on navy background. Mobile-responsive (horizontal scroll on < 768px). Includes inline styles or references layout.css.
      Files: resources/views/components/admin-tabs.blade.php
      Verify: Visit any admin page and confirm tab bar renders below topbar with correct active tab highlighted.

- [ ] 4. Create five split sidebar partials in `resources/views/admin/partials/`:
      - `sidebar-erapor.blade.php`: UTAMA (Dasbor, Profile) + DATA REFERENSI (14 items: Sekolah, Guru, Siswa, Kelas, Mapel, Pembelajaran, Alignment, Semester, Ekstrakurikuler, Kelompok Mapel, Mapping Rapor, Logo/TTD, Tanggal Rapor, Foto Siswa) + STATUS PENILAIAN + PERKEMBANGAN NILAI + TRANSKRIP IJAZAH + CETAK NILAI
      - `sidebar-cbt.blade.php`: CBT & E-LEARNING (Sesi Ujian CBT, Bank Soal CBT, Dashboard Tryout, Paket Tryout, Sesi Tryout, Hasil Tryout)
      - `sidebar-tka.blade.php`: TKA (Dashboard TKA, Dimensi P5, Data Penilaian, Early Warning)
      - `sidebar-setup.blade.php`: PENGGUNA + DAPODIK (with cache logic for sync badges) + DATA KOKURIKULER + PENGATURAN + SYSTEM
      - `sidebar-default.blade.php`: Fallback rendering all groups from $menuAdmin (same as current sidebar.blade.php)
      
      Each partial must preserve the $menuAdmin structure, cache logic (Dapodik badges), and component usage (x-sidebar-submenu, x-sidebar-link). Include only the groups relevant to that tab.
      Files: 
        resources/views/admin/partials/sidebar-erapor.blade.php
        resources/views/admin/partials/sidebar-cbt.blade.php
        resources/views/admin/partials/sidebar-tka.blade.php
        resources/views/admin/partials/sidebar-setup.blade.php
        resources/views/admin/partials/sidebar-default.blade.php
      Verify: Check that each sidebar partial renders correctly with proper group structure and active item highlighting.

- [ ] 5. Create the new layout: `resources/views/layouts/admin-with-tabs.blade.php`.
      Copy `layouts/app.blade.php` and insert the admin-tabs component after the topbar (between `<header class="topbar">` and `<main class="page-content">`). The tab bar should use `@activeAdminTab` to detect the active tab and pass it to the component. The sidebar @yield section should conditionally render one of the 5 split sidebars based on the active tab:
      
      ```blade
      @php
          $activeTab = activeAdminTab();
      @endphp
      
      @section('sidebar')
          @if($activeTab === 'erapor')
              @include('admin.partials.sidebar-erapor')
          @elseif($activeTab === 'cbt')
              @include('admin.partials.sidebar-cbt')
          @elseif($activeTab === 'tka')
              @include('admin.partials.sidebar-tka')
          @elseif($activeTab === 'setup')
              @include('admin.partials.sidebar-setup')
          @else
              @include('admin.partials.sidebar-default')
          @endif
      @endsection
      ```
      
      All other elements (sidebar structure, topbar, footer) remain identical to app.blade.php.
      Files: resources/views/layouts/admin-with-tabs.blade.php
      Verify: View any admin page; confirm tab bar renders and sidebar switches content on tab click.

- [ ] 6. Add CSS for the tab bar to `resources/css/layout.css`.
      Append styles for `.admin-tabs` container (flex, gap, padding), `.admin-tab-button` (inactive: gray text, navy bg; active: white text, navy bg with gold underline or highlight). Mobile: horizontal scroll with snap alignment. Ensure alignment with existing --navy, --gold, --header-height variables. Position the tab bar as sticky below the topbar using z-index: 99 (below topbar's 100).
      Files: resources/css/layout.css
      Verify: Open browser DevTools, inspect .admin-tabs element, confirm styles apply and active tab highlights correctly at different screen sizes.

- [ ] 7. Create a route helper or middleware to inject $activeTab into views automatically.
      In `app/Providers/AppServiceProvider.php` boot method, use `View::share()` to make `$activeTab` available to all admin views. This allows views to check the current tab without calling the function manually in each template.
      Files: app/Providers/AppServiceProvider.php
      Verify: In any admin view, test `{{ $activeTab }}` outputs the correct tab name.

- [ ] 8. Update all admin views to use the new layout.
      Replace `@extends('layouts.app')` with `@extends('layouts.admin-with-tabs')` in all admin view files. Use grep and str_replace to batch update ~70 files in `resources/views/admin/`.
      Files: All .blade.php files under resources/views/admin/ (except partials and components)
      Verify: Run `grep -r "@extends('layouts.admin-with-tabs')" resources/views/admin/` and confirm count matches the number of admin views. Visit 2-3 admin pages from different tabs (e.g., Dasbor, CBT sessions, Setup) and confirm correct sidebar renders.

- [ ] 9. Test the implementation end-to-end.
      - Start dev server: `php artisan serve --host=127.0.0.1 --port=8000`
      - Navigate to admin dashboard (`/admin/dashboard`) and confirm tab bar renders with E-Rapor active
      - Click CBT tab and verify sidebar switches to CBT items, URL shows `?tab=cbt` (if query param used)
      - Navigate to a CBT page directly (`/admin/cbt/sessions`) and confirm CBT tab is auto-selected
      - Repeat for TKA and Setup tabs
      - Test mobile responsiveness (DevTools, < 768px): confirm tab bar is accessible
      - Verify all menu items render correctly (no broken links, icons display, active state works)
      - Confirm non-admin pages (guru, siswa, etc.) are unaffected
      Files: None (testing only)
      Verify: All navigation works smoothly, tab switching reflects in sidebar, no console errors.

- [ ] 10. Run unit and feature tests.
      If tests exist for admin routes or views, run them to ensure no regressions:
      `php artisan test` or `phpunit`.
      Verify: All tests pass, no failures related to layout or sidebar changes.

- [ ] 11. Commit the changes.
      Create a git commit with message: "feat: Add tab-based admin navigation (E-Rapor, CBT, TKA, Setup) with dynamic sidebar"
      Include all new files (helpers, components, partials, layout) and updates to existing files.
      Files: All from steps 1-8
      Verify: `git log --oneline -1` shows the commit message.

---

## Implementation Notes

- **URL State**: Use query parameter `?tab=<tabname>` to persist active tab across page navigation. JavaScript can set this when clicking tab buttons, but tab detection should work from route alone (fallback if no query param).
- **Fallback**: If a route doesn't match any tab pattern, render the default sidebar showing all groups. This handles edge cases and future routes.
- **Cache Preservation**: The Dapodik sync badge (showing "2 hours ago", "Belum sync") relies on Cache::remember() in the current sidebar. Replicate this logic in sidebar-setup.blade.php to preserve the feature.
- **No Breaking Changes**: The existing sidebar.blade.php remains unchanged. Non-admin roles (guru, siswa, etc.) continue using layouts/app with the original sidebar.
- **Mobile**: Tab bar on mobile should remain visible (not hidden by sidebar overlay) and allow horizontal scrolling if tabs overflow.
- **Performance**: Each split sidebar is smaller than the monolithic sidebar, potentially faster to render. Caching benefits unchanged.

---

## Risk Mitigation

- **Test Grep Results**: Before bulk replace in step 8, run a dry-run grep to count files and confirm no false positives.
- **Staging**: Update a few admin views first (dashboard, one CBT page, one Setup page) and manually test before batch updating.
- **Rollback**: If tab detection fails or sidebar doesn't render, revert to `@extends('layouts.app')` in affected views and troubleshoot helper function.
- **Cache Invalidation**: If caching sidebars, ensure cache key includes the active tab to avoid stale sidebar content.

