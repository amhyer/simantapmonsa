<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Header keamanan pada setiap respons (nilai di config/security.php).
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Isolasi data multi-tenant (aktif saat user memiliki tenant_id atau mode SaaS diaktifkan).
        $middleware->web(remove: [\Illuminate\Routing\Middleware\SubstituteBindings::class], append: [
            \App\Http\Middleware\IdentifyTenant::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'apikey' => \App\Http\Middleware\ApiKeyMiddleware::class,
            'force.password.change' => \App\Http\Middleware\ForcePasswordChange::class,
            'module.asesmen' => \App\Http\Middleware\EnsureModuleEnabled::class,
            'tenant' => \App\Http\Middleware\IdentifyTenant::class,
            'dapodik.single-school' => \App\Http\Middleware\EnsureSingleSchoolDapodik::class,
            'school.context' => \App\Http\Middleware\EnsureSchoolContext::class,
            'data.single-school' => \App\Http\Middleware\EnsureSingleSchoolData::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
