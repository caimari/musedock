<?php

namespace Screenart\Musedock\Services\EdgeGuard;

use PDO;
use Screenart\Musedock\Database;
use Screenart\Musedock\Services\Dns\CloudflareClient;
use Screenart\Musedock\Services\DnsAccounts;

/**
 * Escudo ante bloqueos de Cloudflare.
 *
 * Unas sondas en conexiones reales (p. ej. una máquina en España con dos operadoras) prueban cada minuto si
 * llegan a las IPs de Cloudflare que usan los dominios de este CMS y, si hace falta, directas al servidor de
 * origen. Cuando una IP de Cloudflare deja de responder desde alguna operadora (bloqueos de LaLiga, caídas de
 * Cloudflare…) y el origen sí responde con un certificado válido, se quita el proxy (nube naranja) de los
 * registros afectados. Cuando la IP lleva un rato respondiendo bien, se vuelve a poner.
 *
 * Reglas que no se rompen:
 *  - Solo se toca lo que tenía el proxy puesto en el momento del bloqueo (se guarda la foto del registro).
 *    Un registro que ya estaba sin proxy nunca entra en el ciclo, así que nunca se le pone.
 *  - Al volver, solo se reactiva si el registro sigue exactamente como lo dejó el sistema. Si alguien lo cambió
 *    entretanto, se respeta su cambio y se avisa.
 *  - Sin informes de sondas recientes no se cambia nada (ante la duda, quieto).
 *  - En modo «simulación» no se toca Cloudflare: solo se anota qué se habría hecho.
 *  - Las sondas solo informan; las claves de Cloudflare nunca salen de este servidor.
 */
class EdgeGuard
{
    public const MODES = ['off', 'simulate', 'auto'];
    private const PROBE_STALE = 300;      // s sin informes para dar una sonda por muda
    private const FAIL_WINDOW = 600;      // s: ventana para buscar fallos seguidos
    private const FAILS_TO_BLOCK = 2;     // informes seguidos fallando para dar una IP por bloqueada
    private const ORIGIN_FRESH = 420;     // s: antigüedad máxima de la prueba directa al origen
    private const INVENTORY_EVERY = 1800; // s entre lecturas de Cloudflare

    private PDO $pdo;
    private string $driver;
    private static bool $ready = false;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connect();
        $this->driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $this->ensureTables();
    }

    // ------------------------------------------------------------------ esquema y ajustes

    private function ensureTables(): void
    {
        if (self::$ready) {
            return;
        }
        $pk = $this->driver === 'pgsql' ? 'SERIAL PRIMARY KEY' : ($this->driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY');
        $small = $this->driver === 'pgsql' ? 'SMALLINT' : ($this->driver === 'sqlite' ? 'INTEGER' : 'TINYINT(1)');
        $ts = $this->driver === 'mysql' ? 'DATETIME' : 'TIMESTAMP';
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS edge_guard_settings (k VARCHAR(80) PRIMARY KEY, v TEXT NULL)');
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS edge_guard_records (
            id $pk, account_id INT NOT NULL, zone_id VARCHAR(64) NOT NULL, zone_name VARCHAR(255) NOT NULL,
            record_id VARCHAR(64) NOT NULL UNIQUE, name VARCHAR(255) NOT NULL, type VARCHAR(10) NOT NULL,
            content VARCHAR(255) NOT NULL, proxied $small NOT NULL DEFAULT 0, ttl INT NOT NULL DEFAULT 1,
            edge_ips TEXT NULL, origin_ip VARCHAR(64) NULL, refreshed_at $ts NOT NULL)");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS edge_guard_observations (
            id $pk, probe VARCHAR(60) NOT NULL, path VARCHAR(40) NOT NULL, target VARCHAR(120) NOT NULL,
            ok $small NOT NULL, code VARCHAR(10) NULL, ms INT NULL, public_ip VARCHAR(64) NULL, created_at $ts NOT NULL)");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS edge_guard_changes (
            id $pk, account_id INT NOT NULL, zone_id VARCHAR(64) NOT NULL, zone_name VARCHAR(255) NOT NULL,
            record_id VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(10) NOT NULL, content VARCHAR(255) NOT NULL,
            original_ttl INT NOT NULL DEFAULT 1, edge_ips TEXT NOT NULL, simulated $small NOT NULL DEFAULT 0,
            cause VARCHAR(40) NOT NULL, status VARCHAR(20) NOT NULL, changed_at $ts NOT NULL, restored_at $ts NULL, note TEXT NULL)");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS edge_guard_events (id $pk, level VARCHAR(10) NOT NULL, message TEXT NOT NULL, created_at $ts NOT NULL)");
        try {
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_edge_guard_obs ON edge_guard_observations (target, created_at)');
        } catch (\Throwable $e) {
            // MySQL sin IF NOT EXISTS en índices: la tabla funciona igual
        }
        // IPs de Cloudflare que ven las sondas en su país (Cloudflare responde distinto según desde dónde se pregunte)
        try {
            $this->pdo->exec($this->driver === 'pgsql'
                ? 'ALTER TABLE edge_guard_records ADD COLUMN IF NOT EXISTS edge_ips_probe TEXT NULL'
                : 'ALTER TABLE edge_guard_records ADD COLUMN edge_ips_probe TEXT NULL');
        } catch (\Throwable $e) {
            // ya existe
        }
        self::$ready = true;
    }

    public function setting(string $key, ?string $default = null): ?string
    {
        $stmt = $this->pdo->prepare('SELECT v FROM edge_guard_settings WHERE k = ?');
        $stmt->execute([$key]);
        $v = $stmt->fetchColumn();

        return $v === false ? $default : $v;
    }

    public function setSetting(string $key, ?string $value): void
    {
        $this->pdo->prepare('DELETE FROM edge_guard_settings WHERE k = ?')->execute([$key]);
        if ($value !== null) {
            $this->pdo->prepare('INSERT INTO edge_guard_settings (k, v) VALUES (?, ?)')->execute([$key, $value]);
        }
    }

    public function mode(): string
    {
        $m = (string) $this->setting('mode', 'off');

        return in_array($m, self::MODES, true) ? $m : 'off';
    }

    public function restoreMinutes(): int
    {
        return max(10, min(240, (int) $this->setting('restore_minutes', '30')));
    }

    /** Dominios que nunca entran en el ciclo (uno por línea; vale el dominio y todo lo que cuelga de él). */
    public function excluded(): array
    {
        return array_values(array_filter(array_map(fn ($l) => strtolower(trim($l, " \t.")), preg_split('/[\r\n,]+/', (string) $this->setting('excluded', '')))));
    }

    public function isExcluded(string $name): bool
    {
        foreach ($this->excluded() as $ex) {
            if ($name === $ex || str_ends_with($name, '.' . $ex)) {
                return true;
            }
        }

        return false;
    }

    public function event(string $level, string $message): void
    {
        $this->pdo->prepare('INSERT INTO edge_guard_events (level, message, created_at) VALUES (?, ?, ?)')->execute([$level, $message, self::now()]);
    }

    /** Fecha UTC (la base de datos guarda todo en UTC). */
    public static function now(int $offset = 0): string
    {
        return gmdate('Y-m-d H:i:s', time() + $offset);
    }

    /** Zona horaria para mostrar las horas (la del país vigilado; por defecto, España peninsular). */
    public function displayTimezone(): string
    {
        $tz = (string) $this->setting('display_tz', 'Europe/Madrid');

        return in_array($tz, \DateTimeZone::listIdentifiers(), true) ? $tz : 'Europe/Madrid';
    }

    /** «17/10 16:27 hora local (14:27 UTC)» a partir de una fecha UTC de la base de datos. */
    public function fmt(?string $utc, bool $withDate = true): string
    {
        if (!$utc) {
            return '—';
        }
        $d = new \DateTime($utc, new \DateTimeZone('UTC'));
        $u = $d->format('H:i');
        $d->setTimezone(new \DateTimeZone($this->displayTimezone()));

        return $d->format($withDate ? 'd/m H:i' : 'H:i') . ' (' . $u . ' UTC)';
    }

    /** Desde cuándo falla una IP sin interrupción (primer fallo tras su última respuesta buena). */
    public function blockedSince(string $ip): ?string
    {
        // Por operadora: con una ya respondiendo y otra no, mirarlas juntas daría la hora de la última
        // respuesta de la que funciona. Para cada camino que falla ahora, primer fallo tras su última respuesta buena.
        $t = 'cf:' . $ip;
        $since = null;
        $paths = $this->pdo->prepare('SELECT DISTINCT path FROM edge_guard_observations WHERE target = ?');
        $paths->execute([$t]);
        foreach ($paths->fetchAll(PDO::FETCH_COLUMN) as $path) {
            $stmt = $this->pdo->prepare('SELECT MAX(created_at) FROM edge_guard_observations WHERE target = ? AND path = ? AND ok = 1');
            $stmt->execute([$t, $path]);
            $lastOk = $stmt->fetchColumn() ?: '1970-01-01 00:00:00';
            $stmt = $this->pdo->prepare('SELECT MIN(created_at) FROM edge_guard_observations WHERE target = ? AND path = ? AND ok = 0 AND created_at > ?');
            $stmt->execute([$t, $path, $lastOk]);
            $first = $stmt->fetchColumn();
            if ($first && ($since === null || $first < $since)) {
                $since = $first;
            }
        }

        return $since ?: null;
    }

    /**
     * Anota el principio y el final de cada bloqueo de IP (con su duración) en el registro.
     * Se llama en cada vuelta; usa el ajuste «blocked_ips» para recordar qué estaba bloqueado.
     */
    private function trackEpisodes(array $states, array &$report): void
    {
        $open = json_decode((string) $this->setting('blocked_ips', '{}'), true) ?: [];
        $changed = false;
        foreach ($states as $ip => $s) {
            if ($s['state'] === 'blocked' && !isset($open[$ip])) {
                $since = $this->blockedSince($ip) ?: self::now();
                $open[$ip] = $since;
                $changed = true;
                $msg = "IP {$ip} bloqueada desde las " . $this->fmt($since) . '.';
                $this->event('warn', $msg);
                $report['notes'][] = $msg;
            } elseif (isset($open[$ip]) && in_array($s['state'], ['clear', 'recovering'], true) && !in_array(false, $s['paths'], true)) {
                // Primera respuesta buena desde todas las sondas: fin del bloqueo
                $stmt = $this->pdo->prepare('SELECT MIN(created_at) FROM edge_guard_observations WHERE target = ? AND ok = 1 AND created_at > ?');
                $stmt->execute(['cf:' . $ip, $open[$ip]]);
                $end = $stmt->fetchColumn() ?: self::now();
                $mins = (int) round((strtotime($end . ' UTC') - strtotime($open[$ip] . ' UTC')) / 60);
                $dur = $mins >= 60 ? intdiv($mins, 60) . ' h ' . ($mins % 60) . ' min' : $mins . ' min';
                $msg = "IP {$ip} vuelve a responder a las " . $this->fmt($end) . ". Bloqueada desde las " . $this->fmt($open[$ip]) . " ({$dur}).";
                $this->event('info', $msg);
                $report['notes'][] = $msg;
                unset($open[$ip]);
                $changed = true;
            }
        }
        if ($changed) {
            $this->setSetting('blocked_ips', $open ? json_encode($open) : null);
        }
    }

    /** IPs con un bloqueo abierto y desde cuándo (UTC). */
    public function openEpisodes(): array
    {
        return json_decode((string) $this->setting('blocked_ips', '{}'), true) ?: [];
    }

    // ------------------------------------------------------------------ sondas (tokens)

    /** @return array<int, array{name:string, hash:string, created_at:string}> */
    public function probes(): array
    {
        $list = json_decode((string) $this->setting('probes', '[]'), true);

        return is_array($list) ? $list : [];
    }

    /** Crea (o renueva) una sonda y devuelve su token: solo se ve esta vez, se guarda el hash. */
    public function createProbe(string $name): string
    {
        $name = substr(preg_replace('/[^a-z0-9._-]/', '', strtolower($name)) ?: 'sonda', 0, 40);
        $token = 'egp_' . bin2hex(random_bytes(24));
        $list = array_values(array_filter($this->probes(), fn ($p) => $p['name'] !== $name));
        $list[] = ['name' => $name, 'hash' => hash('sha256', $token), 'created_at' => self::now()];
        $this->setSetting('probes', json_encode($list));
        $this->event('info', "Sonda «{$name}» creada.");

        return $token;
    }

    public function revokeProbe(string $name): void
    {
        $this->setSetting('probes', json_encode(array_values(array_filter($this->probes(), fn ($p) => $p['name'] !== $name))));
        $this->event('info', "Sonda «{$name}» revocada.");
    }

    public function probeForToken(string $token): ?string
    {
        $hash = hash('sha256', $token);
        foreach ($this->probes() as $p) {
            if (hash_equals((string) $p['hash'], $hash)) {
                return (string) $p['name'];
            }
        }

        return null;
    }

    // ------------------------------------------------------------------ inventario

    /** Nombres que sirve este CMS (dominios de tenants, alias y redirecciones, con www si procede). */
    public function cmsHostnames(): array
    {
        $hosts = [];
        $add = function (?string $domain, $www) use (&$hosts) {
            $d = strtolower(trim((string) $domain, " .\t"));
            if ($d === '' || !str_contains($d, '.')) {
                return;
            }
            $hosts[$d] = true;
            if ((int) $www === 1 && !str_starts_with($d, 'www.')) {
                $hosts['www.' . $d] = true;
            }
        };
        foreach ([
            'SELECT domain, include_www FROM tenants',
            'SELECT domain, include_www FROM domain_aliases',
            'SELECT domain, include_www FROM domain_redirects',
        ] as $sql) {
            try {
                foreach ($this->pdo->query($sql)->fetchAll(PDO::FETCH_NUM) as [$d, $w]) {
                    $add($d, $w);
                }
            } catch (\Throwable $e) {
                // tabla o columna inexistente en esta instalación
            }
        }
        // El propio sitio principal
        $main = parse_url((string) \Screenart\Musedock\Env::get('APP_URL', ''), PHP_URL_HOST);
        if ($main) {
            $add($main, str_starts_with($main, 'www.') ? 0 : 1);
        }

        return array_keys($hosts);
    }

    /** Lee de Cloudflare los registros de los dominios del CMS. @return array{records:int, proxied:int, errors:string[]} */
    public function refreshInventory(): array
    {
        $hosts = array_flip($this->cmsHostnames());
        $seen = [];
        $errors = [];
        $count = 0;
        $proxied = 0;
        foreach (DnsAccounts::make()->cloudflareAccounts(true) as $acc) {
            $client = new CloudflareClient((string) $acc['api_token_decrypted'], $acc['account_id'] ?: null);
            try {
                $zones = $client->listZones();
            } catch (\Throwable $e) {
                $errors[] = "{$acc['name']}: " . $e->getMessage();
                continue;
            }
            foreach ($zones as $zone) {
                $relevant = false;
                foreach ($hosts as $h => $_) {
                    if ($h === $zone['name'] || str_ends_with($h, '.' . $zone['name'])) {
                        $relevant = true;
                        break;
                    }
                }
                if (!$relevant || isset($seen['zone:' . $zone['id']])) {
                    continue;
                }
                $seen['zone:' . $zone['id']] = true;
                try {
                    $records = $client->listRecords($zone['id']);
                } catch (\Throwable $e) {
                    $errors[] = "{$zone['name']}: " . $e->getMessage();
                    continue;
                }
                foreach ($records as $r) {
                    if (!isset($hosts[$r['name']]) || $r['type'] === 'AAAA') {
                        continue;
                    }
                    $seen[$r['id']] = true;
                    $count++;
                    $proxied += $r['proxied'] ? 1 : 0;
                    $this->upsertRecord((int) $acc['id'], $zone, $r);
                }
            }
        }
        // CNAME a otro nombre de la lista (p. ej. www → raíz con proxy): el DNS daría las IPs de Cloudflare,
        // así que el origen real es el del registro al que apunta (se sigue la cadena, hasta 5 saltos)
        $byName = [];
        foreach ($this->records() as $rec) {
            $byName[$rec['name']] = $rec;
        }
        foreach ($byName as $rec) {
            if ($rec['type'] !== 'CNAME') {
                continue;
            }
            $cur = $byName[strtolower(rtrim($rec['content'], '.'))] ?? null;
            for ($hop = 0; $cur && $hop < 5 && $cur['type'] === 'CNAME' && isset($byName[strtolower(rtrim($cur['content'], '.'))]); $hop++) {
                $cur = $byName[strtolower(rtrim($cur['content'], '.'))];
            }
            $origin = $cur['origin_ip'] ?? null;
            if ($origin && $origin !== $rec['origin_ip']) {
                $this->pdo->prepare('UPDATE edge_guard_records SET origin_ip = ? WHERE record_id = ?')->execute([$origin, $rec['record_id']]);
            }
        }

        if (!$errors) {
            // Registros que ya no existen o ya no son del CMS (solo si la lectura fue completa)
            foreach ($this->pdo->query('SELECT record_id FROM edge_guard_records')->fetchAll(PDO::FETCH_COLUMN) as $rid) {
                if (!isset($seen[$rid])) {
                    $this->pdo->prepare('DELETE FROM edge_guard_records WHERE record_id = ?')->execute([$rid]);
                }
            }
        }
        $this->setSetting('inventory_at', (string) time());
        $this->setSetting('inventory_errors', $errors ? implode("\n", $errors) : null);

        return ['records' => $count, 'proxied' => $proxied, 'errors' => $errors];
    }

    private function upsertRecord(int $accountId, array $zone, array $r): void
    {
        $old = $this->pdo->prepare('SELECT edge_ips, edge_ips_probe FROM edge_guard_records WHERE record_id = ?');
        $old->execute([$r['id']]);
        $prev = $old->fetch(PDO::FETCH_ASSOC) ?: [];
        $prevEdge = (string) ($prev['edge_ips'] ?? '');
        // Las IPs de Cloudflare solo se pueden leer del DNS mientras el registro tiene el proxy puesto
        $edge = $r['proxied'] ? (implode(',', self::resolveA($r['name'])) ?: $prevEdge) : $prevEdge;
        $origin = $r['type'] === 'A' ? $r['content'] : (self::resolveA($r['content'])[0] ?? null);
        $this->pdo->prepare('DELETE FROM edge_guard_records WHERE record_id = ?')->execute([$r['id']]);
        $this->pdo->prepare('INSERT INTO edge_guard_records (account_id, zone_id, zone_name, record_id, name, type, content, proxied, ttl, edge_ips, edge_ips_probe, origin_ip, refreshed_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$accountId, $zone['id'], $zone['name'], $r['id'], $r['name'], $r['type'], $r['content'], $r['proxied'] ? 1 : 0, $r['ttl'], $edge ?: null, ($prev['edge_ips_probe'] ?? null) ?: null, $origin, self::now()]);
    }

    /** Une listas de IPs separadas por comas (sin repetir). */
    private static function mergeIps(?string ...$lists): string
    {
        $all = [];
        foreach ($lists as $l) {
            foreach (array_filter(explode(',', (string) $l)) as $ip) {
                $all[$ip] = true;
            }
        }

        return implode(',', array_keys($all));
    }

    /** @return string[] IPv4 del nombre */
    private static function resolveA(string $host): array
    {
        $ips = @gethostbynamel($host);

        return $ips ? array_values(array_unique($ips)) : [];
    }

    /** Registros del inventario; edge_ips incluye también las IPs que han visto las sondas en su país. */
    public function records(): array
    {
        $rows = $this->pdo->query('SELECT * FROM edge_guard_records ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['edge_ips'] = self::mergeIps($r['edge_ips'] ?? '', $r['edge_ips_probe'] ?? '') ?: null;
        }

        return $rows;
    }

    public function activeChanges(): array
    {
        return $this->pdo->query("SELECT * FROM edge_guard_changes WHERE status = 'active' ORDER BY changed_at")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function history(int $limit = 60): array
    {
        return $this->pdo->query('SELECT * FROM edge_guard_changes ORDER BY id DESC LIMIT ' . (int) $limit)->fetchAll(PDO::FETCH_ASSOC);
    }

    // ------------------------------------------------------------------ cambio manual de la configuración original

    /**
     * IPs de Cloudflare de una zona que ahora no están sanas (bloqueadas, o que fallaron y aún no llevan
     * el tiempo estable). Sirve para saber si poner el proxy ahora a un nombre de esa zona lo dejaría caído.
     * @param array|null $states edgeStates() ya calculado (para no repetirlo por cada fila)
     */
    public function zoneUnhealthyIps(string $zoneId, ?array $states = null): array
    {
        $states ??= $this->edgeStates();
        $ips = [];
        $stmt = $this->pdo->prepare('SELECT edge_ips, edge_ips_probe FROM edge_guard_records WHERE zone_id = ? AND proxied = 1');
        $stmt->execute([$zoneId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            foreach (array_filter(explode(',', self::mergeIps($row['edge_ips'], $row['edge_ips_probe']))) as $ip) {
                $ips[$ip] = true;
            }
        }
        $stmt = $this->pdo->prepare("SELECT edge_ips FROM edge_guard_changes WHERE zone_id = ? AND status = 'active' AND simulated = 0");
        $stmt->execute([$zoneId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $csv) {
            foreach (array_filter(explode(',', (string) $csv)) as $ip) {
                $ips[$ip] = true;
            }
        }
        $bad = [];
        foreach (array_keys($ips) as $ip) {
            $s = $states[$ip] ?? null;
            if ($s && ($s['state'] === 'blocked' || ($s['state'] !== 'clear' && !empty($s['had_fail'])))) {
                $bad[] = $ip;
            }
        }
        sort($bad);

        return $bad;
    }

    /**
     * Cambia a mano la configuración original de un registro (con o sin proxy), teniendo en cuenta los bloqueos:
     *  - a «con proxy» con su zona bloqueada: se deja directo y queda pendiente; al acabar el bloqueo se le pone el proxy;
     *  - a «sin proxy» estando sin proxy por un bloqueo: se anula el regreso (ya no se le volverá a poner).
     * @return string lo que se ha hecho, para el aviso
     */
    public function setOriginal(string $recordId, bool $wantProxied, string $who): string
    {
        $stmt = $this->pdo->prepare('SELECT * FROM edge_guard_records WHERE record_id = ?');
        $stmt->execute([$recordId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$r) {
            throw new \RuntimeException('Ese registro ya no está en el inventario; pulsa «Leer Cloudflare».');
        }
        $stmt = $this->pdo->prepare("SELECT * FROM edge_guard_changes WHERE record_id = ? AND status = 'active' AND simulated = 0 ORDER BY id DESC LIMIT 1");
        $stmt->execute([$recordId]);
        $active = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $name = $r['name'];

        if ($wantProxied) {
            if ($active || (int) $r['proxied']) {
                return "{$name} ya tiene «con proxy» como configuración original; no hay nada que cambiar.";
            }
            $bad = $this->isExcluded($name) ? [] : $this->zoneUnhealthyIps($r['zone_id']);
            if ($bad) {
                // Se deja directo y se apunta como si el escudo lo hubiera quitado: el regreso le pondrá el proxy
                $this->pdo->prepare('INSERT INTO edge_guard_changes (account_id, zone_id, zone_name, record_id, name, type, content, original_ttl, edge_ips, simulated, cause, status, changed_at, note) VALUES (?,?,?,?,?,?,?,?,?,0,?,?,?,?)')
                    ->execute([(int) $r['account_id'], $r['zone_id'], $r['zone_name'], $r['record_id'], $name, $r['type'], $r['content'], 1, implode(',', $bad), 'manual', 'active', self::now(), "Cambio manual de {$who}: pendiente de poner el proxy al acabar el bloqueo."]);
                $msg = "{$name}: configuración original cambiada a «con proxy» por {$who}. Su zona tiene IPs bloqueadas (" . implode(', ', $bad) . '), así que sigue directo y se le pondrá el proxy cuando acabe el bloqueo.';
                $this->event('info', $msg);

                return $msg;
            }
            $this->client((int) $r['account_id'])->setProxied($r['zone_id'], $r['record_id'], true, 1);
            $this->pdo->prepare('UPDATE edge_guard_records SET proxied = 1 WHERE record_id = ?')->execute([$recordId]);
            $this->setSetting('inventory_at', '0'); // en la próxima vuelta se leen sus IPs de Cloudflare
            $msg = "{$name}: configuración original cambiada a «con proxy» por {$who}; proxy puesto en Cloudflare.";
            $this->event('info', $msg);

            return $msg;
        }

        if ($active) {
            // Ya está directo por un bloqueo: basta con anular el regreso
            $this->pdo->prepare("UPDATE edge_guard_changes SET status = 'cancelled_user', restored_at = ?, note = ? WHERE id = ?")
                ->execute([self::now(), "Cambiado a «sin proxy» a mano por {$who}: no se volverá a poner el proxy.", $active['id']]);
            $msg = "{$name}: configuración original cambiada a «sin proxy» por {$who}. Ya estaba directo por el bloqueo; al acabar no se le volverá a poner el proxy.";
            $this->event('info', $msg);

            return $msg;
        }
        if (!(int) $r['proxied']) {
            return "{$name} ya tiene «sin proxy» como configuración original; no hay nada que cambiar.";
        }
        $this->client((int) $r['account_id'])->setProxied($r['zone_id'], $r['record_id'], false, 1);
        $this->pdo->prepare('UPDATE edge_guard_records SET proxied = 0 WHERE record_id = ?')->execute([$recordId]);
        $msg = "{$name}: configuración original cambiada a «sin proxy» por {$who}; proxy quitado en Cloudflare.";
        $this->event('info', $msg);

        return $msg;
    }

    public function clearLog(): void
    {
        $this->pdo->exec('DELETE FROM edge_guard_events');
        $this->pdo->exec("DELETE FROM edge_guard_changes WHERE status <> 'active'");
    }

    public function events(int $limit = 40): array
    {
        return $this->pdo->query('SELECT * FROM edge_guard_events ORDER BY id DESC LIMIT ' . (int) $limit)->fetchAll(PDO::FETCH_ASSOC);
    }

    // ------------------------------------------------------------------ API de sondas

    /**
     * Qué tiene que probar una sonda: cada IP de Cloudflare (con un nombre para el SNI) y, para los registros
     * cuyas IPs están fallando, la IP directa del origen.
     * @return array<int, array{id:string, ip:string, host:string}>
     */
    public function targets(): array
    {
        $targets = [];
        $suspect = $this->suspectEdgeIps();
        foreach ($this->records() as $r) {
            if (!(int) $r['proxied'] || $this->isExcluded($r['name'])) {
                continue;
            }
            foreach (array_filter(explode(',', (string) $r['edge_ips'])) as $ip) {
                $targets['cf:' . $ip] ??= ['id' => 'cf:' . $ip, 'ip' => $ip, 'host' => $r['name']];
                if (isset($suspect[$ip]) && $r['origin_ip']) {
                    $targets['origin:' . $r['record_id']] = ['id' => 'origin:' . $r['record_id'], 'ip' => $r['origin_ip'], 'host' => $r['name']];
                }
            }
            // Que la sonda resuelva el nombre desde su país: Cloudflare puede dar allí otras IPs
            $targets['dns:' . $r['record_id']] = ['id' => 'dns:' . $r['record_id'], 'ip' => '-', 'host' => $r['name']];
        }
        // Lo que está cambiado: seguir probando sus IPs de Cloudflare para saber cuándo vuelven
        foreach ($this->activeChanges() as $c) {
            foreach (array_filter(explode(',', (string) $c['edge_ips'])) as $ip) {
                $targets['cf:' . $ip] ??= ['id' => 'cf:' . $ip, 'ip' => $ip, 'host' => $c['name']];
            }
        }

        return array_values($targets);
    }

    /**
     * Guarda un informe de sonda.
     * $paths: [{path, public_ip, control_ok, results:[{t, ok, code, ms}]}]
     * $dns:   [{t: "dns:<record_id>", ips: [...]}] IPs que da el DNS en el país de la sonda
     */
    public function ingest(string $probe, array $paths, array $dns = []): int
    {
        $known = array_flip(array_column($this->targets(), 'id'));
        $now = self::now();
        $upd = $this->pdo->prepare('UPDATE edge_guard_records SET edge_ips_probe = ? WHERE record_id = ? AND proxied = 1 AND (origin_ip IS NULL OR origin_ip <> ?)');
        foreach (array_slice($dns, 0, 1000) as $d) {
            $t = is_array($d) ? (string) ($d['t'] ?? '') : '';
            if (!isset($known[$t]) || !str_starts_with($t, 'dns:')) {
                continue;
            }
            $ips = array_values(array_unique(array_filter((array) ($d['ips'] ?? []), fn ($ip) => is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))));
            $ips = array_slice($ips, 0, 8);
            if ($ips) {
                sort($ips);
                $upd->execute([implode(',', $ips), substr($t, 4), $ips[0]]);
            }
        }
        $ins = $this->pdo->prepare('INSERT INTO edge_guard_observations (probe, path, target, ok, code, ms, public_ip, created_at) VALUES (?,?,?,?,?,?,?,?)');
        $n = 0;
        foreach (array_slice($paths, 0, 6) as $p) {
            if (!is_array($p)) {
                continue;
            }
            $path = substr(preg_replace('/[^A-Za-z0-9._-]/', '', (string) ($p['path'] ?? '')) ?: 'default', 0, 40);
            $pub = substr(preg_replace('/[^0-9a-fA-F.:]/', '', (string) ($p['public_ip'] ?? '')), 0, 64) ?: null;
            $ins->execute([$probe, $path, 'control', !empty($p['control_ok']) ? 1 : 0, null, null, $pub, $now]);
            $n++;
            foreach (array_slice((array) ($p['results'] ?? []), 0, 500) as $res) {
                $t = is_array($res) ? (string) ($res['t'] ?? '') : '';
                if (!isset($known[$t])) {
                    continue; // solo objetivos que este servidor pidió
                }
                $ins->execute([$probe, $path, $t, !empty($res['ok']) ? 1 : 0, substr((string) ($res['code'] ?? ''), 0, 10), isset($res['ms']) ? (int) $res['ms'] : null, $pub, $now]);
                $n++;
            }
        }

        return $n;
    }

    // ------------------------------------------------------------------ estado

    /** Caminos de sonda vivos (control OK reciente). @return array<string, array{probe:string, path:string, public_ip:?string, last:string}> */
    public function livePaths(): array
    {
        $stmt = $this->pdo->prepare("SELECT probe, path, MAX(created_at) AS last FROM edge_guard_observations WHERE target = 'control' AND ok = 1 AND created_at >= ? GROUP BY probe, path ORDER BY probe, path");
        $stmt->execute([self::now(-self::PROBE_STALE)]);
        $ipStmt = $this->pdo->prepare("SELECT public_ip FROM edge_guard_observations WHERE probe = ? AND path = ? AND target = 'control' ORDER BY id DESC LIMIT 1");
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $ipStmt->execute([$r['probe'], $r['path']]);
            $out[$r['probe'] . '/' . $r['path']] = ['probe' => $r['probe'], 'path' => $r['path'], 'public_ip' => $ipStmt->fetchColumn() ?: null, 'last' => (string) $r['last']];
        }

        return $out;
    }

    /** Todas las sondas que han informado alguna vez en 3 días (vivas o no), para la pantalla. */
    public function probePaths(): array
    {
        return $this->pdo->query("SELECT probe, path, MAX(created_at) AS last, MAX(public_ip) AS public_ip FROM edge_guard_observations WHERE target = 'control' GROUP BY probe, path ORDER BY probe, path")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Observaciones de un objetivo por camino (más reciente primero). */
    private function observations(string $target, string $since): array
    {
        $stmt = $this->pdo->prepare('SELECT probe, path, ok, created_at FROM edge_guard_observations WHERE target = ? AND created_at >= ? ORDER BY id DESC');
        $stmt->execute([$target, $since]);
        $by = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $o) {
            $by[$o['probe'] . '/' . $o['path']][] = $o;
        }

        return $by;
    }

    /** IPs con algún fallo reciente (para pedir también la prueba directa al origen). */
    private function suspectEdgeIps(): array
    {
        $stmt = $this->pdo->prepare("SELECT DISTINCT target FROM edge_guard_observations WHERE target LIKE 'cf:%' AND ok = 0 AND created_at >= ?");
        $stmt->execute([self::now(-self::FAIL_WINDOW)]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $t) {
            $out[substr($t, 3)] = true;
        }

        return $out;
    }

    /**
     * Estado de cada IP de Cloudflare según las sondas vivas.
     * blocked: algún camino falla en los últimos informes seguidos · clear: todos los caminos responden desde hace
     * «restore_minutes» · recovering: entre medias · unknown: sin sondas vivas.
     * @return array<string, array{state:string, paths:array<string, ?bool>}>
     */
    public function edgeStates(): array
    {
        $live = $this->livePaths();
        $ips = [];
        foreach ($this->records() as $r) {
            foreach (array_filter(explode(',', (string) $r['edge_ips'])) as $ip) {
                $ips[$ip] = true;
            }
        }
        foreach ($this->activeChanges() as $c) {
            foreach (array_filter(explode(',', (string) $c['edge_ips'])) as $ip) {
                $ips[$ip] = true;
            }
        }
        $restoreSecs = $this->restoreMinutes() * 60;
        $out = [];
        foreach (array_keys($ips) as $ip) {
            $recent = $this->observations('cf:' . $ip, self::now(-self::FAIL_WINDOW));
            $long = $this->observations('cf:' . $ip, self::now(-$restoreSecs));
            $paths = [];
            $blocked = false;
            $allClear = (bool) $live;
            foreach ($live as $key => $_) {
                $obs = $recent[$key] ?? [];
                $paths[$key] = $obs ? (int) $obs[0]['ok'] === 1 : null;
                $lastN = array_slice($obs, 0, self::FAILS_TO_BLOCK);
                if (count($lastN) >= self::FAILS_TO_BLOCK && !array_filter($lastN, fn ($o) => (int) $o['ok'] === 1)) {
                    $blocked = true;
                }
                $window = $long[$key] ?? [];
                $oldest = $window ? end($window)['created_at'] : null;
                $covered = $oldest !== null && strtotime($oldest . ' UTC') <= time() - $restoreSecs + 180;
                if (!$window || array_filter($window, fn ($o) => (int) $o['ok'] === 0) || !$covered) {
                    $allClear = false;
                }
            }
            $hadFail = false;
            foreach ($long as $obsList) {
                if (array_filter($obsList, fn ($o) => (int) $o['ok'] === 0)) {
                    $hadFail = true;
                }
            }
            $state = !$live ? 'unknown' : ($blocked ? 'blocked' : ($allClear ? 'clear' : 'recovering'));
            $out[$ip] = ['state' => $state, 'paths' => $paths, 'had_fail' => $hadFail];
        }
        ksort($out);

        return $out;
    }

    /** ¿Responde el origen directo (con certificado válido) según la sonda? null = aún sin prueba. */
    private function originOk(string $recordId): ?bool
    {
        $stmt = $this->pdo->prepare('SELECT ok FROM edge_guard_observations WHERE target = ? AND created_at >= ? ORDER BY id DESC LIMIT 6');
        $stmt->execute(['origin:' . $recordId, self::now(-self::ORIGIN_FRESH)]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!$rows) {
            return null;
        }

        return in_array(1, array_map('intval', $rows), true);
    }

    // ------------------------------------------------------------------ ciclo

    /**
     * Una vuelta del vigilante (cron). Devuelve lo hecho, para el aviso por correo.
     * @return array{applied:string[], restored:string[], notes:string[]}
     */
    public function run(bool $forceInventory = false): array
    {
        $report = ['applied' => [], 'restored' => [], 'notes' => []];
        $mode = $this->mode();
        if ($forceInventory || time() - (int) $this->setting('inventory_at', '0') >= self::INVENTORY_EVERY) {
            $inv = $this->refreshInventory();
            if ($inv['errors']) {
                $this->noteOnce('inventory', 'Lectura de Cloudflare con errores: ' . implode('; ', $inv['errors']), $report);
            }
        }
        $this->pdo->prepare('DELETE FROM edge_guard_observations WHERE created_at < ?')->execute([self::now(-3 * 86400)]);
        $this->pdo->prepare('DELETE FROM edge_guard_events WHERE created_at < ?')->execute([self::now(-30 * 86400)]);

        // Al salir de la simulación, lo simulado deja de tener sentido
        if ($mode !== 'simulate') {
            $this->pdo->prepare("UPDATE edge_guard_changes SET status = 'sim_closed', restored_at = ? WHERE status = 'active' AND simulated = 1")->execute([self::now()]);
        }

        $live = $this->livePaths();
        if (!$live) {
            if (($this->activeChanges() || $mode !== 'off') && $this->setting('stale_noted') !== '1') {
                $this->setSetting('stale_noted', '1');
                $msg = 'Ninguna sonda ha informado en los últimos ' . (self::PROBE_STALE / 60) . ' minutos: no se cambia nada hasta que vuelvan.';
                $this->event('warn', $msg);
                $report['notes'][] = $msg;
            }

            return $report;
        }
        $this->setSetting('stale_noted', null);

        $states = $this->edgeStates();
        $this->trackEpisodes($states, $report);

        // 1. Volver a poner el proxy donde ya se puede (también en modo apagado: lo que cambió el sistema, lo deshace)
        foreach ($this->activeChanges() as $c) {
            $ips = array_filter(explode(',', (string) $c['edge_ips']));
            $clear = $ips && !array_filter($ips, fn ($ip) => ($states[$ip]['state'] ?? 'unknown') !== 'clear');
            if ($clear) {
                $report['restored'][] = $this->restore($c);
            }
        }

        if ($mode === 'off') {
            return $report;
        }

        // 2. Quitar el proxy donde hay bloqueo
        $active = [];
        foreach ($this->activeChanges() as $c) {
            $active[$c['record_id']] = true;
        }
        foreach ($this->records() as $r) {
            if (!(int) $r['proxied'] || isset($active[$r['record_id']]) || $this->isExcluded($r['name'])) {
                continue;
            }
            $ips = array_filter(explode(',', (string) $r['edge_ips']));
            $blockedIps = array_values(array_filter($ips, fn ($ip) => ($states[$ip]['state'] ?? '') === 'blocked'));
            if (!$blockedIps) {
                continue;
            }
            $origin = $this->originOk($r['record_id']);
            if ($origin === null) {
                continue; // la sonda aún no ha probado el origen: ya se le pide en la lista de objetivos
            }
            if ($origin === false) {
                $this->noteOnce('origin:' . $r['record_id'], "{$r['name']}: Cloudflare bloqueado, pero el servidor de origen no responde directo con un certificado válido; se deja como está.", $report);
                continue;
            }
            $report['applied'][] = $this->unproxy($r, $blockedIps, $mode === 'simulate');
        }

        return $report;
    }

    private function noteOnce(string $key, string $msg, array &$report): void
    {
        $k = 'note_' . md5($key);
        if ((int) $this->setting($k, '0') > time() - 3600) {
            return;
        }
        $this->setSetting($k, (string) time());
        $this->event('warn', $msg);
        $report['notes'][] = $msg;
    }

    /** Desde este servidor (normalmente fuera de España): si aquí responde, es un bloqueo de las operadoras. */
    private function cause(array $ips, string $host): string
    {
        foreach ($ips as $ip) {
            $ch = curl_init('https://' . $host . '/');
            curl_setopt_array($ch, [CURLOPT_RESOLVE => [$host . ':443:' . $ip], CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6, CURLOPT_CONNECTTIMEOUT => 4]);
            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if ($code > 0) {
                return 'bloqueo';
            }
        }

        return 'cloudflare';
    }

    private function client(int $accountId): CloudflareClient
    {
        foreach (DnsAccounts::make()->cloudflareAccounts(true) as $acc) {
            if ((int) $acc['id'] === $accountId) {
                return new CloudflareClient((string) $acc['api_token_decrypted'], $acc['account_id'] ?: null);
            }
        }
        throw new \RuntimeException('la cuenta de Cloudflare ya no está disponible');
    }

    private function unproxy(array $r, array $blockedIps, bool $simulated): string
    {
        $cause = $this->cause($blockedIps, $r['name']);
        $why = $cause === 'bloqueo' ? 'IP de Cloudflare bloqueada por operadoras; desde fuera responde' : 'Cloudflare no responde, tampoco desde fuera';
        $status = 'active';
        $note = null;
        if (!$simulated) {
            try {
                $cf = $this->client((int) $r['account_id']);
                $now = $cf->getRecord($r['zone_id'], $r['record_id']);
                if (!$now['proxied'] || $now['content'] !== $r['content']) {
                    $this->pdo->prepare('UPDATE edge_guard_records SET proxied = ?, content = ? WHERE record_id = ?')->execute([$now['proxied'] ? 1 : 0, $now['content'], $r['record_id']]);

                    return "{$r['name']}: cambió en Cloudflare desde la última lectura; no se toca.";
                }
                $cf->setProxied($r['zone_id'], $r['record_id'], false, 60);
                $this->pdo->prepare('UPDATE edge_guard_records SET proxied = 0 WHERE record_id = ?')->execute([$r['record_id']]);
            } catch (\Throwable $e) {
                $status = 'failed';
                $note = $e->getMessage();
            }
        }
        $this->pdo->prepare('INSERT INTO edge_guard_changes (account_id, zone_id, zone_name, record_id, name, type, content, original_ttl, edge_ips, simulated, cause, status, changed_at, note) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([(int) $r['account_id'], $r['zone_id'], $r['zone_name'], $r['record_id'], $r['name'], $r['type'], $r['content'], (int) $r['ttl'], (string) $r['edge_ips'], $simulated ? 1 : 0, $cause, $status, self::now(), $note]);
        $ipList = implode(', ', $blockedIps);
        $msg = match (true) {
            $status === 'failed' => "{$r['name']}: no se pudo quitar el proxy ({$note}).",
            $simulated => "{$r['name']}: se habría quitado el proxy ({$why}; IPs {$ipList}).",
            default => "{$r['name']}: proxy quitado ({$why}; IPs {$ipList}).",
        };
        $this->event($status === 'failed' ? 'error' : 'info', $msg);

        return $msg;
    }

    /** Versión en inglés de una línea del informe (para los avisos de quien los quiere en inglés). */
    public static function lineEn(string $es): string
    {
        $es = strtr($es, [
            'IP de Cloudflare bloqueada por operadoras; desde fuera responde' => 'Cloudflare IP blocked by ISPs; reachable from abroad',
            'Cloudflare no responde, tampoco desde fuera' => 'Cloudflare not responding, not even from abroad',
        ]);
        $map = [
            '/^IP (\S+) bloqueada desde las (.+)\.$/u' => 'IP $1 blocked since $2.',
            '/^IP (\S+) vuelve a responder a las (.+)\. Bloqueada desde las (.+) \((.+)\)\.$/u' => 'IP $1 responds again at $2. Blocked since $3 ($4).',
            '/^Ninguna sonda ha informado en los últimos (\d+) minutos: no se cambia nada hasta que vuelvan\.$/u' => 'No probe has reported in the last $1 minutes: nothing will be changed until they are back.',
            '/^(.+): Cloudflare bloqueado, pero el servidor de origen no responde directo con un certificado válido; se deja como está\.$/u' => '$1: Cloudflare blocked, but the origin server does not answer directly with a valid certificate; left as it is.',
            '/^(.+): cambió en Cloudflare desde la última lectura; no se toca\.$/u' => '$1: changed in Cloudflare since the last read; not touched.',
            '/^(.+): no se pudo quitar el proxy \((.*)\)\.$/u' => '$1: could not turn the proxy off ($2).',
            '/^(.+): se habría quitado el proxy \((.+); IPs (.+)\)\.$/u' => '$1: the proxy would have been turned off ($2; IPs $3).',
            '/^(.+): proxy quitado \((.+); IPs (.+)\)\.$/u' => '$1: proxy turned off ($2; IPs $3).',
            '/^(.+): se habría vuelto a poner el proxy \(sus IPs llevan (\d+) min respondiendo\)\.$/u' => '$1: the proxy would have been turned back on (its IPs have been responding for $2 min).',
            '/^(.+): alguien lo cambió durante el bloqueo; se respeta su cambio y no se toca\.$/u' => '$1: someone changed it during the block; their change is kept and it is not touched.',
            '/^(.+): proxy puesto de nuevo \(sus IPs llevan (\d+) min respondiendo\)\.$/u' => '$1: proxy turned back on (its IPs have been responding for $2 min).',
            '/^(.+): no se pudo volver a poner el proxy \((.*)\); se reintentará\.$/u' => '$1: could not turn the proxy back on ($2); will retry.',
        ];
        foreach ($map as $re => $en) {
            $out = preg_replace($re, $en, $es, 1, $n);
            if ($n) {
                return (string) $out;
            }
        }

        return $es;
    }

    private function restore(array $c): string
    {
        $mins = $this->restoreMinutes();
        if ((int) $c['simulated'] === 1) {
            $this->pdo->prepare("UPDATE edge_guard_changes SET status = 'restored', restored_at = ? WHERE id = ?")->execute([self::now(), $c['id']]);
            $msg = "{$c['name']}: se habría vuelto a poner el proxy (sus IPs llevan {$mins} min respondiendo).";
            $this->event('info', $msg);

            return $msg;
        }
        try {
            $cf = $this->client((int) $c['account_id']);
            $now = $cf->getRecord($c['zone_id'], $c['record_id']);
            if ($now['proxied'] || $now['content'] !== $c['content'] || $now['type'] !== $c['type']) {
                $this->pdo->prepare("UPDATE edge_guard_changes SET status = 'kept_manual', restored_at = ?, note = ? WHERE id = ?")
                    ->execute([self::now(), 'El registro se cambió a mano mientras tanto; se respeta.', $c['id']]);
                $msg = "{$c['name']}: alguien lo cambió durante el bloqueo; se respeta su cambio y no se toca.";
                $this->event('warn', $msg);

                return $msg;
            }
            $cf->setProxied($c['zone_id'], $c['record_id'], true, (int) $c['original_ttl']);
            $this->pdo->prepare('UPDATE edge_guard_records SET proxied = 1 WHERE record_id = ?')->execute([$c['record_id']]);
            $this->pdo->prepare("UPDATE edge_guard_changes SET status = 'restored', restored_at = ? WHERE id = ?")->execute([self::now(), $c['id']]);
            $msg = "{$c['name']}: proxy puesto de nuevo (sus IPs llevan {$mins} min respondiendo).";
            $this->event('info', $msg);

            return $msg;
        } catch (\Throwable $e) {
            $msg = "{$c['name']}: no se pudo volver a poner el proxy ({$e->getMessage()}); se reintentará.";
            $this->event('error', $msg);

            return $msg;
        }
    }
}
