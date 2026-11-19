<?php

use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\CspReportOnlyHeaders;
use App\Http\Middleware\EnsureCbtEnabled;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\EnsureSchoolContext;
use App\Http\Middleware\EnsureSingleSchoolDapodik;
use App\Http\Middleware\EnsureSingleSchoolData;
use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\RecordMenuUsage;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
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
        // Header keamanan pada setiap respons (nilai di config/security.php).
        $middleware->append(SecurityHeaders::class);

        // Isolasi data multi-tenant (aktif saat user memiliki tenant_id atau mode SaaS diaktifkan).
        $middleware->web(remove: [SubstituteBindings::class], append: [
            IdentifyTenant::class,
            SubstituteBindings::class,
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
            'module.asesmen' => EnsureModuleEnabled::class,
            'cbt.enabled' => EnsureCbtEnabled::class,
            'tenant' => IdentifyTenant::class,
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
    })->create();
