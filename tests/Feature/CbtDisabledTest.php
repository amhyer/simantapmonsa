<?php

namespace Tests\Feature;

use Tests\TestCase;

class CbtDisabledTest extends TestCase
{
    public function test_flag_nonaktif_tidak_mendaftarkan_endpoint_cbt(): void
    {
        $old = [getenv('SIMANTAP_CBT_ENABLED'), $_ENV['SIMANTAP_CBT_ENABLED'] ?? null, $_SERVER['SIMANTAP_CBT_ENABLED'] ?? null];
        try {
            putenv('SIMANTAP_CBT_ENABLED=false');
            $_ENV['SIMANTAP_CBT_ENABLED'] = $_SERVER['SIMANTAP_CBT_ENABLED'] = 'false';
            \Illuminate\Support\Env::enablePutenv();
            $this->refreshApplication();
            $this->assertFalse(config('simantap.cbt_enabled'));
            $this->assertFalse(app('router')->has('cbt.student.index'));
            $this->get('/cbt/student')->assertNotFound();
        } finally {
            [$env, $arrayEnv, $server] = $old;
            $env === false ? putenv('SIMANTAP_CBT_ENABLED') : putenv('SIMANTAP_CBT_ENABLED='.$env);
            if ($arrayEnv === null) { unset($_ENV['SIMANTAP_CBT_ENABLED']); } else { $_ENV['SIMANTAP_CBT_ENABLED'] = $arrayEnv; }
            if ($server === null) { unset($_SERVER['SIMANTAP_CBT_ENABLED']); } else { $_SERVER['SIMANTAP_CBT_ENABLED'] = $server; }
            \Illuminate\Support\Env::enablePutenv();
        }
    }
}
