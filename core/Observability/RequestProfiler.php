<?php

namespace Screenart\Musedock\Observability;

/**
 * Instrumentación ligera de peticiones (M-003, bloque D).
 *
 * Mide, solo en una fracción de las peticiones (muestreo), cuánto tarda cada fase del arranque, cuántas
 * consultas SQL hace y cuánto suman, si la caché HTML acertó y la memoria máxima, y lo anota en
 * storage/logs/perf-AAAAMMDD.jsonl (una línea JSON por petición muestreada).
 *
 * Activación: variable PERF_SAMPLE_RATE en .env, en porcentaje (0 = apagado, que es el valor por defecto;
 * 1 = una de cada 100; 100 = todas). Con el muestreo apagado el coste por petición es una lectura de la
 * variable y una comparación.
 *
 * Privacidad: NO se registran URL, parámetros, cookies, cabeceras, IP ni User-Agent. Solo identificador del
 * tenant, método, código de estado y las medidas.
 *
 * Nunca debe romper una petición: todo va dentro de try/catch y la escritura es silenciosa.
 */
final class RequestProfiler
{
    /** Tamaño máximo del fichero diario (seguridad frente a un muestreo mal puesto). */
    private const MAX_FILE_BYTES = 50 * 1024 * 1024;

    private static bool $on = false;
    private static float $t0 = 0.0;          // inicio de la petición (segundos, reloj de pared)
    private static array $marks = [];        // nombre => milisegundos desde el inicio de la petición
    private static int $sqlCount = 0;
    private static int $sqlNs = 0;
    private static string $cache = 'skip';   // hit | miss | skip
    private static $tenantId = null;
    private static bool $attached = false;

    /** Llamar una vez, justo después de cargar el .env. */
    public static function start(): void
    {
        try {
            $rate = (float) \Screenart\Musedock\Env::get('PERF_SAMPLE_RATE', 0);
            if ($rate <= 0) {
                return;
            }
            if ($rate < 100 && (mt_rand() / mt_getrandmax()) * 100 >= $rate) {
                return;
            }
            self::$on = true;
            self::$t0 = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
            self::mark('start');
            register_shutdown_function([self::class, 'flush']);
        } catch (\Throwable $e) {
            self::$on = false;
        }
    }

    public static function enabled(): bool
    {
        return self::$on;
    }

    /** Anota el instante (ms desde el inicio de la petición) de un punto del arranque. */
    public static function mark(string $name): void
    {
        if (self::$on) {
            self::$marks[$name] = round((microtime(true) - self::$t0) * 1000, 2);
        }
    }

    /** Resultado de la caché HTML: hit | miss | skip. */
    public static function cache(string $result, $tenantId = null): void
    {
        if (self::$on) {
            self::$cache = $result;
            if ($tenantId !== null) {
                self::$tenantId = $tenantId;
            }
        }
    }

    /** Acumula una consulta SQL (nanosegundos). La llama ProfilingStatement. */
    public static function sql(int $ns): void
    {
        self::$sqlCount++;
        self::$sqlNs += $ns;
    }

    /** Engancha la medición de consultas a una conexión PDO (idempotente). */
    public static function attach(\PDO $pdo): void
    {
        if (!self::$on || self::$attached) {
            return;
        }
        try {
            $pdo->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [ProfilingStatement::class]);
            self::$attached = true;
        } catch (\Throwable $e) {
            // sin medición de SQL; el resto sigue funcionando
        }
    }

    /** Se ejecuta al terminar la petición (también tras un exit). */
    public static function flush(): void
    {
        try {
            if (!self::$on) {
                return;
            }
            self::$on = false;
            $total = round((microtime(true) - self::$t0) * 1000, 2);
            $m = self::$marks;
            $span = static fn(string $a, string $b): ?float => isset($m[$a], $m[$b]) ? round($m[$b] - $m[$a], 2) : null;

            $tenant = self::$tenantId;
            if ($tenant === null && isset($GLOBALS['tenant']['id'])) {
                $tenant = (int) $GLOBALS['tenant']['id'];
            }

            $line = json_encode([
                'ts' => gmdate('Y-m-d\TH:i:s\Z'),
                'tenant' => $tenant,
                'method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'status' => http_response_code() ?: null,
                'cache' => self::$cache,
                'ms_total' => $total,
                'ms_tenant' => $span('tenant_start', 'tenant_end'),
                'ms_modules' => $span('modules_start', 'modules_end'),
                'ms_plugins' => $span('modules_end', 'plugins_end'),
                'ms_to_routes' => $m['plugins_end'] ?? null,
                'sql_n' => self::$sqlCount,
                'sql_ms' => round(self::$sqlNs / 1e6, 2),
                'mem_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
            ], JSON_UNESCAPED_SLASHES);
            if ($line === false) {
                return;
            }

            $file = dirname(__DIR__, 2) . '/storage/logs/perf-' . gmdate('Ymd') . '.jsonl';
            if (is_file($file) && (int) @filesize($file) > self::MAX_FILE_BYTES) {
                return;
            }
            @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // la instrumentación nunca debe afectar a la petición
        }
    }
}

/**
 * Sentencia PDO que mide cuánto tarda cada execute(). Solo se usa cuando la petición está muestreada.
 */
final class ProfilingStatement extends \PDOStatement
{
    protected function __construct()
    {
    }

    public function execute(?array $params = null): bool
    {
        $t = hrtime(true);
        try {
            return parent::execute($params);
        } finally {
            RequestProfiler::sql(hrtime(true) - $t);
        }
    }
}
