<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>

## Koordinasi multi-agen (Opencode + Kiro AI)

- PHP WAJIB: `C:\Users\USER\.config\herd\bin\php84\php.exe` (jangan `php` polos — ext rusak). Serve: artisan serve --host=127.0.0.1 --port=8000.
- Test: sqlite in-memory (`DB_CONNECTION=sqlite`), jadi hindari SQL khusus-PostgreSQL (`ilike`, `to_char`) di query yang kena test.
- JANGAN cache model Eloquent (korup saat unserialize -> 500). Cache skalar/array saja.
- Tabel di Blade: pakai `.table-wrapper` (resources/css/layout.css).
- Pembagian area: Kiro = CBT (`app/**/Cbt/`, `Cbt*.php`, migrasi `*cbt*`), alignment e-Rapor (docs 09-12, `alignment*`), dasbor/feature peran guru-siswa-ortu. Opencode = Transkrip, Cetak, struktur sidebar, security, `TranskripTest`/`CetakTest`.
- File bersama (`routes/web.php`, `sidebar.blade.php`, `MenuAuditTest.php`, `layouts/app.blade.php`): baca dulu, edit seperlunya, wajib `phpunit` hijau sebelum commit. Commit kecil + pesan jelas.
