<?php

use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\CspReportOnlyHeaders;
use App\Http\Middleware\EnforceTenantQuota;
use App\Http\Middleware\EnsureCbtEnabled;
use App\Http\Middleware\EnsureMfaSetup;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\EnsureSchoolContext;
use App\Http\Middleware\EnsureSingleSchoolDapodik;
use App\Http\Middleware\EnsureSingleSchoolData;
use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\RecordMenuUsage;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrustHosts;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Ganti TrustProxies bawaan agar membaca daftar IP/CIDR dari config('security.trusted_proxies')
        $middleware->replace(
            TrustProxies::class,
            App\Http\Middleware\TrustProxies::class,
        );

        // Tolak Host header tak dikenal (anti Host Header Injection pada reset sandi & verifikasi email).
        $middleware->append(TrustHosts::class);

        // Header keamanan pada setiap respons (nilai di config/security.php).
        $middleware->append(SecurityHeaders::class);

        // Isolasi data multi-tenant (aktif saat user memiliki tenant_id atau mode SaaS diaktifkan).
        $middleware->web(remove: [SubstituteBindings::class], append: [
            IdentifyTenant::class,
            SubstituteBindings::class,
            EnforceTenantQuota::class,
            CspReportOnlyHeaders::class,
        ]);

        // Pengecualian CSRF HANYA untuk endpoint pelaporan CSP browser.
        $middleware->validateCsrfTokens(except: [
            'csp-report',
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'apikey' => ApiKeyMiddleware::class,
            'force.password.change' => ForcePasswordChange::class,
            'mfa.setup' => EnsureMfaSetup::class,
            'module.asesmen' => EnsureModuleEnabled::class,
            'cbt.enabled' => EnsureCbtEnabled::class,
            'tenant' => IdentifyTenant::class,
            'tenant.quota' => EnforceTenantQuota::class,
            'dapodik.single-school' => EnsureSingleSchoolDapodik::class,
            'school.context' => EnsureSchoolContext::class,
            'data.single-school' => EnsureSingleSchoolData::class,
            'menu.telemetry' => RecordMenuUsage::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            $postMax = (string) ini_get('post_max_size');
            $uploadMax = (string) ini_get('upload_max_filesize');
            $pesan = "Ukuran berkas yang diunggah melebihi batas server saat ini (post_max_size={$postMax}, upload_max_filesize={$uploadMax}). Kecilkan berkas atau naikkan batas unggah PHP.";

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $pesan], 413);
            }

            $target = url()->previous();
            if (! $target || $target === $request->fullUrl()) {
                $target = route('admin.backup.index');
            }

            return redirect($target)->with('error', $pesan);
        });
    })->create();
