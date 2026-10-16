<?php

namespace Modules\SearchConsole\Services;

use PDO;

/**
 * «Descargar datos ahora» como cola en el servidor: una propiedad por paso, con barra de progreso, parar/reanudar y
 * sin una petición HTTP gigante (descargar 30 dominios de golpe agotaba el tiempo y daba «Error de red»).
 *
 * Cuida al servidor: solo procesa si la carga de CPU lo permite, deja una pausa entre propiedades, baja su prioridad
 * y limita la memoria. Si se cierra la página, la continúa el cron (cli/cron-search-console.php).
 */
class SyncQueueService
{
    /** Pausa mínima entre dos propiedades (segundos). */
    public const MIN_GAP = 2;
    /** Con más carga que núcleos × este factor, se espera. */
    public const LOAD_FACTOR = 1.0;
    public const STALE_AFTER = 90;

    private string $file;
    /** @var callable():float */
    private $load;

    public function __construct(private PDO $pdo, ?string $file = null, ?callable $load = null)
    {
        $this->file = $file ?? (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 3)) . '/storage/cache/search-console-sync.json';
        $this->load = $load ?? static fn () => (float) (sys_getloadavg()[0] ?? 0.0);
    }

    public static function cores(): int
    {
        static $n = null;
        if ($n === null) {
            $n = max(1, substr_count((string) @file_get_contents('/proc/cpuinfo'), "processor\t:") ?: 1);
        }

        return $n;
    }

    /** ¿Está el servidor demasiado cargado para descargar ahora? */
    public function loadHigh(): bool
    {
        return ($this->load)() > self::cores() * self::LOAD_FACTOR;
    }

    public function state(): array
    {
        $raw = is_file($this->file) ? json_decode((string) file_get_contents($this->file), true) : null;

        return is_array($raw) ? $raw : ['status' => 'idle', 'items' => [], 'updated_at' => 0, 'last_step_at' => 0];
    }

    private function save(array $s): void
    {
        $s['updated_at'] = time();
        file_put_contents($this->file, json_encode($s, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /** @param array<int,array> $properties propiedades activas visibles (filas de gsc_properties) */
    public function start(array $properties): array
    {
        $rows = array_values(array_filter($properties, fn ($p) => ($p['status'] ?? '') === 'active'));
        // Primero las que nunca se han descargado, luego las más antiguas
        usort($rows, fn ($a, $b) => [empty($a['last_sync_at']) ? 0 : 1, (string) ($a['last_sync_at'] ?? '')] <=> [empty($b['last_sync_at']) ? 0 : 1, (string) ($b['last_sync_at'] ?? '')]);
        $items = [];
        foreach ($rows as $p) {
            $items[(int) $p['id']] = ['id' => (int) $p['id'], 'domain' => $p['domain'], 'state' => 'queued', 'rows' => 0, 'message' => '', 'secs' => 0];
        }
        $s = ['status' => $items ? 'running' : 'done', 'started_at' => time(), 'items' => $items, 'current' => null, 'last_step_at' => 0];
        $this->save($s);

        return $this->view($s);
    }

    public function stop(): array
    {
        $s = $this->state();
        if ($s['status'] === 'running') {
            $s['status'] = 'stopped';
            $s['current'] = null;
            $this->save($s);
        }

        return $this->view($s);
    }

    public function resume(): array
    {
        $s = $this->state();
        if ($s['status'] === 'stopped') {
            $s['status'] = 'running';
            $this->save($s);
        }

        return $this->view($s);
    }

    public function isStale(): bool
    {
        $s = $this->state();

        return $s['status'] === 'running' && time() - (int) $s['updated_at'] > self::STALE_AFTER;
    }

    /** Procesa como mucho una propiedad. */
    public function step(CredentialStore $store, SyncService $sync): array
    {
        $lock = fopen($this->file . '.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return $this->view($this->state(), 'busy');
        }
        try {
            $s = $this->state();
            if ($s['status'] !== 'running') {
                return $this->view($s);
            }
            $next = null;
            foreach ($s['items'] as $id => $it) {
                if ($it['state'] === 'queued') {
                    $next = $id;
                    break;
                }
            }
            if ($next === null) {
                $s['status'] = 'done';
                $s['current'] = null;
                $this->save($s);

                return $this->view($s);
            }
            if ($this->loadHigh()) {
                return $this->view($s, 'load'); // el servidor está ocupado: se espera, no se añade carga
            }
            if (time() - (int) ($s['last_step_at'] ?? 0) < self::MIN_GAP) {
                return $this->view($s, 'gap');
            }

            // Prioridad baja y memoria acotada para esta descarga
            if (function_exists('proc_nice')) {
                @proc_nice(15);
            }
            @ini_set('memory_limit', '256M');

            $s['current'] = $next;
            $s['items'][$next]['state'] = 'working';
            $this->save($s);

            $t = microtime(true);
            $stmt = $this->pdo->prepare('SELECT * FROM gsc_properties WHERE id = ?');
            $stmt->execute([(int) $next]);
            $prop = $stmt->fetch(PDO::FETCH_ASSOC);
            $item = &$s['items'][$next];
            try {
                $cred = $prop ? $store->findById((int) $prop['credential_id']) : null;
                if (!$prop || !$cred || $prop['status'] !== 'active') {
                    $item['state'] = 'error';
                    $item['message'] = 'Propiedad no disponible.';
                } else {
                    $r = $sync->syncProperty($prop, $store->client($cred));
                    $item['rows'] = (int) $r['rows'];
                    $item['state'] = $r['status'] === 'ok' ? 'ok' : 'error';
                    $item['message'] = $r['status'] === 'ok' ? '' : mb_substr((string) $r['error'], 0, 200);
                }
            } catch (\Throwable $e) {
                $item['state'] = 'error';
                $item['message'] = mb_substr($e->getMessage(), 0, 200);
            }
            $item['secs'] = round(microtime(true) - $t, 1);
            unset($item);
            $s['current'] = null;
            $s['last_step_at'] = time();
            if (!array_filter($s['items'], fn ($i) => $i['state'] === 'queued')) {
                $s['status'] = 'done';
            }
            $this->save($s);

            return $this->view($s);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @param string|null $wait null | busy (otro proceso) | load (servidor cargado) | gap (pausa entre propiedades) */
    public function view(array $s, ?string $wait = null): array
    {
        $counts = ['queued' => 0, 'working' => 0, 'ok' => 0, 'error' => 0];
        foreach ($s['items'] as $i) {
            $counts[$i['state']] = ($counts[$i['state']] ?? 0) + 1;
        }

        return [
            'status' => $s['status'],
            'total' => count($s['items']),
            'finished' => count($s['items']) - $counts['queued'] - $counts['working'],
            'counts' => $counts,
            'current' => $s['current'] ?? null,
            'wait' => $wait,
            'load' => round(($this->load)(), 1),
            'cores' => self::cores(),
            'rows' => array_sum(array_column($s['items'], 'rows')),
            'items' => array_values(array_map(fn ($i) => ['id' => $i['id'], 'domain' => $i['domain'], 'state' => $i['state'], 'rows' => $i['rows'], 'message' => $i['message'], 'secs' => $i['secs']], $s['items'])),
        ];
    }
}
