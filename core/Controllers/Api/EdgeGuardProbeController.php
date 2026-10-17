<?php

namespace Screenart\Musedock\Controllers\Api;

use Screenart\Musedock\Services\EdgeGuard\EdgeGuard;

/**
 * API de las sondas del escudo ante bloqueos de Cloudflare (sin sesión ni CSRF; Bearer de sonda).
 *   GET  /api/v1/edge-guard/targets  → texto plano, una línea por objetivo: «id ip host»
 *   POST /api/v1/edge-guard/report   → JSON {paths:[{path, public_ip, control_ok, results:[{t, ok, code, ms}]}]}
 * Una sonda solo puede pedir la lista y entregar resultados: no ve ni toca nada más.
 */
class EdgeGuardProbeController
{
    public static function targets(): void
    {
        $guard = new EdgeGuard();
        if (self::probe($guard) === null) {
            return;
        }
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        foreach ($guard->targets() as $t) {
            echo $t['id'], ' ', $t['ip'], ' ', $t['host'], "\n";
        }
    }

    public static function report(): void
    {
        $guard = new EdgeGuard();
        $probe = self::probe($guard);
        if ($probe === null) {
            return;
        }
        $raw = (string) file_get_contents('php://input', false, null, 0, 512 * 1024);
        $data = json_decode($raw, true);
        if (!is_array($data) || !is_array($data['paths'] ?? null)) {
            self::json(400, ['ok' => false, 'error' => 'JSON no válido']);

            return;
        }
        // Una entrega cada 20 s como mucho por sonda
        $last = (int) $guard->setting('last_report_' . $probe, '0');
        if (time() - $last < 20) {
            self::json(429, ['ok' => false, 'error' => 'Demasiado seguido']);

            return;
        }
        $guard->setSetting('last_report_' . $probe, (string) time());
        $n = $guard->ingest($probe, $data['paths'], is_array($data['dns'] ?? null) ? $data['dns'] : []);
        self::json(200, ['ok' => true, 'stored' => $n]);
    }

    private static function probe(EdgeGuard $guard): ?string
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        $token = preg_match('/^Bearer\s+(egp_[a-f0-9]{48})$/i', trim($auth), $m) ? $m[1] : '';
        $probe = $token !== '' ? $guard->probeForToken($token) : null;
        if ($probe === null) {
            self::json(401, ['ok' => false, 'error' => 'Token de sonda no válido']);
        }

        return $probe;
    }

    private static function json(int $code, array $payload): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
