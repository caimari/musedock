<?php

namespace Screenart\Musedock\Services;

/**
 * Dirección canónica de los sitios: www.<dominio> → <dominio> con 301.
 *
 * Los tenants respondían igual con y sin www: para Google eran dos copias de cada
 * página (el canonical cambiaba según la dirección de entrada) y la caché HTML
 * guardaba dos copias. Se redirige, conservando la ruta y los parámetros, cuando el
 * dominio sin www es un tenant o un alias activo. Solo GET/HEAD: un formulario
 * enviado a la dirección con www no se pierde por una redirección.
 *
 * Se ejecuta antes de cargar el framework, con una consulta ligera que solo ocurre
 * si la dirección empieza por www. (el dominio principal ya lo redirige Caddy).
 */
class CanonicalHost
{
    public static function redirectWww(): void
    {
        $host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
        if (strncmp($host, 'www.', 4) !== 0 || !in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
            return;
        }
        $bare = substr($host, 4);
        if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $bare) || !self::isSiteDomain($bare)) {
            return;
        }

        header('Location: https://' . $bare . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        header('Cache-Control: public, max-age=86400');
        exit;
    }

    private static function isSiteDomain(string $domain): bool
    {
        try {
            $config = require dirname(__DIR__, 2) . '/config/config.php';
            $db = $config['db'] ?? [];
            $driver = strtolower((string) ($db['driver'] ?? 'mysql'));
            $port = !empty($db['port']) ? ';port=' . $db['port'] : '';
            $dsn = in_array($driver, ['pgsql', 'postgresql', 'postgres'], true)
                ? "pgsql:host={$db['host']};dbname={$db['name']}{$port}"
                : "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4{$port}";
            // Conexión compartida con el resto de la petición (Database::connect() la adopta)
            if (!(($GLOBALS['__musedock_pdo'] ?? null) instanceof \PDO)) {
                $GLOBALS['__musedock_pdo'] = new \PDO($dsn, (string) ($db['user'] ?? ''), (string) ($db['pass'] ?? ''), [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_TIMEOUT => 2,
                ]);
            }
            $pdo = $GLOBALS['__musedock_pdo'];
            $stmt = $pdo->prepare("SELECT 1 FROM tenants WHERE domain = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$domain]);
            if ($stmt->fetchColumn()) {
                return true;
            }
            try {
                $stmt = $pdo->prepare("SELECT 1 FROM domain_aliases WHERE domain = ? AND status = 'active' LIMIT 1");
                $stmt->execute([$domain]);

                return (bool) $stmt->fetchColumn();
            } catch (\Throwable $e) {
                return false; // tabla de alias opcional
            }
        } catch (\Throwable $e) {
            return false; // ante cualquier fallo, servir la página sin redirigir
        }
    }
}
