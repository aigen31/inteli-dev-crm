<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyYooKassaWebhook
{
    private const ALLOWED_IPS = [
        '185.71.76.0/27',
        '185.71.77.0/27',
        '77.75.153.0/25',
        '77.75.156.11',
        '77.75.156.35',
        '77.75.154.128/25',
        '2a02:5180::/32',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        if (! $this->isAllowedIp($ip)) {
            abort(403, 'Forbidden');
        }

        return $next($request);
    }

    private function isAllowedIp(?string $ip): bool
    {
        if (! $ip) {
            return false;
        }

        if (app()->environment('local', 'testing')) {
            return true;
        }

        foreach (self::ALLOWED_IPS as $range) {
            if (str_contains($range, '/')) {
                if ($this->ipInRange($ip, $range)) {
                    return true;
                }
            } elseif ($ip === $range) {
                return true;
            }
        }

        return false;
    }

    private function ipInRange(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return str_starts_with($cidr, '2a02:');
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $mask = -1 << (32 - (int) $bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
