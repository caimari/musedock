<?php

namespace Screenart\Musedock\Security;

/**
 * Registro de eventos de seguridad para fail2ban (jaulas musedock-cms-* del panel).
 *
 * Una línea por evento, con formato fijo y sin datos personales (ni emails ni
 * contraseñas):
 *
 *   2026-10-16T23:10:00+00:00 musedock-cms event=login_failed ip=203.0.113.7 host=musedock.com path=/musedock/login
 *
 * Eventos: login_failed (superadmin, admin de tenant, cliente), twofa_failed,
 * probe (rutas que solo piden los escáneres: /.env, /.git, *.php sueltos...).
 *
 * Nunca se registra una IP de Cloudflare, privada o de loopback: banearla dejaría
 * fuera a muchos visitantes legítimos a la vez. La IP sale de IPHelper, que solo se
 * fía de las cabeceras de proxy cuando la conexión viene de Cloudflare o de un
 * proxy de confianza.
 */
class SecurityEventLog
{
    public const EVENTS = ['login_failed', 'twofa_failed', 'probe'];

    public static function log(string $event, ?string $ip = null): void
    {
        if (!in_array($event, self::EVENTS, true) || self::disabled()) {
            return;
        }
        try {
            if (!class_exists(IPHelper::class, false)) {
                require_once __DIR__ . '/IPHelper.php';
            }
            $ip = $ip ?? IPHelper::getRealIP();
            if (!self::isBannable($ip)) {
                return;
            }
            $line = sprintf(
                "%s musedock-cms event=%s ip=%s host=%s path=%s\n",
                gmdate('Y-m-d\TH:i:s+00:00'),
                $event,
                $ip,
                self::clean((string) ($_SERVER['HTTP_HOST'] ?? '-'), 100),
                self::clean((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), 200)
            );
            @file_put_contents(self::path(), $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // El registro de seguridad nunca debe romper la petición
        }
    }

    public static function path(): string
    {
        $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);

        return rtrim($root, '/') . '/storage/logs/security-events.log';
    }

    /** IP pública real y fuera de Cloudflare: la única que tiene sentido bloquear. */
    public static function isBannable(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        $cloudflare = [
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
            '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
            '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
            '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
            '2a06:98c0::/29', '2c0f:f248::/32',
        ];

        return !IPHelper::ipInRanges($ip, $cloudflare);
    }

    /** Sin espacios ni saltos de línea: una línea por evento y nada que confunda al filtro. */
    private static function clean(string $value, int $max): string
    {
        $value = preg_replace('/[^\x21-\x7E]/', '', $value) ?? '';

        return $value === '' ? '-' : substr($value, 0, $max);
    }

    private static function disabled(): bool
    {
        $flag = getenv('SECURITY_EVENT_LOG');
        if ($flag === false && isset($_ENV['SECURITY_EVENT_LOG'])) {
            $flag = $_ENV['SECURITY_EVENT_LOG'];
        }

        return $flag !== false && !filter_var($flag, FILTER_VALIDATE_BOOLEAN);
    }
}
