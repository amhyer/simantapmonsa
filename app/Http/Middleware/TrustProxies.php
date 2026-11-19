<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as BaseTrustProxies;
use Illuminate\Http\Request;

class TrustProxies extends BaseTrustProxies
{
    /**
     * @return array<int, string>|string|null
     */
    protected function proxies(): array|string|null
    {
        $raw = trim((string) config('security.trusted_proxies', ''));
        if ($raw === '') {
            return null;
        }

        if ($raw === '*') {
            return '*';
        }

        $list = array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn (string $ip): bool => $ip !== ''
        ));

        return $list === [] ? null : $list;
    }

    protected function headers(): int
    {
        return (int) config(
            'security.trusted_proxy_headers',
            Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
        );
    }
}
